<?php

namespace App\Services\SeniorSecondary;

use App\Exceptions\AcademicRuleException;
use App\Models\School;
use App\Models\SsApplication;
use App\Models\SsApplicationDocument;
use App\Models\SsApplicationEvent;
use App\Models\SsApplicationResult;
use App\Models\SsRound;
use App\Models\User;
use App\Services\Records\SchoolSequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Senior Secondary admission workflow. The engine only RECOMMENDS; every status change is a human action that
 * is written to `ss_application_events`, and a decision that departs from the recommendation (or from capacity)
 * is flagged as an override and needs a written reason.
 */
class SeniorSecondaryService
{
    public const STATUSES = ['submitted', 'under_review', 'shortlisted', 'waitlisted', 'approved', 'rejected', 'withdrawn'];

    public const DECISIONS = ['shortlisted', 'waitlisted', 'approved', 'rejected'];

    public function __construct(protected RulesEngine $engine, protected SchoolSequence $sequence) {}

    // ------------------------------------------------------------------ rounds

    public function saveRound(array $data, array $rules, ?SsRound $round = null): SsRound
    {
        $normalised = $this->engine->normalise($rules);

        if ($data['opens_on'] && $data['closes_on'] && $data['closes_on'] < $data['opens_on']) {
            throw AcademicRuleException::because('err.ss.periodInvalid');
        }
        if ($round && $round->status === 'finalised') {
            throw AcademicRuleException::because('err.ss.finalised');
        }
        // Changing the rules after applications exist is allowed (recommendations are recomputed), but never after finalisation.
        $payload = [
            'name' => $data['name'], 'instructions' => $data['instructions'] ?? null,
            'opens_on' => $data['opens_on'] ?: null, 'closes_on' => $data['closes_on'] ?: null,
            'capacity' => (int) $data['capacity'], 'waitlist_capacity' => (int) ($data['waitlist_capacity'] ?? 0),
            'rules' => $normalised,
        ];
        if ($round) {
            $round->update($payload);
            $this->recompute($round->refresh());

            return $round;
        }

        $slug = Str::slug($data['name']) ?: 'round';
        $base = $slug;
        $i = 1;
        while (SsRound::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return SsRound::query()->create($payload + ['slug' => $slug, 'status' => 'draft']);
    }

    public function setRoundStatus(SsRound $round, string $status): void
    {
        $allowed = ['draft' => ['open'], 'open' => ['closed', 'draft'], 'closed' => ['open', 'finalised'], 'finalised' => []];
        if (! in_array($status, $allowed[$round->status] ?? [], true)) {
            throw AcademicRuleException::because('err.ss.roundTransition');
        }
        if ($status === 'open' && ($round->capacity < 1 || empty($round->rules))) {
            throw AcademicRuleException::because('err.ss.roundIncomplete');
        }
        $round->update(['status' => $status]);
    }

    public function isAcceptingApplications(SsRound $round): bool
    {
        if ($round->status !== 'open') {
            return false;
        }
        $today = now()->startOfDay();

        return ! (($round->opens_on && $today->lt($round->opens_on)) || ($round->closes_on && $today->gt($round->closes_on)));
    }

    // ------------------------------------------------------------------ submission (public or desk)

    /**
     * @param  array<string,mixed>  $data  applicant fields
     * @param  array<string,string>  $grades  subject code => declared grade
     * @param  array<int,array{type:string,file:UploadedFile}>  $uploads
     */
    public function submit(School $school, SsRound $round, array $data, array $grades, string $source, array $uploads = [], ?string $ip = null): SsApplication
    {
        if (! $this->isAcceptingApplications($round)) {
            throw AcademicRuleException::because('err.ss.notOpen');
        }
        $rules = $round->rules;
        $known = collect($rules['subjects'])->pluck('name', 'code');
        $clean = [];
        foreach ($grades as $code => $grade) {
            $code = strtoupper((string) $code);
            $grade = strtoupper(trim((string) $grade));
            if ($grade === '') {
                continue;
            }
            if (! $known->has($code) || ! array_key_exists($grade, $rules['grade_points'])) {
                throw AcademicRuleException::because('err.ss.gradeInvalid');
            }
            $clean[$code] = $grade;
        }
        if ($clean === []) {
            throw AcademicRuleException::because('err.ss.noResults');
        }
        if (! empty($data['programme_choice']) && ! collect($rules['combinations'])->contains('name', $data['programme_choice'])) {
            throw AcademicRuleException::because('err.ss.programmeInvalid');
        }

        return DB::transaction(function () use ($school, $round, $data, $clean, $known, $source, $uploads, $ip) {
            $n = $this->sequence->next($school->id, 'ss_application');
            $app = SsApplication::query()->create($data + [
                'school_id' => $school->id, 'round_id' => $round->id, 'source' => $source, 'status' => 'submitted',
                'reference' => sprintf('SSA-%s-%05d', now()->format('Y'), $n),
                'consent_at' => $source === 'online' ? now() : null, 'submitted_ip' => $ip,
            ]);
            foreach ($clean as $code => $grade) {
                SsApplicationResult::query()->create([
                    'school_id' => $school->id, 'application_id' => $app->id,
                    'subject_code' => $code, 'subject_name' => $known[$code], 'grade' => $grade,
                ]);
            }
            foreach ($uploads as $u) {
                $file = $u['file'];
                $path = $file->store('senior-secondary/'.$school->id.'/'.$app->id, config('schoolos.students.document_disk', 'local'));
                SsApplicationDocument::query()->create([
                    'school_id' => $school->id, 'application_id' => $app->id, 'type' => $u['type'],
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 190), 'path' => $path,
                    'mime' => $file->getClientMimeType(), 'size' => $file->getSize(),
                ]);
            }
            $this->event($app, null, 'submitted', null, 'submitted', $source === 'online' ? 'Online application' : 'Entered at the desk');
            $this->assess($app->refresh(), $round);
            $this->rank($round);

            return $app->refresh();
        });
    }

    // ------------------------------------------------------------------ assessment & ranking

    /** Compute aggregate / eligibility for one application (advisory) and log it. */
    public function assess(SsApplication $app, ?SsRound $round = null): SsApplication
    {
        $round ??= $app->round;
        $grades = $app->results()->pluck('grade', 'subject_code')->all();
        $res = $this->engine->assess($round->rules, $grades, $app->programme_choice);

        $app->update([
            'aggregate' => $res['aggregate'], 'eligible' => $res['eligible'],
            'assessment' => ['reasons' => $res['reasons'], 'counted' => $res['counted'], 'combination' => $res['combination']],
            'computed_at' => now(),
        ]);

        return $app;
    }

    /** Re-assess every open application of a round and recompute ranks and recommendations. */
    public function recompute(SsRound $round, ?User $user = null): void
    {
        DB::transaction(function () use ($round, $user) {
            $apps = SsApplication::query()->where('round_id', $round->id)->whereNotIn('status', ['withdrawn'])->get();
            foreach ($apps as $app) {
                $this->assess($app, $round);
            }
            $this->rank($round);
            $round->update(['ranked_at' => now()]);
            if ($user) {
                foreach ($apps as $app) {
                    $this->event($app->refresh(), $user->id, 'recomputed', $app->status, $app->status, null, ['rank' => $app->rank_position, 'recommendation' => $app->recommendation]);
                }
            }
        });
    }

    /**
     * Ranking is limited to applications whose declared results a human has VERIFIED. Eligible ones are ordered
     * best aggregate first (ties: earlier submission, then lower id) and the recommendation follows capacity.
     */
    public function rank(SsRound $round): void
    {
        $lower = ($round->rules['direction'] ?? 'lower_better') === 'lower_better';
        $apps = SsApplication::query()->where('round_id', $round->id)->where('status', '!=', 'withdrawn')->get();

        $ranked = $apps->filter(fn ($a) => $a->eligible && $a->results_verified_at !== null && $a->aggregate !== null)
            ->sort(function ($a, $b) use ($lower) {
                $cmp = $lower ? (float) $a->aggregate <=> (float) $b->aggregate : (float) $b->aggregate <=> (float) $a->aggregate;

                return $cmp ?: ($a->created_at <=> $b->created_at) ?: ($a->id <=> $b->id);
            })->values();

        $rankById = [];
        foreach ($ranked as $i => $a) {
            $rankById[$a->id] = $i + 1;
        }

        foreach ($apps as $a) {
            $rank = $rankById[$a->id] ?? null;
            if ($a->results_verified_at === null) {
                $rec = 'review';
            } elseif (! $a->eligible) {
                $rec = 'reject';
            } elseif ($rank <= $round->capacity) {
                $rec = 'shortlist';
            } elseif ($rank <= $round->capacity + $round->waitlist_capacity) {
                $rec = 'waitlist';
            } else {
                $rec = 'reject';
            }
            if ($a->rank_position !== $rank || $a->recommendation !== $rec) {
                $a->update(['rank_position' => $rank, 'recommendation' => $rec]);
            }
        }
    }

    // ------------------------------------------------------------------ human steps

    public function verifyResults(User $user, SsApplication $app, ?array $corrected = null, ?string $note = null): SsApplication
    {
        $this->assertOpenForReview($app);
        DB::transaction(function () use ($user, $app, $corrected, $note) {
            if ($corrected !== null) {
                $rules = $app->round->rules;
                foreach ($corrected as $code => $grade) {
                    $code = strtoupper((string) $code);
                    $grade = strtoupper(trim((string) $grade));
                    $row = SsApplicationResult::query()->where('application_id', $app->id)->where('subject_code', $code)->first();
                    if ($grade === '' || ! array_key_exists($grade, $rules['grade_points'])) {
                        if ($grade === '' && $row) {
                            $this->event($app, $user->id, 'result_removed', null, null, $note, ['subject' => $code, 'from' => $row->grade]);
                            $row->delete();
                        } elseif ($grade !== '') {
                            throw AcademicRuleException::because('err.ss.gradeInvalid');
                        }

                        continue;
                    }
                    if ($row && $row->grade !== $grade) {
                        $this->event($app, $user->id, 'result_corrected', null, null, $note, ['subject' => $code, 'from' => $row->grade, 'to' => $grade]);
                        $row->update(['grade' => $grade]);
                    } elseif (! $row && ($name = collect($rules['subjects'])->firstWhere('code', $code)['name'] ?? null)) {
                        SsApplicationResult::query()->create(['school_id' => $app->school_id, 'application_id' => $app->id, 'subject_code' => $code, 'subject_name' => $name, 'grade' => $grade]);
                        $this->event($app, $user->id, 'result_added', null, null, $note, ['subject' => $code, 'to' => $grade]);
                    }
                }
            }
            $app->update(['results_verified_at' => now(), 'results_verified_by' => $user->id,
                'status' => $app->status === 'submitted' ? 'under_review' : $app->status]);
            $this->event($app, $user->id, 'results_verified', null, null, $note);
            $this->assess($app->refresh());
            $this->rank($app->round);
        });

        return $app->refresh();
    }

    /** Bulk-apply the engine's shortlist / waiting-list recommendations to applications still awaiting a decision. */
    public function applyRecommendations(User $user, SsRound $round): int
    {
        if ($round->status === 'finalised') {
            throw AcademicRuleException::because('err.ss.finalised');
        }
        $this->rank($round);
        $map = ['shortlist' => 'shortlisted', 'waitlist' => 'waitlisted'];
        $n = 0;
        DB::transaction(function () use ($user, $round, $map, &$n) {
            foreach (SsApplication::query()->where('round_id', $round->id)->whereIn('status', ['submitted', 'under_review'])->whereIn('recommendation', array_keys($map))->get() as $app) {
                $from = $app->status;
                $app->update(['status' => $map[$app->recommendation], 'is_override' => false, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_reason' => null]);
                $this->event($app, $user->id, 'recommendation_applied', $from, $app->status, null, ['rank' => $app->rank_position, 'recommendation' => $app->recommendation]);
                $n++;
            }
        });
        if ($n === 0) {
            throw AcademicRuleException::because('err.ss.nothingToApply');
        }

        return $n;
    }

    public function decide(User $user, SsApplication $app, string $decision, ?string $reason = null): SsApplication
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw AcademicRuleException::because('err.ss.decisionInvalid');
        }
        $this->assertOpenForReview($app);
        $round = $app->round;
        if ($round->status === 'finalised') {
            throw AcademicRuleException::because('err.ss.finalised');
        }
        $this->rank($round);
        $app->refresh();

        $recommended = ['shortlist' => ['shortlisted', 'approved'], 'waitlist' => ['waitlisted'], 'reject' => ['rejected']][$app->recommendation] ?? [];
        $override = ! in_array($decision, $recommended, true);

        // Approving beyond capacity is always an override.
        if ($decision === 'approved') {
            $approved = SsApplication::query()->where('round_id', $round->id)->where('status', 'approved')->where('id', '!=', $app->id)->count();
            if ($approved >= $round->capacity) {
                $override = true;
            }
            if ($app->results_verified_at === null) {
                throw AcademicRuleException::because('err.ss.verifyFirst');
            }
        }
        if ($override && blank($reason)) {
            throw AcademicRuleException::because('err.ss.overrideReason');
        }

        $from = $app->status;
        $app->update([
            'status' => $decision, 'is_override' => $override, 'decision_reason' => $reason,
            'decided_by' => $user->id, 'decided_at' => now(),
        ]);
        $this->event($app, $user->id, $override ? 'override' : 'decision', $from, $decision, $reason, [
            'recommendation' => $app->recommendation, 'rank' => $app->rank_position, 'eligible' => $app->eligible, 'aggregate' => $app->aggregate,
        ]);

        return $app;
    }

    public function withdraw(User $user, SsApplication $app, ?string $reason): void
    {
        $this->assertOpenForReview($app);
        $from = $app->status;
        $app->update(['status' => 'withdrawn', 'decision_reason' => $reason, 'decided_by' => $user->id, 'decided_at' => now()]);
        $this->event($app, $user->id, 'withdrawn', $from, 'withdrawn', $reason);
        $this->rank($app->round);
    }

    public function finalise(User $user, SsRound $round): void
    {
        if ($round->status !== 'closed') {
            throw AcademicRuleException::because('err.ss.closeFirst');
        }
        if (SsApplication::query()->where('round_id', $round->id)->whereIn('status', ['submitted', 'under_review'])->exists()) {
            throw AcademicRuleException::because('err.ss.undecided');
        }
        $round->update(['status' => 'finalised']);
    }

    /** @return Collection<int,SsApplicationEvent> */
    public function history(SsApplication $app): Collection
    {
        return SsApplicationEvent::query()->with('user')->where('application_id', $app->id)->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ helpers

    protected function assertOpenForReview(SsApplication $app): void
    {
        if ($app->status === 'withdrawn') {
            throw AcademicRuleException::because('err.ss.withdrawn');
        }
    }

    protected function event(SsApplication $app, ?int $userId, string $event, ?string $from, ?string $to, ?string $note = null, ?array $data = null): void
    {
        SsApplicationEvent::query()->create([
            'school_id' => $app->school_id, 'application_id' => $app->id, 'user_id' => $userId,
            'event' => $event, 'from_status' => $from, 'to_status' => $to,
            'note' => $note ? mb_substr($note, 0, 500) : null, 'data' => $data,
        ]);
    }
}
