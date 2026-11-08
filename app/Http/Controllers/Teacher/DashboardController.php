<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassSubject;
use App\Models\Course;
use App\Models\CourseTeacher;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TimetableSlot;
use App\Services\Academics\TeacherScope;
use App\Services\Communications\AnnouncementService;
use App\Services\Messaging\MessagingService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Teacher portal home. Everything shown is limited to what the teacher is assigned to (TeacherScope): their classes,
 * their subject allocations, their timetable and their own courses. There is no "show everything" fallback: a teacher
 * with no allocation sees empty lists, never the whole school.
 */
class DashboardController extends Controller
{
    use SharesSchoolShell;

    public function __invoke(TeacherScope $scope, AnnouncementService $announcements, MessagingService $messages): Response
    {
        $user = auth()->user();
        $classIds = $scope->classIds($user);

        $classes = SchoolClass::query()->whereIn('id', $classIds)->withCount('students')->orderBy('name')->get();
        $studentCount = Student::query()->whereIn('school_class_id', $classIds)->where('status', 'active')->count();

        $courseIds = Course::query()->where('teacher_id', $user->id)->pluck('id')
            ->merge(CourseTeacher::query()->where('user_id', $user->id)->pluck('course_id'))->unique()->values();

        $assignmentIds = Assignment::query()->whereIn('course_id', $courseIds)->pluck('id');
        $needsMarking = AssignmentSubmission::query()->whereIn('assignment_id', $assignmentIds)->where('status', 'submitted')->count();

        $timetable = TimetableSlot::query()
            ->with(['subject', 'schoolClass'])
            ->where('teacher_id', $user->id)
            ->where('day_of_week', now()->dayOfWeekIso)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (TimetableSlot $slot) => [
                'subject' => $slot->subject?->name,
                'class' => $slot->schoolClass?->name,
                'starts_at' => substr((string) $slot->starts_at, 0, 5),
                'ends_at' => substr((string) $slot->ends_at, 0, 5),
                'room' => $slot->room,
            ]);

        $subjects = ClassSubject::query()->with(['schoolClass:id,name', 'subject:id,name'])->where('teacher_id', $user->id)->get()
            ->map(fn (ClassSubject $c) => ['class' => $c->schoolClass?->name, 'subject' => $c->subject?->name])->values();

        return Inertia::render('Teacher/Dashboard', array_merge($this->shellProps(), [
            'stats' => [
                'classes' => $classes->count(),
                'students' => $studentCount,
                'courses' => $courseIds->count(),
                'pending_marking' => $needsMarking,
                'unread_messages' => $messages->unreadCount($user),
            ],
            'timetable' => $timetable,
            'classes' => $classes->map(fn (SchoolClass $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'students_count' => $c->students_count,
            ]),
            'subjects' => $subjects,
            'pendingItems' => Assignment::query()->whereIn('course_id', $courseIds)->where('due_at', '>=', now()->subDay())
                ->orderBy('due_at')->limit(5)->get(['id', 'title', 'due_at'])
                ->map(fn (Assignment $a) => ['title' => $a->title, 'due_at' => $a->due_at?->toDateString()]),
            'announcements' => $announcements->feed($user, 3)->map(fn ($a) => [
                'id' => $a->id, 'title' => $a->title, 'body' => mb_substr($a->body, 0, 160), 'unread' => $a->my_read_at === null,
            ])->values(),
        ]));
    }
}
