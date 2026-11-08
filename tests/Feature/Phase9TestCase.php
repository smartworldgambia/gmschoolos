<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\FeeCategory;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolPaymentMethod;
use App\Models\Staff;
use App\Models\Term;
use App\Models\User;

/** Shared fixtures for the Phase 9 finance and operations suites (builds on the Phase 8 school, admin, teacher, classes). */
abstract class Phase9TestCase extends Phase8TestCase
{
    protected AcademicYear $year;

    protected Term $term1;

    protected Term $term2;

    protected User $head;

    protected User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->year = AcademicYear::query()->create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-31', 'is_current' => true]);
        $this->term1 = Term::query()->create(['academic_year_id' => $this->year->id, 'name' => 'Term 1', 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-15']);
        $this->term2 = Term::query()->create(['academic_year_id' => $this->year->id, 'name' => 'Term 2', 'starts_on' => '2027-01-05', 'ends_on' => '2027-04-01']);
        $this->head = $this->user('headteacher');
        $this->finance = $this->user('finance_officer');
    }

    protected function category(string $name = 'Tuition'): FeeCategory
    {
        return $this->inSchool(fn () => FeeCategory::query()->firstOrCreate(['name' => $name], ['is_active' => true]));
    }

    protected function structure(int $amount, array $over = []): FeeStructure
    {
        $category = $over['category'] ?? $this->category();
        unset($over['category']);

        return $this->inSchool(fn () => FeeStructure::query()->create($over + [
            'fee_category_id' => $category->id,
            'academic_year_id' => $this->year->id, 'term_id' => $this->term1->id, 'amount' => $amount, 'is_optional' => false, 'is_active' => true,
        ]));
    }

    /** A learner with a ready-made issued invoice of $total Dalasi. */
    protected function invoiceFor($student, int $total = 1000, array $over = []): Invoice
    {
        return $this->inSchool(fn () => Invoice::query()->create($over + [
            'student_id' => $student->id, 'invoice_number' => 'INV-T-'.$student->id.'-'.random_int(100, 999999), 'status' => 'issued', 'source' => 'manual',
            'subtotal' => $total, 'adjustments_total' => 0, 'total_amount' => $total, 'amount_paid' => 0, 'issued_at' => now(), 'due_on' => now()->addDays(30)->toDateString(),
        ]));
    }

    protected function payMethod(string $method = 'wave', string $label = 'School Wave'): SchoolPaymentMethod
    {
        return $this->inSchool(fn () => SchoolPaymentMethod::query()->create(['method' => $method, 'label' => $label, 'account_number' => '7000000', 'is_active' => true]));
    }

    protected function staffRecord(string $first = 'Fatou', ?User $user = null, array $over = []): Staff
    {
        static $n = 0;
        $n++;

        return $this->inSchool(fn () => Staff::query()->create($over + ['staff_number' => 'STF-9-'.$n, 'first_name' => $first.$n, 'last_name' => 'Sowe', 'status' => 'active', 'user_id' => $user?->id]));
    }
}
