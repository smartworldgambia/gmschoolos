<?php

namespace App\Http\Controllers\Records;

use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentTermRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Historical term results recorded by hand, for learners whose earlier terms were taught before the school
 * adopted the platform. Platform-published grades always take precedence over these rows in a transcript.
 */
class HistoryController extends Controller
{
    use HandlesAcademicRules;
    use SharesSchoolShell;

    public function show(Student $student): Response
    {
        $records = StudentTermRecord::query()->where('student_id', $student->id)->orderBy('grade_number')->orderBy('term_sequence')->get()
            ->map(fn ($r) => ['id' => $r->id, 'grade_number' => $r->grade_number, 'term_sequence' => $r->term_sequence, 'academic_year_label' => $r->academic_year_label, 'entries' => $r->entries, 'source' => $r->source]);

        return Inertia::render('Records/History', $this->shellProps() + [
            'student' => ['id' => $student->id, 'name' => $student->full_name, 'student_number' => $student->student_number],
            'records' => $records,
        ]);
    }

    public function store(Request $request, Student $student): RedirectResponse
    {
        $v = $request->validate([
            'grade_number' => ['required', 'integer', 'between:7,12'], 'term_sequence' => ['required', 'integer', 'between:1,3'],
            'academic_year_label' => ['required', 'string', 'max:40'],
            'entries' => ['required', 'array', 'min:1', 'max:30'],
            'entries.*.subject' => ['required', 'string', 'max:100'],
            'entries.*.mark' => ['nullable', 'numeric', 'between:0,1000'],
            'entries.*.grade' => ['nullable', 'string', 'max:12'],
        ]);

        StudentTermRecord::query()->updateOrCreate(
            ['student_id' => $student->id, 'grade_number' => $v['grade_number'], 'term_sequence' => $v['term_sequence']],
            ['school_id' => $student->school_id, 'academic_year_label' => $v['academic_year_label'], 'entries' => array_values($v['entries']), 'source' => 'manual', 'entered_by' => $request->user()->id]
        );

        \App\Models\ActivityLog::query()->create([
            'school_id' => $student->school_id, 'user_id' => $request->user()->id, 'event' => 'records.history.saved',
            'description' => "{$student->student_number}: G{$v['grade_number']} T{$v['term_sequence']} historical record saved",
        ]);

        return back()->with('success', 'ok.saved');
    }

    public function destroy(Student $student, StudentTermRecord $record): RedirectResponse
    {
        abort_unless($record->student_id === $student->id, 404);
        $record->delete();

        return back()->with('success', 'ok.saved');
    }
}
