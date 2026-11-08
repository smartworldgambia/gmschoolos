<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Student;
use App\Services\Lms\CommunityService;
use App\Services\Lms\GradebookService;
use App\Services\Lms\LmsAccess;
use App\Services\Lms\ProgressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Course gradebook (teachers see everyone, learners only themselves) and course analytics. */
class GradebookController extends Controller
{
    use UsesLms;

    public function __construct(protected GradebookService $gradebook, protected ProgressService $progress, protected CommunityService $community) {}

    public function show(Course $course): Response
    {
        $role = $this->role($course);
        $author = LmsAccess::authors($role);
        $student = $author ? null : $this->me();
        $book = $this->gradebook->course($course, ! $author, $student);

        return Inertia::render('Lms/Gradebook', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'columns' => $book['columns'], 'rows' => $book['rows'],
            'pending' => $author ? $this->gradebook->pending($course) : null,
            'exportUrl' => $author ? route('lms.courses.gradebook.export', $course) : null,
            'feedbackUrl' => $author ? url("/lms/courses/{$course->id}/gradebook/feedback") : null,
        ]);
    }

    public function feedback(Request $request, Course $course, int $student): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate(['body' => ['nullable', 'string', 'max:3000']]);
        $record = Student::query()->findOrFail($student);
        abort_unless($this->gradebook->activeStudents($course)->contains($record->id), 404);
        $this->community->saveFeedback($request->user(), $course, $record, $v['body'] ?? null);

        return back()->with('success', 'ok.lms.feedbackSaved');
    }

    /** CSV for the teacher. Cells that a spreadsheet could execute as formulas are neutralised. */
    public function export(Course $course): StreamedResponse
    {
        $this->author($course);
        $book = $this->gradebook->course($course, false);
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;

        return response()->streamDownload(function () use ($book, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['student_number', 'name', 'class'], array_map(fn ($c) => $safe($c['title']), $book['columns']), ['course_percent', 'lesson_progress_percent']));
            foreach ($book['rows'] as $r) {
                $cells = array_map(function ($c) use ($r) {
                    $cell = $r['cells'][$c['key']] ?? null;

                    return $cell && $cell['score'] !== null ? $cell['score'] : '';
                }, $book['columns']);
                fputcsv($out, array_merge([$safe($r['student']['number']), $safe($r['student']['name']), $safe($r['student']['class'])], $cells, [$r['percent'], $r['progress']]));
            }
            fclose($out);
        }, 'gradebook-'.$course->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function progress(Course $course): Response
    {
        $role = $this->author($course);
        $data = $this->progress->course($course);

        return Inertia::render('Lms/Progress', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'summary' => $data['summary'], 'rows' => $data['rows'],
        ]);
    }
}
