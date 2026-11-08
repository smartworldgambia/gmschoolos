<?php

namespace App\Services\Admissions;

use App\Exceptions\AcademicRuleException;
use App\Models\ActivityLog;
use App\Models\AdmissionApplication;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Services\Students\StudentRegistrar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Admission decisions. Every transition is explicit; enrolment is the only step that creates a Student, and it goes
 * through StudentRegistrar so the permanent number, quota, capacity and duplicate rules apply.
 */
class AdmissionWorkflow
{
    public const TRANSITIONS = [
        'submitted' => ['under_review', 'accepted', 'waitlisted', 'rejected', 'withdrawn'],
        'under_review' => ['accepted', 'waitlisted', 'rejected', 'withdrawn'],
        'waitlisted' => ['under_review', 'accepted', 'rejected', 'withdrawn'],
        'accepted' => ['enrolled', 'waitlisted', 'rejected', 'withdrawn'],
        'rejected' => ['under_review'],
        'enrolled' => [],
        'withdrawn' => [],
    ];

    public function __construct(protected StudentRegistrar $registrar) {}

    /**
     * Record a new application (online form or walk-in desk). Never creates a Student.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,array{type:string,file:UploadedFile}>  $uploads
     */
    public function submit(School $school, array $data, string $source, array $uploads = [], ?string $ip = null): AdmissionApplication
    {
        return DB::transaction(function () use ($school, $data, $source, $uploads, $ip) {
            do {
                $number = 'ADM-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
            } while (AdmissionApplication::query()->where('school_id', $school->id)->where('application_number', $number)->exists());

            $application = AdmissionApplication::query()->create([
                ...$data,
                'school_id' => $school->id,
                'application_number' => $number,
                'status' => 'submitted',
                'source' => $source,
                'submitted_ip' => $ip,
            ]);

            $disk = (string) config('schoolos.students.document_disk', 'local');
            foreach ($uploads as $upload) {
                $file = $upload['file'];
                StudentDocument::query()->create([
                    'school_id' => $school->id,
                    'admission_application_id' => $application->id,
                    'type' => $upload['type'],
                    'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
                    'path' => $file->storeAs("applications/{$school->id}/{$application->id}", Str::random(40).'.'.$file->extension(), $disk),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => auth()->id(),
                ]);
            }

            return $application;
        });
    }

    /** Same child + guardian phone already applied recently (used to stop public double-posting). */
    public function recentDuplicate(School $school, string $first, string $last, ?string $dob, ?string $phone): bool
    {
        return AdmissionApplication::query()->where('school_id', $school->id)
            ->whereRaw('lower(first_name) = ?', [mb_strtolower(trim($first))])
            ->whereRaw('lower(last_name) = ?', [mb_strtolower(trim($last))])
            ->when($dob, fn ($q) => $q->whereDate('date_of_birth', $dob))
            ->when($phone, fn ($q) => $q->where('guardian_phone', $phone))
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotIn('status', ['rejected', 'withdrawn'])
            ->exists();
    }

    public function decide(AdmissionApplication $application, string $status, ?string $reason = null, ?int $classId = null, bool $allowDuplicate = false): AdmissionApplication
    {
        $allowed = self::TRANSITIONS[$application->status] ?? [];
        if (! in_array($status, $allowed, true)) {
            throw AcademicRuleException::because('err.admission.transition');
        }

        if ($status === 'rejected' && blank($reason)) {
            throw AcademicRuleException::because('err.admission.reasonRequired');
        }

        if ($status === 'enrolled') {
            $this->enrol($application, $classId, $allowDuplicate);

            return $application->fresh();
        }

        DB::transaction(function () use ($application, $status, $reason) {
            $update = [
                'status' => $status,
                'decision_reason' => $reason,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'decided_at' => in_array($status, ['accepted', 'rejected', 'waitlisted'], true) ? now() : null,
                'waitlist_position' => null,
            ];

            if ($status === 'waitlisted') {
                $update['waitlist_position'] = $this->nextWaitlistPosition($application);
            }

            $application->update($update);
            $this->renumberWaitlist($application->preferred_class_id);

            ActivityLog::query()->create([
                'school_id' => $application->school_id,
                'user_id' => auth()->id(),
                'event' => 'admission_'.$status,
                'description' => "Application {$application->application_number} -> {$status}",
            ]);
        });

        return $application->fresh();
    }

    public function enrol(AdmissionApplication $application, ?int $classId = null, bool $allowDuplicate = false): Student
    {
        if ($application->student_id) {
            throw AcademicRuleException::because('err.admission.alreadyEnrolled');
        }
        if ($application->status !== 'accepted') {
            throw AcademicRuleException::because('err.admission.notAccepted');
        }

        $school = School::query()->findOrFail($application->school_id);

        return DB::transaction(function () use ($application, $classId, $school, $allowDuplicate) {
            $student = $this->registrar->register($school, [
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'gender' => $application->gender,
                'date_of_birth' => $application->date_of_birth?->toDateString(),
                'place_of_birth' => $application->place_of_birth,
                'nationality' => $application->nationality,
                'religion' => $application->religion,
                'school_class_id' => $classId ?? $application->preferred_class_id,
                'previous_school' => $application->previous_school,
                'address' => $application->address,
                'emergency_contact_name' => $application->emergency_contact_name,
                'emergency_contact_phone' => $application->emergency_contact_phone,
            ], $application->guardian_name ? [
                'name' => $application->guardian_name,
                'relationship' => $application->guardian_relationship,
                'phone' => $application->guardian_phone,
                'email' => $application->guardian_email,
            ] : null, $allowDuplicate);

            // Documents uploaded with the application now belong to the student's file.
            StudentDocument::query()
                ->where('admission_application_id', $application->id)
                ->update(['student_id' => $student->id]);

            $application->update([
                'status' => 'enrolled',
                'student_id' => $student->id,
                'waitlist_position' => null,
                'reviewed_at' => $application->reviewed_at ?? now(),
                'reviewed_by' => $application->reviewed_by ?? auth()->id(),
            ]);

            ActivityLog::query()->create([
                'school_id' => $application->school_id,
                'user_id' => auth()->id(),
                'event' => 'admission_enrolled',
                'description' => "Enrolled {$student->student_number} from application {$application->application_number}",
            ]);

            return $student;
        });
    }

    protected function nextWaitlistPosition(AdmissionApplication $application): int
    {
        return 1 + (int) AdmissionApplication::query()
            ->where('status', 'waitlisted')
            ->where('preferred_class_id', $application->preferred_class_id)
            ->where('id', '!=', $application->id)
            ->max('waitlist_position');
    }

    /** Keep waitlist positions 1..n with no gaps after somebody leaves the list. */
    protected function renumberWaitlist(?int $classId): void
    {
        $position = 1;
        AdmissionApplication::query()
            ->where('status', 'waitlisted')->where('preferred_class_id', $classId)
            ->orderBy('waitlist_position')->orderBy('id')->get()
            ->each(function (AdmissionApplication $a) use (&$position) {
                if ((int) $a->waitlist_position !== $position) {
                    $a->update(['waitlist_position' => $position]);
                }
                $position++;
            });
    }
}
