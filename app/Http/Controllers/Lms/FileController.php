<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Lesson;
use App\Models\LmsFile;
use App\Services\Lms\CourseService;
use App\Services\Lms\LmsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

/** Authorised access to LMS uploads. Files live on the private disk under random names and are never public. */
class FileController extends Controller
{
    use UsesLms;

    public function __construct(protected CourseService $service) {}

    public function show(LmsFile $file)
    {
        $course = $file->course;
        $role = $this->role($course);
        abort_unless($file->path, 404);

        if ($role === LmsAccess::LEARNER) {
            $student = $this->me();
            $allowed = match ($file->owner_type) {
                'lesson' => ($l = Lesson::query()->find($file->owner_id)) && (int) $l->course_id === (int) $course->id && $this->lms()->lessonVisible($l, $student),
                'assignment' => ($a = Assignment::query()->find($file->owner_id)) && (int) $a->course_id === (int) $course->id && $a->isLive(),
                'submission' => ($s = AssignmentSubmission::query()->find($file->owner_id)) && (int) $s->student_id === (int) $student->id,
                default => false,
            };
            abort_unless($allowed, 404);
        }

        $disk = CourseService::disk();
        abort_unless(Storage::disk($disk)->exists($file->path), 404);
        $inline = preg_match('#^(video|audio|image)/|application/pdf#', (string) $file->mime) === 1;
        $name = str_replace(['"', "\r", "\n"], '', $file->original_name ?: 'file');

        return Storage::disk($disk)->response($file->path, $name, [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(LmsFile $file): RedirectResponse
    {
        $this->author($file->course);
        abort_unless(in_array($file->owner_type, ['lesson', 'assignment'], true), 403); // learners' submissions are never deleted by staff here
        $this->service->deleteFile($file);

        return back()->with('success', 'ok.lms.materialRemoved');
    }
}
