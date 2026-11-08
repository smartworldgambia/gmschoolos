<?php

namespace Tests\Feature;

use App\Exceptions\AcademicRuleException;
use App\Models\Student;
use App\Models\SsApplication;
use App\Models\SsApplicationEvent;
use App\Models\SsRound;
use App\Notifications\SeniorApplicationReceived;
use App\Services\Records\DocumentService;
use App\Services\SeniorSecondary\RulesEngine;
use App\Services\SeniorSecondary\SeniorSecondaryService;
use Illuminate\Support\Facades\Notification;

/** Phase 6 - Senior Secondary admission rules engine, public application, ranking and human override. */
class SeniorSecondaryTest extends Phase6TestCase
{
    protected SeniorSecondaryService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(SeniorSecondaryService::class);
    }

    protected function rules(array $over = []): array
    {
        return array_replace([
            'direction' => 'lower_better',
            'subjects' => [
                ['code' => 'ENG', 'name' => 'English'], ['code' => 'MATH', 'name' => 'Mathematics'], ['code' => 'SCI', 'name' => 'Science'],
                ['code' => 'SOC', 'name' => 'Social Studies'], ['code' => 'PHY', 'name' => 'Physics'], ['code' => 'CHE', 'name' => 'Chemistry'],
            ],
            'grade_points' => ['A1' => 1, 'B2' => 2, 'B3' => 3, 'C4' => 4, 'C5' => 5, 'C6' => 6, 'D7' => 7, 'E8' => 8, 'F9' => 9],
            'required_subjects' => ['ENG', 'MATH'], 'pass_limit' => 6, 'elective_count' => 2, 'weights' => [],
            'combinations' => [], 'min_subjects' => 4, 'aggregate_threshold' => 14,
        ], $over);
    }

    protected function round(array $rules = [], array $over = []): SsRound
    {
        $round = $this->svc->saveRound($over + ['name' => '2026 Intake', 'opens_on' => null, 'closes_on' => null, 'capacity' => 2, 'waitlist_capacity' => 1], $this->rules($rules));
        $this->svc->setRoundStatus($round, 'open');

        return $round->refresh();
    }

    protected function apply(SsRound $round, array $grades, array $data = []): SsApplication
    {
        static $n = 0;
        $n++;

        return $this->svc->submit($this->school, $round, $data + [
            'first_name' => 'Applicant'.$n, 'last_name' => 'Family'.$n, 'gender' => 'female', 'date_of_birth' => '2010-02-0'.(($n % 8) + 1),
            'previous_school' => 'Primary School X', 'guardian_name' => 'Guardian', 'guardian_phone' => '+22070000'.$n,
        ], $grades, 'online');
    }

    protected function refuses(string $key, callable $fn): void
    {
        try {
            $fn();
            $this->fail("Expected refusal {$key}");
        } catch (AcademicRuleException $e) {
            $this->assertSame($key, $e->getMessage());
        }
    }

    protected const GOOD = ['ENG' => 'B2', 'MATH' => 'C4', 'SCI' => 'B3', 'SOC' => 'A1'];

    // ------------------------------------------------------------------ rules engine (pure)

    public function test_same_results_get_different_outcomes_under_different_school_configuration(): void
    {
        $engine = app(RulesEngine::class);
        $lenient = $engine->normalise($this->rules());
        $strict = $engine->normalise($this->rules(['aggregate_threshold' => 8, 'required_subjects' => ['ENG', 'MATH', 'SCI']]));

        $a = $engine->assess($lenient, self::GOOD);
        $b = $engine->assess($strict, self::GOOD);

        $this->assertSame(10.0, $a['aggregate']); // ENG 2 + MATH 4 + best two electives (SOC 1 + SCI 3)
        $this->assertTrue($a['eligible']);
        $this->assertFalse($b['eligible']);
        $this->assertContains('threshold', $b['reasons']);
    }

    public function test_required_subjects_pass_limit_and_minimum_subjects(): void
    {
        $engine = app(RulesEngine::class);
        $rules = $engine->normalise($this->rules());

        $missing = $engine->assess($rules, ['ENG' => 'A1', 'SCI' => 'A1', 'SOC' => 'A1', 'PHY' => 'A1']);
        $this->assertContains('missingRequired:MATH', $missing['reasons']);
        $this->assertFalse($missing['eligible']);

        $failed = $engine->assess($rules, ['ENG' => 'A1', 'MATH' => 'E8', 'SCI' => 'A1', 'SOC' => 'A1']);
        $this->assertContains('failedRequired:MATH', $failed['reasons']);

        $few = $engine->assess($rules, ['ENG' => 'A1', 'MATH' => 'A1']);
        $this->assertContains('minSubjects', $few['reasons']);
        $this->assertContains('notEnoughElectives', $few['reasons']);
    }

    public function test_weighted_scoring_and_combinations(): void
    {
        $engine = app(RulesEngine::class);
        $weighted = $engine->normalise($this->rules(['weights' => ['MATH' => 2], 'aggregate_threshold' => null]));
        $this->assertSame(14.0, $engine->assess($weighted, self::GOOD)['aggregate']); // 2 + (4*2) + 1 + 3

        $combos = $engine->normalise($this->rules(['combinations' => [['name' => 'Science', 'subjects' => ['PHY', 'CHE'], 'min' => 2]], 'aggregate_threshold' => null, 'elective_count' => 2]));
        $without = $engine->assess($combos, self::GOOD);
        $this->assertContains('combination', $without['reasons']);

        $with = $engine->assess($combos, self::GOOD + ['PHY' => 'B3', 'CHE' => 'C5']);
        $this->assertTrue($with['eligible']);
        $this->assertSame('Science', $with['combination']);

        $chosen = $engine->assess($combos, self::GOOD + ['PHY' => 'B3', 'CHE' => 'C5'], 'Science');
        $this->assertSame('Science', $chosen['combination']);
    }

    public function test_higher_is_better_direction(): void
    {
        $engine = app(RulesEngine::class);
        $rules = $engine->normalise([
            'direction' => 'higher_better', 'subjects' => [['code' => 'A', 'name' => 'A'], ['code' => 'B', 'name' => 'B']],
            'grade_points' => ['X' => 10, 'Y' => 5], 'required_subjects' => ['A'], 'pass_limit' => 6, 'elective_count' => 1, 'aggregate_threshold' => 14,
        ]);

        $this->assertTrue($engine->assess($rules, ['A' => 'X', 'B' => 'Y'])['eligible']);   // 15 >= 14
        $this->assertFalse($engine->assess($rules, ['A' => 'Y', 'B' => 'Y'])['eligible']);  // 10 < 14 and A below pass limit
    }

    public function test_unknown_grades_and_invalid_configuration_are_refused(): void
    {
        $engine = app(RulesEngine::class);
        $this->assertContains('unknownGrade:ENG', $engine->assess($engine->normalise($this->rules()), ['ENG' => 'ZZ'])['reasons']);

        $this->refuses('err.ss.rulesGradePoints', fn () => $engine->normalise($this->rules(['grade_points' => []])));
        $this->refuses('err.ss.rulesUnknownSubject', fn () => $engine->normalise($this->rules(['required_subjects' => ['NOPE']])));
        $this->refuses('err.ss.rulesCombination', fn () => $engine->normalise($this->rules(['combinations' => [['name' => 'X', 'subjects' => ['NOPE']]]])));
        $this->refuses('err.ss.rulesWeights', fn () => $engine->normalise($this->rules(['weights' => ['ENG' => 0]])));
    }

    public function test_no_named_school_requirements_are_hard_coded(): void
    {
        $source = '';
        foreach (glob(app_path('Services/SeniorSecondary/*.php')) as $file) {
            $source .= file_get_contents($file);
        }
        foreach (['Armitage', 'St. Augustine', 'Gambia High', 'Nusrat', 'Muslim High', 'Saint Joseph', 'Marina International'] as $name) {
            $this->assertStringNotContainsString($name, $source);
        }
    }

    // ------------------------------------------------------------------ rounds

    public function test_round_needs_capacity_and_valid_period_and_follows_a_lifecycle(): void
    {
        $this->refuses('err.ss.periodInvalid', fn () => $this->svc->saveRound(['name' => 'Bad', 'opens_on' => '2026-06-01', 'closes_on' => '2026-05-01', 'capacity' => 5], $this->rules()));

        $r = $this->svc->saveRound(['name' => 'Zero', 'opens_on' => null, 'closes_on' => null, 'capacity' => 0], $this->rules());
        $this->refuses('err.ss.roundIncomplete', fn () => $this->svc->setRoundStatus($r, 'open'));
        $this->refuses('err.ss.roundTransition', fn () => $this->svc->setRoundStatus($r, 'finalised'));

        $r2 = $this->round();
        $this->assertTrue($this->svc->isAcceptingApplications($r2));
        $r2->update(['closes_on' => now()->subDay()]);
        $this->assertFalse($this->svc->isAcceptingApplications($r2->refresh()), 'outside the application period');
        $this->refuses('err.ss.notOpen', fn () => $this->apply($r2, self::GOOD));
    }

    // ------------------------------------------------------------------ public application

    public function test_public_form_creates_only_a_reviewable_application(): void
    {
        Notification::fake();
        $staff = $this->user('admissions_officer');
        $round = $this->round();
        $url = route('school.senior.apply.store', ['school_slug' => 'alpha', 'round_slug' => $round->slug]);
        $payload = [
            'first_name' => 'Awa', 'last_name' => 'Jallow', 'gender' => 'female', 'date_of_birth' => '2010-04-05', 'previous_school' => 'Bakau Lower Basic',
            'guardian_name' => 'Fatou', 'guardian_phone' => '+2207111111', 'guardian_email' => 'fatou@example.test',
            'gabece_index_number' => ' as/typed-1 ', 'grades' => self::GOOD, 'consent' => true,
        ];

        $this->get(route('school.senior.apply', ['school_slug' => 'alpha']))->assertOk();
        $this->get(route('school.senior.apply', ['school_slug' => 'alpha', 'round_slug' => $round->slug]))->assertOk();

        $students = Student::query()->count();
        $this->post($url, $payload)->assertSessionHas('applied');

        $app = SsApplication::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^SSA-\d{4}-\d{5}$/', $app->reference);
        $this->assertSame('submitted', $app->status);
        $this->assertSame($students, Student::query()->count(), 'no student record is created by a public application');
        $this->assertNull($app->results_verified_at);
        $this->assertSame(4, $app->results()->count());
        $this->assertNotNull($app->consent_at);
        $this->assertSame('review', $app->recommendation, 'unverified results are never ranked');
        Notification::assertSentTo($staff, SeniorApplicationReceived::class);
        Notification::assertSentOnDemand(SeniorApplicationReceived::class);
    }

    public function test_public_form_rejects_bots_missing_consent_bad_grades_and_closed_rounds(): void
    {
        $round = $this->round();
        $url = route('school.senior.apply.store', ['school_slug' => 'alpha', 'round_slug' => $round->slug]);
        $ok = [
            'first_name' => 'Awa', 'last_name' => 'Jallow', 'gender' => 'female', 'date_of_birth' => '2010-04-05', 'previous_school' => 'X',
            'guardian_name' => 'F', 'guardian_phone' => '+2207111111', 'grades' => self::GOOD, 'consent' => true,
        ];

        $this->post($url, $ok + ['company_website' => 'http://spam'])->assertSessionHas('applied');
        $this->assertSame(0, SsApplication::query()->count(), 'honeypot hits store nothing');

        $this->post($url, array_merge($ok, ['consent' => false]))->assertSessionHasErrors('consent');
        $this->post($url, array_merge($ok, ['grades' => ['ENG' => 'ZZ']]))->assertSessionHasErrors('grades');
        $this->post($url, array_merge($ok, ['grades' => ['NOPE' => 'A1']]))->assertSessionHasErrors('grades');
        $this->post($url, array_merge($ok, ['grades' => ['ENG' => '']]))->assertSessionHasErrors('grades');
        $this->assertSame(0, SsApplication::query()->count());
    }

    public function test_public_form_ignores_repeat_submissions_and_closed_rounds(): void
    {
        $round = $this->round();
        $url = route('school.senior.apply.store', ['school_slug' => 'alpha', 'round_slug' => $round->slug]);
        $ok = [
            'first_name' => 'Awa', 'last_name' => 'Jallow', 'gender' => 'female', 'date_of_birth' => '2010-04-05', 'previous_school' => 'X',
            'guardian_name' => 'F', 'guardian_phone' => '+2207111111', 'grades' => self::GOOD, 'consent' => true,
        ];

        $this->post($url, $ok)->assertSessionHas('applied');
        $this->post($url, $ok)->assertSessionHas('applied'); // repeat within 30 days: silently not stored again
        $this->assertSame(1, SsApplication::query()->count());

        $this->svc->setRoundStatus($round, 'closed');
        $this->post($url, array_merge($ok, ['first_name' => 'Other', 'guardian_phone' => '+2207222222']))->assertSessionHasErrors('grades');
        $this->assertSame(1, SsApplication::query()->count());
    }

    public function test_public_form_is_not_available_for_unpublished_or_other_schools(): void
    {
        $round = $this->round();
        $this->school->update(['website_published' => false]);
        $this->get(route('school.senior.apply', ['school_slug' => 'alpha', 'round_slug' => $round->slug]))->assertNotFound();
        $this->get(route('school.senior.apply', ['school_slug' => 'nobody']))->assertNotFound();
    }

    // ------------------------------------------------------------------ ranking, shortlist, waiting list

    protected function fourApplicants(SsRound $round): array
    {
        $best = $this->apply($round, ['ENG' => 'A1', 'MATH' => 'A1', 'SCI' => 'A1', 'SOC' => 'A1']);        // 1+1+1+1 = 4
        $good = $this->apply($round, self::GOOD);                                                           // 10
        $okay = $this->apply($round, ['ENG' => 'C4', 'MATH' => 'C5', 'SCI' => 'C6', 'SOC' => 'C6']);        // 4+5+6+6 = 21 > 14 -> ineligible
        $edge = $this->apply($round, ['ENG' => 'B3', 'MATH' => 'B3', 'SCI' => 'C4', 'SOC' => 'C4']);        // 3+3+4+4 = 14 -> eligible (threshold inclusive)

        return [$best, $good, $okay, $edge];
    }

    public function test_only_verified_applications_are_ranked_and_recommendations_follow_capacity(): void
    {
        $user = $this->user('admissions_officer');
        $round = $this->round();
        [$best, $good, $okay, $edge] = $this->fourApplicants($round);

        $this->svc->rank($round);
        $this->assertNull($best->refresh()->rank_position);
        $this->assertSame('review', $best->recommendation);

        foreach ([$best, $good, $okay, $edge] as $a) {
            $this->svc->verifyResults($user, $a);
        }

        $this->assertSame([1, 2, null, 3], [$best->refresh()->rank_position, $good->refresh()->rank_position, $okay->refresh()->rank_position, $edge->refresh()->rank_position]);
        $this->assertSame(['shortlist', 'shortlist', 'reject', 'waitlist'], [$best->recommendation, $good->recommendation, $okay->recommendation, $edge->recommendation]);
        $this->assertFalse((bool) $okay->eligible);
        $this->assertContains('threshold', $okay->assessment['reasons']);
    }

    public function test_applying_recommendations_is_a_human_action_that_is_logged(): void
    {
        $user = $this->user('admissions_officer');
        $round = $this->round();
        [$best, $good, $okay, $edge] = $this->fourApplicants($round);
        foreach ([$best, $good, $okay, $edge] as $a) {
            $this->svc->verifyResults($user, $a);
        }

        $this->assertSame(3, $this->svc->applyRecommendations($user, $round));
        $this->assertSame(['shortlisted', 'shortlisted', 'under_review', 'waitlisted'], [$best->refresh()->status, $good->refresh()->status, $okay->refresh()->status, $edge->refresh()->status]);

        $event = SsApplicationEvent::query()->where('application_id', $best->id)->where('event', 'recommendation_applied')->firstOrFail();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(['rank' => 1, 'recommendation' => 'shortlist'], $event->data);
        $this->refuses('err.ss.nothingToApply', fn () => $this->svc->applyRecommendations($user, $round));
    }

    public function test_human_override_needs_a_reason_and_leaves_an_audit_trail(): void
    {
        $user = $this->user('admissions_officer');
        $round = $this->round();
        [$best, $good, $okay, $edge] = $this->fourApplicants($round);
        foreach ([$best, $good, $okay, $edge] as $a) {
            $this->svc->verifyResults($user, $a);
        }

        // Following the recommendation (within capacity) needs no reason
        $this->svc->decide($user, $best, 'approved');
        $this->svc->decide($user, $good, 'approved');
        $this->assertFalse($best->refresh()->is_override || $good->refresh()->is_override);

        // Departing from the recommendation needs one (okay was recommended for rejection)
        $this->refuses('err.ss.overrideReason', fn () => $this->svc->decide($user, $okay, 'approved'));
        $this->svc->decide($user, $okay, 'approved', 'Documented special circumstances reviewed by the head');
        $okay->refresh();
        $this->assertTrue($okay->is_override);
        $this->assertSame('approved', $okay->status);
        $this->assertSame($user->id, $okay->decided_by);

        $override = SsApplicationEvent::query()->where('application_id', $okay->id)->where('event', 'override')->firstOrFail();
        $this->assertSame('Documented special circumstances reviewed by the head', $override->note);
        $this->assertSame(['recommendation' => 'reject', 'rank' => null, 'eligible' => false, 'aggregate' => '21.00'], collect($override->data)->only(['recommendation', 'rank', 'eligible', 'aggregate'])->all());

        // Capacity (2) is already used: approving a waitlisted applicant is an override too
        $this->refuses('err.ss.overrideReason', fn () => $this->svc->decide($user, $edge, 'approved'));
        $this->svc->decide($user, $edge, 'approved', 'Additional place granted by the board');
        $this->assertTrue($edge->refresh()->is_override);
    }

    public function test_cannot_approve_without_verified_results_and_audit_history_is_append_only(): void
    {
        $user = $this->user('admissions_officer');
        $round = $this->round();
        $a = $this->apply($round, self::GOOD);

        $this->refuses('err.ss.verifyFirst', fn () => $this->svc->decide($user, $a, 'approved', 'because'));

        $event = SsApplicationEvent::query()->firstOrFail();
        $this->expectException(\LogicException::class);
        $event->delete();
    }

    public function test_verification_may_correct_declared_grades_and_is_audited(): void
    {
        $user = $this->user('admissions_officer');
        $round = $this->round();
        $a = $this->apply($round, ['ENG' => 'A1', 'MATH' => 'A1', 'SCI' => 'A1', 'SOC' => 'A1']);
        $this->assertSame(4.0, (float) $a->aggregate);

        $this->svc->verifyResults($user, $a, ['MATH' => 'D7'], 'Slip shows D7');
        $a->refresh();
        $this->assertSame('D7', $a->results()->where('subject_code', 'MATH')->value('grade'));
        $this->assertFalse((bool) $a->eligible, 'D7 is below the configured pass limit for a required subject');
        $this->assertContains('failedRequired:MATH', $a->assessment['reasons']);

        $change = SsApplicationEvent::query()->where('event', 'result_corrected')->firstOrFail();
        $this->assertSame(['subject' => 'MATH', 'from' => 'A1', 'to' => 'D7'], $change->data);
        $this->assertSame('Slip shows D7', $change->note);
    }

    public function test_round_finalisation_and_withdrawal(): void
    {
        $user = $this->user('admissions_officer');
        $round = $this->round();
        $a = $this->apply($round, self::GOOD);
        $this->svc->verifyResults($user, $a);

        $this->refuses('err.ss.closeFirst', fn () => $this->svc->finalise($user, $round));
        $this->svc->setRoundStatus($round, 'closed');
        $this->refuses('err.ss.undecided', fn () => $this->svc->finalise($user, $round));

        $this->svc->decide($user, $a, 'approved');
        $this->svc->finalise($user, $round);
        $this->assertSame('finalised', $round->refresh()->status);
        $this->refuses('err.ss.finalised', fn () => $this->svc->decide($user, $a->refresh(), 'rejected', 'x'));

        $round2 = $this->round([], ['name' => 'Second']);
        $b = $this->apply($round2, self::GOOD);
        $this->svc->withdraw($user, $b, 'Family moved');
        $this->assertSame('withdrawn', $b->refresh()->status);
        $this->refuses('err.ss.withdrawn', fn () => $this->svc->decide($user, $b, 'approved', 'x'));
    }

    // ------------------------------------------------------------------ admission letter

    public function test_admission_letter_only_for_approved_applicants_and_follows_the_document_workflow(): void
    {
        $officer = $this->user('admissions_officer');
        $round = $this->round();
        $a = $this->apply($round, self::GOOD);
        $docs = app(DocumentService::class);

        $this->refuses('err.rec.notApproved', fn () => $docs->createAdmissionLetter($officer, $a, null, null));

        $this->svc->verifyResults($officer, $a);
        $this->svc->decide($officer, $a, 'approved');

        $teacher = $this->user('teacher');
        $this->refuses('err.rec.permission', fn () => $docs->createAdmissionLetter($teacher, $a->refresh(), null, null));

        $doc = $docs->createAdmissionLetter($officer, $a, null, 'Please report on the first day.');
        $this->assertStringContainsString($a->first_name, $doc->body);
        $this->assertStringContainsString('Please report on the first day.', $doc->body);
        $this->assertStringContainsString('2026 Intake', $doc->body);

        $docs->submit($officer, $doc);
        $docs->approve($officer, $doc->refresh());
        $docs->issue($officer, $doc->refresh());
        $this->assertSame('issued', $doc->refresh()->status);
        $this->assertSame($a->id, $doc->ss_application_id);
        $this->assertStringStartsWith('ADL-', $doc->reference);
    }

    // ------------------------------------------------------------------ HTTP, permissions, tenancy

    /** Regression: route-model binding must resolve own-school rows when no tenant is pre-bound (a real request). */
    public function test_own_round_and_application_resolve_when_no_tenant_is_pre_bound(): void
    {
        $round = $this->round();
        $a = $this->apply($round, self::GOOD);

        $this->actAs($this->admin)->get(route('senior-secondary.rounds.show', $round))->assertOk();
        $this->actAs($this->admin)->get(route('senior-secondary.applications.show', $a))->assertOk();
    }

    public function test_staff_pages_and_actions_over_http(): void
    {
        $round = $this->round();
        $a = $this->apply($round, self::GOOD);

        $this->actingAs($this->admin)->get(route('senior-secondary.rounds.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('senior-secondary.rounds.show', $round))->assertOk();
        $this->actingAs($this->admin)->get(route('senior-secondary.applications.show', $a))->assertOk();

        $this->actingAs($this->admin)->post(route('senior-secondary.applications.decide', $a), ['decision' => 'approved', 'reason' => ''])
            ->assertSessionHas('error', 'err.ss.verifyFirst');
        $this->actingAs($this->admin)->post(route('senior-secondary.applications.verify', $a), [])->assertSessionHas('success', 'ok.ss.verified');
        $this->actingAs($this->admin)->post(route('senior-secondary.applications.decide', $a), ['decision' => 'shortlisted'])->assertSessionHas('success', 'ok.ss.decided');
        $this->actingAs($this->admin)->post(route('senior-secondary.rounds.recompute', $round))->assertSessionHas('success', 'ok.ss.recomputed');
    }

    public function test_rules_can_be_saved_through_the_form_shape(): void
    {
        $payload = [
            'name' => 'Intake 2027', 'capacity' => 30, 'waitlist_capacity' => 5, 'opens_on' => '2027-01-01', 'closes_on' => '2027-02-01',
            'rules' => [
                'direction' => 'lower_better',
                'subjects' => [['code' => 'ENG', 'name' => 'English'], ['code' => 'MATH', 'name' => 'Maths']],
                'grade_points' => [['grade' => 'A', 'points' => 1], ['grade' => 'B', 'points' => 2], ['grade' => 'C', 'points' => 3]],
                'required_subjects' => ['ENG'], 'weights' => [['code' => 'MATH', 'weight' => 2]],
                'combinations' => [['name' => 'General', 'subjects' => ['ENG', 'MATH'], 'min' => 1]],
                'pass_limit' => 3, 'elective_count' => 1, 'min_subjects' => 2, 'aggregate_threshold' => 6,
            ],
        ];
        $this->actingAs($this->admin)->post(route('senior-secondary.rounds.store'), $payload)->assertRedirect();

        $round = SsRound::query()->firstOrFail();
        $this->assertEquals(['A' => 1, 'B' => 2, 'C' => 3], $round->rules['grade_points']);
        $this->assertEquals(['MATH' => 2], $round->rules['weights']);
        $this->assertSame('draft', $round->status);

        $this->actingAs($this->admin)->put(route('senior-secondary.rounds.update', $round), array_merge($payload, ['capacity' => 40]))->assertSessionHas('success');
        $this->assertSame(40, $round->refresh()->capacity);
    }

    public function test_roles_and_tenant_isolation(): void
    {
        $round = $this->round();
        $a = $this->apply($round, self::GOOD);

        $teacher = $this->user('teacher');
        $this->actingAs($teacher)->get(route('senior-secondary.rounds.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('senior-secondary.applications.verify', $a))->assertForbidden();

        $beta = $this->otherSchool();
        app(\App\Support\TenantContext::class)->clear();
        $outsider = $this->user('school_admin', $beta);
        $this->actAs($outsider)->get(route('senior-secondary.rounds.show', $round))->assertNotFound();
        $this->actAs($outsider)->get(route('senior-secondary.applications.show', $a))->assertNotFound();
        $this->actAs($outsider)->post(route('senior-secondary.applications.decide', $a), ['decision' => 'approved', 'reason' => 'x'])->assertNotFound();
        $this->assertSame('submitted', SsApplication::query()->withoutGlobalScopes()->find($a->id)->status);
    }
}
