<?php

namespace App\Services\Academics;

use App\Exceptions\AcademicRuleException;
use App\Models\AcademicEvent;
use App\Models\AcademicYear;
use App\Models\ActivityLog;
use App\Models\Term;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Academic years, terms and the school calendar. Nothing here hard-codes dates or a number of terms:
 * a school may define any number of terms; three is only the common default offered by the UI.
 */
class AcademicCalendarService
{
    /**
     * @param  array{name:string,starts_on:string,ends_on:string,terms:array<int,array{name:string,starts_on:string,ends_on:string}>,make_current?:bool}  $data
     */
    public function createYear(array $data): AcademicYear
    {
        $terms = array_values($data['terms'] ?? []);
        $this->assertYear($data['starts_on'], $data['ends_on']);
        $this->assertTerms($data['starts_on'], $data['ends_on'], $terms);

        if (AcademicYear::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($data['name']))])->exists()) {
            throw AcademicRuleException::because('err.year.nameTaken');
        }
        if ($this->overlappingYear($data['starts_on'], $data['ends_on'])) {
            throw AcademicRuleException::because('err.year.overlap');
        }

        return DB::transaction(function () use ($data, $terms) {
            $year = AcademicYear::query()->create([
                'name' => trim($data['name']), 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'is_current' => false,
            ]);
            foreach ($terms as $t) {
                Term::query()->create([
                    'academic_year_id' => $year->id, 'name' => trim($t['name']),
                    'starts_on' => $t['starts_on'], 'ends_on' => $t['ends_on'], 'is_current' => false,
                ]);
            }
            if (! empty($data['make_current']) || ! AcademicYear::query()->where('is_current', true)->exists()) {
                $this->setCurrentYear($year);
            }
            $this->audit('academic_year_created', $year->name);

            return $year->fresh('terms');
        });
    }

    public function updateTerm(Term $term, array $data): Term
    {
        $year = AcademicYear::query()->findOrFail($term->academic_year_id);
        $start = $data['starts_on'];
        $end = $data['ends_on'];

        if (Carbon::parse($end)->lte(Carbon::parse($start))) {
            throw AcademicRuleException::because('err.term.order');
        }
        if (Carbon::parse($start)->lt($year->starts_on) || Carbon::parse($end)->gt($year->ends_on)) {
            throw AcademicRuleException::because('err.term.outside');
        }
        $clash = Term::query()->where('academic_year_id', $year->id)->where('id', '!=', $term->id)
            ->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->exists();
        if ($clash) {
            throw AcademicRuleException::because('err.term.overlap');
        }

        $term->update(['name' => trim($data['name']), 'starts_on' => $start, 'ends_on' => $end]);
        $this->audit('term_updated', $term->name);

        return $term->fresh();
    }

    public function setCurrentYear(AcademicYear $year): void
    {
        DB::transaction(function () use ($year) {
            AcademicYear::query()->where('id', '!=', $year->id)->update(['is_current' => false]);
            $year->update(['is_current' => true]);

            $today = now()->toDateString();
            $terms = Term::query()->where('academic_year_id', $year->id)->orderBy('starts_on')->get();
            $current = $terms->first(fn (Term $t) => $t->starts_on->toDateString() <= $today && $t->ends_on->toDateString() >= $today)
                ?? $terms->first(fn (Term $t) => $t->starts_on->toDateString() > $today)
                ?? $terms->last();

            Term::query()->update(['is_current' => false]);
            $current?->update(['is_current' => true]);
            $this->audit('academic_year_current', $year->name);
        });
    }

    public function setCurrentTerm(Term $term): void
    {
        DB::transaction(function () use ($term) {
            $year = AcademicYear::query()->findOrFail($term->academic_year_id);
            AcademicYear::query()->where('id', '!=', $year->id)->update(['is_current' => false]);
            $year->update(['is_current' => true]);
            Term::query()->update(['is_current' => false]);
            $term->update(['is_current' => true]);
            $this->audit('term_current', $term->name);
        });
    }

    public function addEvent(array $data): AcademicEvent
    {
        $end = $data['ends_on'] ?? null;
        if ($end && Carbon::parse($end)->lt(Carbon::parse($data['starts_on']))) {
            throw AcademicRuleException::because('err.event.order');
        }
        if (! in_array($data['type'], AcademicEvent::TYPES, true)) {
            throw AcademicRuleException::because('err.event.type');
        }

        $year = AcademicYear::query()->whereDate('starts_on', '<=', $data['starts_on'])->whereDate('ends_on', '>=', $data['starts_on'])->first();
        $term = $year ? Term::query()->where('academic_year_id', $year->id)->whereDate('starts_on', '<=', $data['starts_on'])->whereDate('ends_on', '>=', $data['starts_on'])->first() : null;

        $event = AcademicEvent::query()->create([
            'type' => $data['type'], 'title' => trim($data['title']), 'starts_on' => $data['starts_on'],
            'ends_on' => $end, 'notes' => $data['notes'] ?? null,
            'academic_year_id' => $year?->id, 'term_id' => $term?->id,
        ]);
        $this->audit('calendar_event_added', $event->title);

        return $event;
    }

    // ------------------------------------------------------------------ rules

    protected function assertYear(string $start, string $end): void
    {
        if (Carbon::parse($end)->lte(Carbon::parse($start))) {
            throw AcademicRuleException::because('err.year.order');
        }
    }

    /** @param  array<int,array{name:string,starts_on:string,ends_on:string}>  $terms */
    protected function assertTerms(string $yearStart, string $yearEnd, array $terms): void
    {
        if ($terms === []) {
            throw AcademicRuleException::because('err.year.noTerms');
        }
        $previousEnd = null;
        foreach ($terms as $t) {
            $s = Carbon::parse($t['starts_on']);
            $e = Carbon::parse($t['ends_on']);
            if ($e->lte($s)) {
                throw AcademicRuleException::because('err.term.order');
            }
            if ($s->lt(Carbon::parse($yearStart)) || $e->gt(Carbon::parse($yearEnd))) {
                throw AcademicRuleException::because('err.term.outside');
            }
            if ($previousEnd && $s->lte($previousEnd)) {
                throw AcademicRuleException::because('err.term.overlap');
            }
            $previousEnd = $e;
        }
    }

    protected function overlappingYear(string $start, string $end): bool
    {
        return AcademicYear::query()->whereDate('starts_on', '<=', $end)->whereDate('ends_on', '>=', $start)->exists();
    }

    protected function audit(string $event, string $description): void
    {
        ActivityLog::query()->create([
            'school_id' => app(\App\Support\TenantContext::class)->id() ?? auth()->user()?->school_id,
            'user_id' => auth()->id(), 'event' => $event, 'description' => $description,
        ]);
    }
}
