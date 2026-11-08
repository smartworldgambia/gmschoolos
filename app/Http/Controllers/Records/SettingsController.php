<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Services\Records\RecordsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function __construct(protected RecordsSettings $settings) {}

    public function edit(): Response
    {
        $s = $this->settings->all();

        return Inertia::render('Records/Settings', $this->shellProps() + [
            'signatories' => $s['signatories'], 'reminders' => $s['reminders'], 'hasStamp' => (bool) $s['stamp_path'],
            'levels' => Level::query()->orderBy('sort_order')->get(['id', 'name', 'grade_number']),
            'estimateText' => config('schoolos.external_exams.certificate_estimate_text'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'signatories' => ['nullable', 'array', 'max:3'], 'signatories.*.name' => ['nullable', 'string', 'max:120'], 'signatories.*.title' => ['nullable', 'string', 'max:120'],
            'reminders.enabled' => ['required', 'boolean'], 'reminders.interval_days' => ['required', 'integer', 'between:1,365'], 'reminders.max' => ['required', 'integer', 'between:1,50'],
        ]);

        $this->settings->save([
            'signatories' => array_values(array_filter($v['signatories'] ?? [], fn ($r) => filled($r['name'] ?? null))),
            'reminders' => ['enabled' => (bool) $v['reminders']['enabled'], 'interval_days' => (int) $v['reminders']['interval_days'], 'max' => (int) $v['reminders']['max']],
        ]);

        return back()->with('success', 'ok.saved');
    }

    public function levels(Request $request): RedirectResponse
    {
        $v = $request->validate(['levels' => ['required', 'array'], 'levels.*.id' => ['required', 'integer', $this->inSchool('levels')], 'levels.*.grade_number' => ['nullable', 'integer', 'between:1,13']]);
        foreach ($v['levels'] as $row) {
            Level::query()->whereKey($row['id'])->update(['grade_number' => $row['grade_number'] ?: null]);
        }

        return back()->with('success', 'ok.saved');
    }

    public function stamp(Request $request): RedirectResponse
    {
        $request->validate(['stamp' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:'.config('schoolos.records.stamp_max_kb')]]);
        $disk = config('schoolos.records.document_disk', 'local');
        $school = $this->settings->school();
        $old = $this->settings->all($school)['stamp_path'];

        $path = $request->file('stamp')->store('records/stamps/'.$school->id, $disk);
        $this->settings->save(['stamp_path' => $path], $school);
        if ($old) {
            Storage::disk($disk)->delete($old);
        }

        return back()->with('success', 'ok.saved');
    }

    public function destroyStamp(): RedirectResponse
    {
        $school = $this->settings->school();
        $old = $this->settings->all($school)['stamp_path'];
        $this->settings->save(['stamp_path' => null], $school);
        if ($old) {
            Storage::disk(config('schoolos.records.document_disk', 'local'))->delete($old);
        }

        return back()->with('success', 'ok.saved');
    }
}
