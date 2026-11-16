<?php

namespace App\Services\Accounting;

use App\Exceptions\AcademicRuleException;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\GlLine;
use App\Models\GlPeriodLock;
use App\Models\User;
use App\Services\Ops\Ops;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Posts balanced journals into the school GL. Never posts unbalanced or into a locked period.
 * Idempotent for source documents: same school + source + source model id does not double-post.
 */
class JournalPoster
{
    public function __construct(private readonly ChartOfAccounts $coa) {}

    /**
     * @param  list<array{account_id:int,debit?:int,credit?:int,memo?:?string}>  $lines
     */
    public function post(
        string $source,
        string $date,
        string $memo,
        array $lines,
        ?User $actor = null,
        ?Model $sourceModel = null,
        ?int $reversesJournalId = null,
    ): ?GlJournal {
        $this->coa->ensureFor();

        if ($sourceModel) {
            $existing = GlJournal::query()
                ->where('source', $source)
                ->where('source_ref_type', $sourceModel->getMorphClass())
                ->where('source_ref_id', $sourceModel->getKey())
                ->whereIn('status', ['posted', 'reversed'])
                ->first();
            if ($existing) {
                return $existing;
            }
        }

        $normalised = $this->normaliseLines($lines);
        $debit = (int) array_sum(array_column($normalised, 'debit'));
        $credit = (int) array_sum(array_column($normalised, 'credit'));
        if ($debit < 1 || $debit !== $credit) {
            throw AcademicRuleException::because('err.ais.unbalanced');
        }

        $this->assertPeriodOpen($date);

        return DB::transaction(function () use ($source, $date, $memo, $normalised, $actor, $sourceModel, $reversesJournalId, $debit) {
            if ($sourceModel) {
                $dup = GlJournal::query()->lockForUpdate()
                    ->where('source', $source)
                    ->where('source_ref_type', $sourceModel->getMorphClass())
                    ->where('source_ref_id', $sourceModel->getKey())
                    ->whereIn('status', ['posted', 'reversed'])
                    ->first();
                if ($dup) {
                    return $dup;
                }
            }

            $journal = GlJournal::query()->create([
                'number' => Ops::nextNumber('gl_journals', 'JV', 'number'),
                'date' => $date,
                'memo' => mb_substr($memo, 0, 190),
                'status' => 'posted',
                'source' => $source,
                'source_ref_type' => $sourceModel?->getMorphClass(),
                'source_ref_id' => $sourceModel?->getKey(),
                'posted_by' => $actor?->id,
                'posted_at' => now(),
                'reverses_journal_id' => $reversesJournalId,
            ]);

            foreach ($normalised as $line) {
                GlLine::query()->create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                    'memo' => $line['memo'],
                ]);
            }

            Ops::audit('ais.journal.posted', 'Journal '.$journal->number.' posted', [
                'journal_id' => $journal->id, 'source' => $source, 'amount' => $debit,
            ]);

            return $journal->load('lines');
        });
    }

    /** Reverse a posted journal with an opposite entry. Does not edit the original. */
    public function reverse(GlJournal $journal, User $actor, ?string $memo = null): GlJournal
    {
        $journal = GlJournal::query()->with('lines')->findOrFail($journal->id);
        if ($journal->status !== 'posted') {
            throw AcademicRuleException::because('err.ais.notPosted');
        }
        if (GlJournal::query()->where('reverses_journal_id', $journal->id)->where('status', 'posted')->exists()) {
            throw AcademicRuleException::because('err.ais.alreadyReversed');
        }

        $lines = $journal->lines->map(fn (GlLine $l) => [
            'account_id' => (int) $l->account_id,
            'debit' => (int) $l->credit,
            'credit' => (int) $l->debit,
            'memo' => $l->memo,
        ])->all();

        $reversal = $this->post(
            'reversal',
            now()->toDateString(),
            $memo ?: 'Reversal of '.$journal->number,
            $lines,
            $actor,
            null,
            (int) $journal->id,
        );

        $journal->forceFill([
            'status' => 'reversed',
            'reversed_by' => $actor->id,
            'reversed_at' => now(),
        ])->save();

        return $reversal;
    }

    /**
     * Auto-post helper: skip silently when required maps are missing (never fail the source transaction).
     *
     * @param  list<array{account_id:int,debit?:int,credit?:int,memo?:?string}>  $lines
     */
    public function tryPost(
        string $source,
        string $date,
        string $memo,
        array $lines,
        ?User $actor = null,
        ?Model $sourceModel = null,
    ): ?GlJournal {
        try {
            if ($lines === []) {
                return null;
            }

            return $this->post($source, $date, $memo, $lines, $actor, $sourceModel);
        } catch (AcademicRuleException) {
            return null;
        } catch (\Throwable) {
            report(new \RuntimeException('AIS auto-post failed for '.$source));

            return null;
        }
    }

    public function assertPeriodOpen(string $date): void
    {
        $ym = substr($date, 0, 7);
        if (GlPeriodLock::query()->where('year_month', $ym)->exists()) {
            throw AcademicRuleException::because('err.ais.periodLocked');
        }
    }

    /**
     * @param  list<array{account_id:int,debit?:int,credit?:int,memo?:?string}>  $lines
     * @return list<array{account_id:int,debit:int,credit:int,memo:?string}>
     */
    private function normaliseLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $debit = max(0, (int) ($line['debit'] ?? 0));
            $credit = max(0, (int) ($line['credit'] ?? 0));
            if (($debit === 0 && $credit === 0) || ($debit > 0 && $credit > 0)) {
                throw AcademicRuleException::because('err.ais.badLine');
            }
            $accountId = (int) ($line['account_id'] ?? 0);
            if (! GlAccount::query()->whereKey($accountId)->where('is_active', true)->exists()) {
                throw AcademicRuleException::because('err.ais.badAccount');
            }
            $out[] = [
                'account_id' => $accountId,
                'debit' => $debit,
                'credit' => $credit,
                'memo' => isset($line['memo']) ? mb_substr((string) $line['memo'], 0, 190) : null,
            ];
        }
        if (count($out) < 2) {
            throw AcademicRuleException::because('err.ais.needTwoLines');
        }

        return $out;
    }
}
