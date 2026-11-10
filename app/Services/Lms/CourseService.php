<?php

namespace App\Services\Lms;

use App\Exceptions\AcademicRuleException;
use App\Models\Assignment;
use App\Models\ClassSubject;
use App\Models\Course;
use App\Models\CourseAudience;
use App\Models\CourseCategory;
use App\Models\CourseEnrolment;
use App\Models\CourseSection;
use App\Models\CourseTeacher;
use App\Models\Department;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LmsFile;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\Academics\TeacherScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Course authoring: courses, audience and enrolment, sections, lessons, materials. */
class CourseService
{
    public function __construct(protected TeacherScope $scope, protected LmsAccess $access) {}

    public static function disk(): string
    {
        return config('schoolos.lms.disk', 'local');
    }

    // ------------------------------------------------------------------ courses

    /**
     * @param  array<string,mixed>  $data  validated: title, code, description, subject_id, category_id|category_name, objectives[], status,
     *                                     teacher_id, co_teacher_ids[], audience_class_ids[]
     */
    public function saveCourse(User $actor, ?Course $course, array $data): Course
    {
        $manager = $actor->can('lms.manage');
        if (! $manager && ! $actor->can('lms.teach')) {
            throw AcademicRuleException::because('err.lms.notAllowed');
        }
        if ($course && ! $manager && ! $this->access->teachesCourse($actor, $course)) {
            throw AcademicRuleException::because('err.lms.notAllowed');
        }

        $subjectId = $data['subject_id'] ?? null;
        if (! $manager) {
            // A teacher may only create courses for a subject they are allocated to (fail-closed).
            if (! $subjectId || ! $this->teachesSubject($actor, (int) $subjectId)) {
                throw AcademicRuleException::because('err.lms.notAllocated');
            }
        }

        $classIds = collect($data['audience_class_ids'] ?? [])->map(fn ($i) => (int) $i)->unique()->values();
        foreach ($classIds as $classId) {
            if (! SchoolClass::query()->whereKey($classId)->exists()) {
                throw AcademicRuleException::because('err.lms.classInvalid');
            }
            if (! $manager && ! $this->scope->canAccessClass($actor, $classId, 'lms.manage')) {
                throw AcademicRuleException::because('err.lms.classNotAllowed');
            }
        }

        return DB::transaction(function () use ($actor, $course, $data, $manager, $subjectId, $classIds) {
            $attrs = [
                'title' => $data['title'], 'code' => $data['code'] ?? null, 'description' => $data['description'] ?? null,
                'subject_id' => $subjectId, 'status' => $data['status'] ?? 'draft',
                'objectives' => $this->cleanList($data['objectives'] ?? []),
                'category_id' => $this->resolveCategory($data),
            ];

            if (! $course && $subjectId) {
                $course = Course::query()->where('subject_id', $subjectId)->orderBy('id')->first();
            }

            if (! $course) {
                $attrs['created_by'] = $actor->id;
                $attrs['teacher_id'] = $manager ? $this->validTeacher($data['teacher_id'] ?? null) : $actor->id;
                $course = Course::query()->create($attrs);
            } else {
                if ($manager && array_key_exists('teacher_id', $data)) {
                    $attrs['teacher_id'] = $this->validTeacher($data['teacher_id']);
                }
                $course->update($attrs);
            }

            if ($manager && array_key_exists('co_teacher_ids', $data)) {
                $this->syncCoTeachers($course, (array) $data['co_teacher_ids']);
            }

            if (array_key_exists('audience_class_ids', $data)) {
                $this->setAudience($course, $classIds->all());
            }

            return $course->fresh();
        });
    }

    /**
     * One LMS course per academic subject: the same offering teachers are allocated to in Academics.
     * Basic schools call that a subject; tertiary schools call it a course.
     */
    public function syncSubjectCourse(Subject $subject): Course
    {
        $allocations = ClassSubject::query()->withoutGlobalScopes()
            ->where('school_id', $subject->school_id)->where('subject_id', $subject->id)->get();
        $teacherIds = $allocations->pluck('teacher_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $classIds = $allocations->pluck('school_class_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $course = Course::query()->withoutGlobalScopes()
            ->where('school_id', $subject->school_id)->where('subject_id', $subject->id)->orderBy('id')->first();

        $primary = $course && $course->teacher_id && $teacherIds->contains((int) $course->teacher_id)
            ? (int) $course->teacher_id
            : ($teacherIds->first() ?: null);

        $attrs = [
            'title' => $subject->name,
            'code' => $subject->code,
            'subject_id' => $subject->id,
            'teacher_id' => $primary,
        ];

        if (! $course) {
            $course = Course::query()->create($attrs + [
                'school_id' => $subject->school_id,
                'created_by' => auth()->id(),
                'status' => $teacherIds->isNotEmpty() ? 'published' : 'draft',
            ]);
        } else {
            if ($course->status !== 'archived' && $teacherIds->isNotEmpty()) {
                $attrs['status'] = 'published';
            }
            $course->forceFill($attrs)->save();
        }

        $keep = $teacherIds->reject(fn ($id) => $id === (int) $course->teacher_id)->values()->all();
        CourseTeacher::query()->where('course_id', $course->id)->whereNotIn('user_id', $keep ?: [0])->delete();
        foreach ($keep as $userId) {
            CourseTeacher::query()->firstOrCreate(
                ['course_id' => $course->id, 'user_id' => $userId],
                ['school_id' => $course->school_id]
            );
        }

        $this->setAudience($course, $classIds);

        return $course->fresh();
    }

    /** Ensure allocated class-subjects have matching LMS courses (one teacher, or the whole school). */
    public function syncAllocatedCourses(?User $teacher = null): void
    {
        $query = ClassSubject::query()->whereNotNull('teacher_id');
        if ($teacher) {
            $query->where('teacher_id', $teacher->id);
        }
        $subjectIds = $query->pluck('subject_id')->unique()->filter()->all();
        if ($subjectIds === []) {
            return;
        }

        foreach (Subject::query()->whereIn('id', $subjectIds)->get() as $subject) {
            $this->syncSubjectCourse($subject);
        }
    }

    public function teachesSubject(User $user, int $subjectId): bool
    {
        if (ClassSubject::query()->where('teacher_id', $user->id)->where('subject_id', $subjectId)->exists()) {
            return true;
        }
        $departmentIds = Department::query()->where('head_user_id', $user->id)->pluck('id');

        return $departmentIds->isNotEmpty() && Subject::query()->whereKey($subjectId)->whereIn('department_id', $departmentIds)->exists();
    }

    protected function validTeacher(mixed $id): ?int
    {
        if (! $id) {
            return null;
        }
        $user = User::query()->where('school_id', app(\App\Support\TenantContext::class)->id())->find($id);
        if (! $user || ! $user->canAny(['lms.teach', 'lms.manage'])) {
            throw AcademicRuleException::because('err.lms.teacherInvalid');
        }

        return (int) $user->id;
    }

    protected function syncCoTeachers(Course $course, array $ids): void
    {
        $keep = [];
        foreach (collect($ids)->map(fn ($i) => (int) $i)->unique() as $id) {
            if ($id === (int) $course->teacher_id) {
                continue;
            }
            $keep[] = $this->validTeacher($id);
        }
        CourseTeacher::query()->where('course_id', $course->id)->whereNotIn('user_id', $keep ?: [0])->delete();
        foreach ($keep as $userId) {
            CourseTeacher::query()->firstOrCreate(['course_id' => $course->id, 'user_id' => $userId], ['school_id' => $course->school_id]);
        }
    }

    protected function resolveCategory(array $data): ?int
    {
        $name = trim((string) ($data['category_name'] ?? ''));
        if ($name !== '') {
            return CourseCategory::query()->firstOrCreate(['name' => mb_substr($name, 0, 120)], ['school_id' => app(\App\Support\TenantContext::class)->id()])->id;
        }
        if (! empty($data['category_id'])) {
            return CourseCategory::query()->whereKey($data['category_id'])->value('id');
        }

        return null;
    }

    /** @return array<int,string> */
    protected function cleanList(array $items): array
    {
        return collect($items)->map(fn ($s) => mb_substr(trim((string) $s), 0, 300))->filter()->values()->take(30)->all();
    }

    // ------------------------------------------------------------------ audience and enrolment

    /** @param array<int,int> $classIds */
    public function setAudience(Course $course, array $classIds): void
    {
        CourseAudience::query()->where('course_id', $course->id)->whereNotIn('school_class_id', $classIds ?: [0])->delete();
        foreach ($classIds as $classId) {
            CourseAudience::query()->firstOrCreate(['course_id' => $course->id, 'school_class_id' => $classId], ['school_id' => $course->school_id]);
        }
        $this->syncAudience($course);
    }

    /** Enrol every active student of the audience classes; withdraw class-sourced enrolments that no longer belong. */
    public function syncAudience(Course $course): array
    {
        $classIds = CourseAudience::query()->where('course_id', $course->id)->pluck('school_class_id');
        $students = Student::query()->whereIn('school_class_id', $classIds)->where('status', 'active')->pluck('id');
        $added = 0;
        foreach ($students as $studentId) {
            $row = CourseEnrolment::query()->where('course_id', $course->id)->where('student_id', $studentId)->first();
            if (! $row) {
                CourseEnrolment::query()->create(['school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $studentId,
                    'source' => 'class', 'status' => 'active', 'enrolled_at' => now()]);
                $added++;
            } elseif ($row->status === 'withdrawn' && $row->source === 'class') {
                $row->update(['status' => 'active', 'withdrawn_at' => null]);
                $added++;
            }
        }
        $removed = CourseEnrolment::query()->where('course_id', $course->id)->where('source', 'class')->where('status', 'active')
            ->whereNotIn('student_id', $students->all() ?: [0])->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);

        return ['added' => $added, 'removed' => $removed];
    }

    /** @param array<int,int> $studentIds */
    public function enrol(User $actor, Course $course, array $studentIds): int
    {
        $query = Student::query()->whereIn('id', $studentIds)->where('status', 'active');
        if (! $actor->can('lms.manage')) {
            $query = $this->scope->limitStudents($query, $actor); // a teacher can only enrol learners of classes they may access
        }
        $students = $query->get();
        if ($students->isEmpty()) {
            throw AcademicRuleException::because('err.lms.noStudents');
        }
        $count = 0;
        foreach ($students as $student) {
            $row = CourseEnrolment::query()->where('course_id', $course->id)->where('student_id', $student->id)->first();
            if ($row && $row->status === 'active') {
                continue;
            }
            if ($row) {
                $row->update(['status' => 'active', 'withdrawn_at' => null, 'source' => 'manual']);
            } else {
                CourseEnrolment::query()->create(['school_id' => $course->school_id, 'course_id' => $course->id, 'student_id' => $student->id,
                    'source' => 'manual', 'status' => 'active', 'enrolled_at' => now()]);
            }
            $count++;
        }

        return $count;
    }

    public function withdraw(CourseEnrolment $enrolment): void
    {
        $enrolment->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
    }

    // ------------------------------------------------------------------ sections

    public function saveSection(Course $course, ?CourseSection $section, array $data): CourseSection
    {
        $attrs = ['title' => $data['title'], 'description' => $data['description'] ?? null];
        if ($section) {
            $section->update($attrs + (isset($data['sort_order']) ? ['sort_order' => (int) $data['sort_order']] : []));

            return $section;
        }

        return CourseSection::query()->create($attrs + [
            'school_id' => $course->school_id, 'course_id' => $course->id,
            'sort_order' => $data['sort_order'] ?? ((int) CourseSection::query()->where('course_id', $course->id)->max('sort_order') + 1),
        ]);
    }

    // ------------------------------------------------------------------ lessons

    public function saveLesson(User $actor, Course $course, ?Lesson $lesson, array $data): Lesson
    {
        $sectionId = $data['course_section_id'] ?? null;
        if ($sectionId && ! CourseSection::query()->where('course_id', $course->id)->whereKey($sectionId)->exists()) {
            throw AcademicRuleException::because('err.lms.sectionInvalid');
        }

        $classIds = collect($data['audience_class_ids'] ?? [])->map(fn ($i) => (int) $i)->unique()->values();
        if ($classIds->isNotEmpty()) {
            $allowed = SchoolClass::query()->whereIn('id', $classIds)->pluck('id');
            if ($allowed->count() !== $classIds->count()) {
                throw AcademicRuleException::because('err.lms.classInvalid');
            }
            // Restricting a lesson to classes the course does not even reach would hide it from everyone.
            $reach = CourseAudience::query()->where('course_id', $course->id)->pluck('school_class_id');
            if ($reach->isNotEmpty() && $classIds->diff($reach)->isNotEmpty()) {
                throw AcademicRuleException::because('err.lms.lessonClassOutside');
            }
        }

        $attrs = [
            'title' => $data['title'], 'content_type' => $data['content_type'] ?? 'text', 'content' => $data['content'] ?? null,
            'course_section_id' => $sectionId, 'duration_minutes' => $data['duration_minutes'] ?? null,
            'status' => $data['status'] ?? 'draft', 'publish_at' => $data['publish_at'] ?? null,
            'objectives' => $this->cleanList($data['objectives'] ?? []), 'audience_class_ids' => $classIds->all() ?: null,
        ];

        if ($lesson) {
            $lesson->update($attrs + (isset($data['sort_order']) ? ['sort_order' => (int) $data['sort_order']] : []));

            return $lesson;
        }

        return Lesson::query()->create($attrs + [
            'school_id' => $course->school_id, 'course_id' => $course->id, 'created_by' => $actor->id,
            'sort_order' => $data['sort_order'] ?? ((int) Lesson::query()->where('course_id', $course->id)->max('sort_order') + 1),
        ]);
    }

    public function completeLesson(Course $course, Lesson $lesson, Student $student): void
    {
        LessonCompletion::query()->updateOrCreate(
            ['lesson_id' => $lesson->id, 'student_id' => $student->id],
            ['school_id' => $course->school_id, 'course_id' => $course->id, 'completed_at' => now()]
        );
        $this->access->touch($course, $student);
    }

    // ------------------------------------------------------------------ files / materials

    public function storeUpload(UploadedFile $file, Course $course, string $ownerType, int $ownerId, User $by, array $attrs = []): LmsFile
    {
        $path = $file->store('lms/'.$course->school_id.'/'.$course->id, self::disk());

        return LmsFile::query()->create($attrs + [
            'school_id' => $course->school_id, 'course_id' => $course->id, 'owner_type' => $ownerType, 'owner_id' => $ownerId,
            'type' => $attrs['type'] ?? 'file', 'path' => $path, 'original_name' => mb_substr($file->getClientOriginalName(), 0, 190),
            'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'uploaded_by' => $by->id,
        ]);
    }

    public function addLink(Course $course, string $ownerType, int $ownerId, User $by, string $type, string $url, ?string $title): LmsFile
    {
        return LmsFile::query()->create([
            'school_id' => $course->school_id, 'course_id' => $course->id, 'owner_type' => $ownerType, 'owner_id' => $ownerId,
            'type' => $type, 'url' => $url, 'title' => $title ? mb_substr($title, 0, 190) : null, 'uploaded_by' => $by->id,
        ]);
    }

    public function deleteFile(LmsFile $file): void
    {
        if ($file->path) {
            Storage::disk(self::disk())->delete($file->path);
        }
        $file->delete();
    }

    /** Remove stored uploads that belong to an owner (lesson / assignment / submission). */
    public function purgeOwnerFiles(string $ownerType, int $ownerId): void
    {
        foreach (LmsFile::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->get() as $f) {
            $this->deleteFile($f);
        }
    }

    public function deleteLesson(Lesson $lesson): void
    {
        $this->purgeOwnerFiles('lesson', $lesson->id);
        $lesson->delete();
    }

    public function deleteAssignment(Assignment $assignment): void
    {
        foreach ($assignment->submissions()->pluck('id') as $sid) {
            $this->purgeOwnerFiles('submission', $sid);
        }
        $this->purgeOwnerFiles('assignment', $assignment->id);
        $assignment->delete();
    }
}
