<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Services\Lms\LmsAccess;
use App\Models\Lesson;
use App\Models\LtiTool;
use App\Models\ScormPackage;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Feasible SCORM/LTI hooks: register tools/packages and deep-link lessons.
 * Full SCORM runtime / LTI Advantage launch signing is out of scope for Phase 6.
 */
class ExternalContentController extends Controller
{
    use UsesLms;

    public function index(): Response
    {
        abort_unless(auth()->user()?->canAny(['lms.teach', 'lms.manage']), 403);

        $schoolId = app(TenantContext::class)->id() ?? auth()->user()->school_id;

        return Inertia::render('Lms/ExternalContent', array_merge($this->lmsPage(), [
            'tools' => LtiTool::query()
                ->where(fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
                ->orderBy('name')
                ->get(['id', 'name', 'launch_url', 'client_id', 'is_active']),
            'packages' => ScormPackage::query()->latest()->get(['id', 'title', 'version', 'entry_point', 'status']),
            'linkedLessons' => Lesson::query()
                ->where(function ($q) {
                    $q->whereIn('content_type', ['lti', 'scorm'])
                        ->orWhereNotNull('lti_tool_id')
                        ->orWhereNotNull('scorm_package_id')
                        ->orWhereNotNull('external_launch_url');
                })
                ->with(['course:id,title', 'ltiTool:id,launch_url', 'scormPackage:id,entry_point'])
                ->latest()
                ->limit(30)
                ->get()
                ->map(fn (Lesson $l) => [
                    'id' => $l->id,
                    'title' => $l->title,
                    'course' => $l->course?->title,
                    'content_type' => $l->content_type,
                    'launch_url' => $l->external_launch_url
                        ?? $l->ltiTool?->launch_url
                        ?? $l->scormPackage?->entry_point,
                ]),
        ]));
    }

    public function storeTool(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('lms.manage'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'launch_url' => ['required', 'url', 'max:500', 'starts_with:https://,http://'],
            'client_id' => ['nullable', 'string', 'max:100'],
            'deployment_id' => ['nullable', 'string', 'max:100'],
        ]);

        LtiTool::query()->create([
            ...$validated,
            'school_id' => app(TenantContext::class)->id() ?? auth()->user()->school_id,
            'is_active' => true,
        ]);

        return back()->with('success', 'ok.lms.extToolSaved');
    }

    public function storePackage(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('lms.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'version' => ['required', 'in:1.2,2004'],
            'entry_point' => ['nullable', 'url', 'max:500', 'starts_with:https://,http://'],
        ]);

        ScormPackage::query()->create([
            ...$validated,
            'school_id' => app(TenantContext::class)->id() ?? auth()->user()->school_id,
            'status' => 'ready',
            'manifest' => ['note' => 'Metadata only — upload/runtime player deferred'],
        ]);

        return back()->with('success', 'ok.lms.extPackageSaved');
    }

    public function launch(Lesson $lesson): Response|RedirectResponse
    {
        $lesson->load(['ltiTool', 'scormPackage', 'course']);

        // Same visibility rules as the lesson itself: enrolled learners (live, class-allowed lessons) and the course's teachers/managers only.
        $access = app(LmsAccess::class);
        $role = $access->authorize(auth()->user(), $lesson->course);
        if (! LmsAccess::authors($role)) {
            abort_unless($access->lessonVisible($lesson, $access->student(auth()->user())), 404);
        }

        $url = $lesson->external_launch_url
            ?? $lesson->ltiTool?->launch_url
            ?? $lesson->scormPackage?->entry_point;

        // Only plain web links are ever put into an anchor; anything else (javascript:, data:, file:) is refused.
        if (! $url || ! preg_match('#^https?://#i', $url)) {
            return back()->with('error', 'err.lms.extNoUrl');
        }

        return Inertia::render('Lms/ExternalLaunch', array_merge($this->lmsPage(), [
            'lesson' => [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'content_type' => $lesson->content_type,
                'course' => $lesson->course?->title,
            ],
            'launchUrl' => $url,
            'disclaimer' => 'lms.ext.disclaimer',
        ]));
    }
}
