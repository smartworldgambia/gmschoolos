<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseAudience;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\LmsFile;
use App\Models\SchoolClass;
use App\Services\Lms\CourseService;
use App\Services\Lms\LmsAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LessonController extends Controller
{
    use UsesLms;

    public const MATERIAL_MIMES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,txt,csv,jpg,jpeg,png,gif,mp4,webm,mp3,m4a,wav';

    public const CONTENT_TYPES = ['text', 'video', 'document', 'link'];

    public function __construct(protected CourseService $service) {}

    public function create(Course $course): Response
    {
        $role = $this->author($course);

        return $this->form($course, null, $role);
    }

    public function edit(Lesson $lesson): Response
    {
        $role = $this->author($lesson->course);

        return $this->form($lesson->course, $lesson, $role);
    }

    protected function form(Course $course, ?Lesson $lesson, string $role): Response
    {
        $reach = CourseAudience::query()->where('course_id', $course->id)->pluck('school_class_id');

        return Inertia::render('Lms/LessonForm', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'lesson' => $lesson ? [
                'id' => $lesson->id, 'title' => $lesson->title, 'content_type' => $lesson->content_type, 'content' => $lesson->content,
                'course_section_id' => $lesson->course_section_id, 'duration_minutes' => $lesson->duration_minutes, 'status' => $lesson->status,
                'publish_at' => $lesson->publish_at?->format('Y-m-d\TH:i'), 'objectives' => $lesson->objectives ?? [],
                'audience_class_ids' => $lesson->audience_class_ids ?? [], 'sort_order' => $lesson->sort_order,
                'materials' => $this->materials($lesson, true),
            ] : null,
            'sections' => CourseSection::query()->where('course_id', $course->id)->orderBy('sort_order')->get(['id', 'title']),
            'classes' => SchoolClass::query()->when($reach->isNotEmpty(), fn ($q) => $q->whereIn('id', $reach))->orderBy('name')->get(['id', 'name']),
            'contentTypes' => self::CONTENT_TYPES,
        ]);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:190'], 'content_type' => ['required', 'in:'.implode(',', self::CONTENT_TYPES)],
            'content' => ['nullable', 'string', 'max:50000'], 'course_section_id' => ['nullable', 'integer'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1000'], 'status' => ['required', 'in:draft,published'],
            'publish_at' => ['nullable', 'date'], 'objectives' => ['nullable', 'array', 'max:30'], 'objectives.*' => ['nullable', 'string', 'max:300'],
            'audience_class_ids' => ['nullable', 'array'], 'audience_class_ids.*' => ['integer', $this->inSchool('school_classes')],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function store(Request $request, Course $course): RedirectResponse
    {
        $this->author($course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $course, $v) {
            $lesson = $this->service->saveLesson($request->user(), $course, null, $v);

            return redirect()->route('lms.lessons.edit', $lesson)->with('success', 'ok.lms.lessonSaved');
        });
    }

    public function update(Request $request, Lesson $lesson): RedirectResponse
    {
        $this->author($lesson->course);
        $v = $request->validate($this->rules());

        return $this->attempt(function () use ($request, $lesson, $v) {
            $this->service->saveLesson($request->user(), $lesson->course, $lesson, $v);

            return back()->with('success', 'ok.lms.lessonSaved');
        });
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $course = $lesson->course;
        $this->author($course);
        $this->service->deleteLesson($lesson);

        return redirect()->route('lms.courses.show', $course)->with('success', 'ok.lms.lessonRemoved');
    }

    // ------------------------------------------------------------------ learner / preview

    public function show(Lesson $lesson): Response
    {
        $course = $lesson->course;
        $role = $this->role($course);
        $author = LmsAccess::authors($role);
        $student = $author ? null : $this->me();
        abort_unless($author || $this->lms()->lessonVisible($lesson, $student), 404);

        $siblings = $course->lessons()->get();
        if (! $author) {
            $siblings = $siblings->filter(fn ($l) => $this->lms()->lessonVisible($l, $student))->values();
        }
        $index = $siblings->search(fn ($l) => (int) $l->id === (int) $lesson->id);
        $previous = $index > 0 ? $siblings[$index - 1] : null;
        $next = ($index !== false && $index < $siblings->count() - 1) ? $siblings[$index + 1] : null;

        if ($student) {
            $this->lms()->touch($course, $student);
        }

        return Inertia::render('Lms/LessonShow', $this->shellProps() + $this->courseHeader($course->loadMissing('subject'), $role) + [
            'lesson' => [
                'id' => $lesson->id, 'title' => $lesson->title, 'content_type' => $lesson->content_type, 'content' => $lesson->content,
                'duration_minutes' => $lesson->duration_minutes, 'objectives' => $lesson->objectives ?? [], 'status' => $lesson->status,
                'publish_at' => $lesson->publish_at?->toDateTimeString(), 'section' => $lesson->section?->title,
                'external_url' => $lesson->isExternalLaunch() ? route('lms.external.launch', $lesson) : null,
            ],
            'materials' => $this->materials($lesson, $author),
            'completed' => $student ? LessonCompletion::query()->where('lesson_id', $lesson->id)->where('student_id', $student->id)->exists() : false,
            'canComplete' => (bool) $student,
            'previousUrl' => $previous ? route('lms.lessons.show', $previous) : null,
            'nextUrl' => $next ? route('lms.lessons.show', $next) : null,
        ]);
    }

    public function complete(Lesson $lesson): RedirectResponse
    {
        $course = $lesson->course;
        $this->role($course, [LmsAccess::LEARNER]);
        $student = $this->me();
        abort_unless($student && $this->lms()->lessonVisible($lesson, $student), 404);
        $this->service->completeLesson($course, $lesson, $student);

        return back()->with('success', 'ok.lms.lessonCompleted');
    }

    // ------------------------------------------------------------------ materials

    /** @return array<int,array<string,mixed>> */
    protected function materials(Lesson $lesson, bool $author): array
    {
        return LmsFile::query()->where('owner_type', 'lesson')->where('owner_id', $lesson->id)->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (LmsFile $f) => [
                'id' => $f->id, 'type' => $f->type, 'title' => $f->title ?: $f->original_name, 'url' => $f->url, 'name' => $f->original_name,
                'size' => $f->size, 'mime' => $f->mime, 'file_url' => $f->path ? route('lms.files.show', $f) : null,
                'inline' => $f->path && preg_match('#^(video|audio|image)/|application/pdf#', (string) $f->mime) === 1,
                'delete_url' => $author ? route('lms.files.destroy', $f) : null,
            ])->values()->all();
    }

    public function storeMaterial(Request $request, Lesson $lesson): RedirectResponse
    {
        $course = $lesson->course;
        $this->author($course);
        $v = $request->validate([
            'type' => ['required', 'in:file,document,video,link'], 'title' => ['nullable', 'string', 'max:190'],
            'url' => ['nullable', 'url', 'max:500', 'starts_with:https://,http://'],
            'file' => ['nullable', 'file', 'mimes:'.self::MATERIAL_MIMES, 'max:'.config('schoolos.lms.file_max_kb')],
        ]);
        if (! $request->hasFile('file') && empty($v['url'])) {
            return back()->with('error', 'err.lms.materialEmpty');
        }
        if ($request->hasFile('file')) {
            $this->service->storeUpload($request->file('file'), $course, 'lesson', $lesson->id, $request->user(), ['type' => $v['type'] === 'link' ? 'file' : $v['type'], 'title' => $v['title'] ?? null]);
        } else {
            $this->service->addLink($course, 'lesson', $lesson->id, $request->user(), $v['type'] === 'file' ? 'link' : $v['type'], $v['url'], $v['title'] ?? null);
        }

        return back()->with('success', 'ok.lms.materialAdded');
    }
}
