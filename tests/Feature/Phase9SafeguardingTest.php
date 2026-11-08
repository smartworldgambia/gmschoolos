<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\SafeguardingCase;
use App\Notifications\SchoolAnnouncement;
use Illuminate\Support\Facades\Notification;

/** Phase 9F: restricted case files, report vs manage, audit without summaries. */
class Phase9SafeguardingTest extends Phase9TestCase
{
    public function test_teachers_can_report_but_cannot_open_the_case_list(): void
    {
        $this->actAs($this->teacher)->get(route('safeguarding.index'))->assertForbidden();
        $this->actAs($this->teacher)->get(route('safeguarding.report'))->assertOk()->assertInertia(fn ($p) => $p->component('Safeguarding/Report'));
        $this->actAs($this->admin)->get(route('safeguarding.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Safeguarding/Index'));
    }

    public function test_the_case_list_never_includes_the_summary_and_opening_is_logged(): void
    {
        $student = $this->student();
        $this->actAs($this->teacher)->post(route('safeguarding.store'), [
            'category' => 'bullying', 'severity' => 'high', 'student_id' => $student->id,
            'summary' => 'Named peer conflict that must not leak into the list.',
        ])->assertSessionHas('success', 'ok.sg.reported');

        $case = SafeguardingCase::query()->withoutGlobalScopes()->firstOrFail();
        $this->actAs($this->teacher)->get(route('safeguarding.show', $case->id))->assertForbidden();

        $this->actAs($this->admin)->get(route('safeguarding.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('cases', 1)->where('cases.0.id', $case->id)->missing('cases.0.summary')->missing('cases.0.subject_name'));

        $this->actAs($this->admin)->get(route('safeguarding.show', $case->id))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Safeguarding/Show')->where('case.summary', 'Named peer conflict that must not leak into the list.'));

        $log = ActivityLog::query()->withoutGlobalScopes()->where('event', 'sg.accessed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($case->id, $log->properties['case_id'] ?? null);
        $this->assertArrayNotHasKey('summary', $log->properties ?? []);
        $this->assertStringNotContainsString('Named peer', json_encode($log->properties));
    }

    public function test_assign_close_and_notes_are_manage_only_and_close_needs_a_note(): void
    {
        $this->actAs($this->admin)->post(route('safeguarding.store'), [
            'category' => 'welfare', 'severity' => 'medium', 'subject_name' => 'Visitor child',
            'summary' => 'Concern at the gate.',
        ])->assertSessionHas('success', 'ok.sg.opened');
        $case = SafeguardingCase::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->teacher)->post(route('safeguarding.assign', $case->id), ['assigned_to' => $this->admin->id])->assertForbidden();
        $this->actAs($this->admin)->post(route('safeguarding.assign', $case->id), ['assigned_to' => $this->teacher->id])->assertSessionHas('error', 'err.sg.assignee');
        $this->actAs($this->admin)->post(route('safeguarding.assign', $case->id), ['assigned_to' => $this->head->id])->assertSessionHas('success', 'ok.sg.assigned');
        $this->actAs($this->admin)->post(route('safeguarding.notes', $case->id), ['body' => 'Spoke with the class teacher.'])->assertSessionHas('success', 'ok.sg.noteAdded');
        $this->assertSame('investigating', $case->fresh()->status);

        $this->actAs($this->admin)->post(route('safeguarding.close', $case->id), [])->assertSessionHasErrors('closure_note');
        $this->actAs($this->admin)->post(route('safeguarding.close', $case->id), ['closure_note' => 'Family meeting held.'])->assertSessionHas('success', 'ok.sg.closed');
        $this->actAs($this->admin)->post(route('safeguarding.close', $case->id), ['closure_note' => 'Again'])->assertSessionHas('error', 'err.sg.alreadyClosed');
    }

    public function test_a_critical_report_notifies_officers_and_needs_a_subject(): void
    {
        Notification::fake();
        $this->actAs($this->teacher)->post(route('safeguarding.store'), [
            'category' => 'abuse_concern', 'severity' => 'critical', 'summary' => 'No subject',
        ])->assertSessionHas('error', 'err.sg.subject');

        $this->actAs($this->teacher)->post(route('safeguarding.store'), [
            'category' => 'abuse_concern', 'severity' => 'critical', 'student_id' => $this->student()->id,
            'summary' => 'Immediate concern.',
        ])->assertSessionHas('success', 'ok.sg.reported');
        Notification::assertSentTo($this->admin, SchoolAnnouncement::class);
        Notification::assertNotSentTo($this->teacher, SchoolAnnouncement::class);
    }

    public function test_another_schools_cases_are_invisible(): void
    {
        $other = $this->otherSchool();
        $foreign = $this->inSchool(fn () => SafeguardingCase::query()->create([
            'reference' => 'SG-OTHER', 'category' => 'other', 'severity' => 'low', 'status' => 'open',
            'summary' => 'Foreign', 'reported_by' => $this->admin->id,
        ]), $other);

        $this->actAs($this->admin)->get(route('safeguarding.show', $foreign->id))->assertNotFound();
        $this->actAs($this->admin)->post(route('safeguarding.notes', $foreign->id), ['body' => 'x'])->assertNotFound();
    }
}
