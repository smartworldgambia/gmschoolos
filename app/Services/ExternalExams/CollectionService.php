<?php

namespace App\Services\ExternalExams;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\CertificateRecord;
use App\Models\ExternalCandidate;
use App\Models\ResultCollection;
use App\Models\School;
use App\Models\User;
use App\Notifications\CollectionReminder;
use App\Services\Records\RecordsSettings;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Physical collection register for printed results (Part 9.3) and certificate tracking (Part 9.4).
 * The 2-3 year certificate figure is an estimate shown to staff; no status or reminder depends on it.
 */
class CollectionService
{
    public const CERT_STATUSES = ['awaiting', 'received', 'available', 'collected', 'outstanding'];

    public function __construct(protected RecordsSettings $settings) {}

    // ------------------------------------------------------------------ results register

    public function markReceived(User $user, ExternalCandidate $candidate, string $date): ResultCollection
    {
        $this->assertDate($date);
        $row = ResultCollection::query()->firstOrNew(['candidate_id' => $candidate->id]);
        if ($row->status === 'collected') {
            throw AcademicRuleException::because('err.col.alreadyCollected');
        }
        $row->fill(['received_on' => $date, 'status' => 'outstanding', 'staff_user_id' => $row->staff_user_id ?? $user->id])->save();
        $this->audit('ext.collection.received', $candidate->internal_reference);

        return $row;
    }

    /** @param array{collected_on:string,collector_name:string,collector_relationship?:?string,collector_id_type?:?string,collector_id_number?:?string,acknowledgement:string} $data */
    public function collect(User $user, ExternalCandidate $candidate, array $data): ResultCollection
    {
        $row = ResultCollection::query()->where('candidate_id', $candidate->id)->first();
        if (! $row || $row->received_on === null) {
            throw AcademicRuleException::because('err.col.notReceived');
        }
        if ($row->status === 'collected') {
            throw AcademicRuleException::because('err.col.alreadyCollected');
        }
        $this->assertDate($data['collected_on']);
        if (\Carbon\Carbon::parse($data['collected_on'])->lt($row->received_on)) {
            throw AcademicRuleException::because('err.col.beforeReceived');
        }
        if (blank($data['collector_name'] ?? null) || blank($data['acknowledgement'] ?? null)) {
            throw AcademicRuleException::because('err.col.collectorRequired');
        }

        $row->fill([
            'collected_on' => $data['collected_on'],
            'collector_name' => $data['collector_name'],
            'collector_relationship' => $data['collector_relationship'] ?? null,
            'collector_id_type' => $data['collector_id_type'] ?? null,
            'collector_id_number' => $data['collector_id_number'] ?? null,
            'acknowledgement' => $data['acknowledgement'],
            'acknowledged_at' => now(),
            'staff_user_id' => $user->id,
            'status' => 'collected',
        ])->save();
        $this->audit('ext.collection.collected', $candidate->internal_reference);

        return $row;
    }

    // ------------------------------------------------------------------ certificates

    /** @param array<string,mixed> $data */
    public function updateCertificate(User $user, ExternalCandidate $candidate, string $status, array $data = []): CertificateRecord
    {
        if (! in_array($status, self::CERT_STATUSES, true)) {
            throw AcademicRuleException::because('err.cert.statusInvalid');
        }
        $row = CertificateRecord::query()->firstOrNew(['candidate_id' => $candidate->id]);
        $from = $row->status ?: 'awaiting';
        if ($from === 'collected' && $status !== 'collected') {
            throw AcademicRuleException::because('err.cert.collectedLocked');
        }
        // Order matters: awaiting -> received -> available -> collected (outstanding is a flag for "available but not collected").
        $rank = ['awaiting' => 0, 'received' => 1, 'available' => 2, 'outstanding' => 2, 'collected' => 3];
        if ($rank[$status] > $rank[$from] + 1 && ! ($from === 'received' && $status === 'outstanding')) {
            throw AcademicRuleException::because('err.cert.skipStep');
        }
        if ($status === 'outstanding' && $rank[$from] < 1) {
            throw AcademicRuleException::because('err.cert.skipStep');
        }

        $today = now()->toDateString();
        $row->status = $status;
        if (in_array($status, ['received', 'available', 'outstanding'], true) && $row->received_on === null) {
            $row->received_on = $data['received_on'] ?? $today;
        }
        if (in_array($status, ['available', 'outstanding'], true) && $row->available_on === null) {
            $row->available_on = $data['available_on'] ?? $today;
        }
        if ($status === 'collected') {
            if (blank($data['collector_name'] ?? null) || blank($data['acknowledgement'] ?? null)) {
                throw AcademicRuleException::because('err.col.collectorRequired');
            }
            $this->assertDate($data['collected_on'] ?? $today);
            $row->fill([
                'collected_on' => $data['collected_on'] ?? $today,
                'collector_name' => $data['collector_name'],
                'collector_relationship' => $data['collector_relationship'] ?? null,
                'collector_id_type' => $data['collector_id_type'] ?? null,
                'collector_id_number' => $data['collector_id_number'] ?? null,
                'acknowledgement' => $data['acknowledgement'],
                'staff_user_id' => $user->id,
            ]);
        }
        if (isset($data['notes'])) {
            $row->notes = $data['notes'];
        }
        $row->save();
        $this->audit('ext.certificate.status', "{$candidate->internal_reference}: {$from} -> {$status}");

        return $row;
    }

    // ------------------------------------------------------------------ reminders

    /**
     * Send one digest per school to staff who manage external exams, covering results/certificates that are
     * still waiting for collection and are due another reminder. Honours each school's reminder settings.
     *
     * @return int number of schools notified
     */
    public function sendReminders(?School $only = null): int
    {
        $sent = 0;
        $schools = $only ? collect([$only]) : School::query()->whereNull('suspended_at')->get();

        foreach ($schools as $school) {
            $sent += app(TenantContext::class)->runForSchool($school, function () use ($school) {
                $cfg = $this->settings->all($school)['reminders'];
                if (! $cfg['enabled']) {
                    return 0;
                }
                $cutoff = now()->subDays($cfg['interval_days']);

                $due = fn ($q, string $sinceCol) => $q
                    ->where('reminders_sent', '<', $cfg['max'])
                    ->where(function ($w) use ($cutoff, $sinceCol) {
                        $w->where(fn ($x) => $x->whereNull('last_reminded_at')->whereDate($sinceCol, '<=', $cutoff->toDateString()))
                            ->orWhere('last_reminded_at', '<=', $cutoff);
                    });

                $results = $due(ResultCollection::query()->where('status', 'outstanding')->whereNotNull('received_on'), 'received_on')->get();
                $certs = $due(CertificateRecord::query()->whereIn('status', ['available', 'outstanding'])->whereNotNull('available_on'), 'available_on')->get();
                if ($results->isEmpty() && $certs->isEmpty()) {
                    return 0;
                }

                $staff = User::query()->withoutGlobalScopes()->where('school_id', $school->id)->permission('external_exams.manage')->get();
                if ($staff->isNotEmpty()) {
                    Notification::send($staff, new CollectionReminder($school->name, $results->count(), $certs->count()));
                }
                foreach ($results as $r) {
                    $r->forceFill(['reminders_sent' => $r->reminders_sent + 1, 'last_reminded_at' => now()])->save();
                }
                foreach ($certs as $c) {
                    $c->forceFill(['reminders_sent' => $c->reminders_sent + 1, 'last_reminded_at' => now()])->save();
                }

                return 1;
            });
        }

        return $sent;
    }

    protected function assertDate(string $date): void
    {
        try {
            $d = \Carbon\Carbon::parse($date);
        } catch (\Throwable) {
            throw AcademicRuleException::because('err.col.dateInvalid');
        }
        if ($d->isFuture() && ! $d->isToday()) {
            throw AcademicRuleException::because('err.col.dateFuture');
        }
    }

    protected function audit(string $event, string $description): void
    {
        ActivityLog::query()->create([
            'school_id' => app(TenantContext::class)->id() ?? auth()->user()?->school_id,
            'user_id' => auth()->id(), 'event' => $event, 'description' => $description,
        ]);
    }
}
