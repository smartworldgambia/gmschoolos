<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\RecordsTemplate;
use App\Services\Records\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public const PLACEHOLDERS = ['student_name', 'student_number', 'school_name', 'date', 'programme', 'round_name'];

    public function __construct(protected DocumentService $service) {}

    public function index(Request $request): Response
    {
        $u = $request->user();
        $types = array_values(array_filter(['testimonial', 'cover_letter', 'admission_letter'], fn ($t) => $t === 'admission_letter' ? $u->can('senior_admissions.manage') : $u->can('records.manage')));

        return Inertia::render('Records/Templates', $this->shellProps() + [
            'templates' => RecordsTemplate::query()->whereIn('type', $types)->orderBy('type')->orderBy('name')->get()
                ->map(fn ($t) => ['id' => $t->id, 'type' => $t->type, 'name' => $t->name, 'body' => $t->body, 'is_default' => $t->is_default, 'is_active' => $t->is_active]),
            'types' => $types, 'placeholders' => self::PLACEHOLDERS,
            'defaults' => collect($types)->mapWithKeys(fn ($t) => [$t => $this->service->defaultBody($t)]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->attempt(fn () => $this->service->saveTemplate($request->user(), $this->validated($request)), 'ok.saved');
    }

    public function update(Request $request, RecordsTemplate $template): RedirectResponse
    {
        $data = $this->validated($request);
        $data['type'] = $template->type; // a template never changes type

        return $this->attempt(fn () => $this->service->saveTemplate($request->user(), $data, $template), 'ok.saved');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:testimonial,cover_letter,admission_letter'], 'name' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:6000'], 'is_default' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
