<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\AcademicRuleException;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rule;

trait HandlesAcademicRules
{
    /**
     * Run a business action; rule refusals become a translated `err.*` flash, success a translated `ok.*` flash.
     */
    protected function attempt(callable $fn, string $ok = 'ok.saved'): RedirectResponse
    {
        try {
            $result = $fn();
        } catch (AcademicRuleException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return $result instanceof RedirectResponse ? $result : back()->with('success', $ok);
    }

    /** Current tenant id (never trusts request input). */
    protected function schoolId(): int
    {
        return (int) (app(TenantContext::class)->id() ?? auth()->user()->school_id);
    }

    /**
     * `exists` rule limited to the current school. A bare `exists:table,id` would accept another school's row id.
     */
    protected function inSchool(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('school_id', $this->schoolId());
    }

    /**
     * People who may be picked as class teacher or subject teacher.
     * School admins hold portal.teacher via *school, so a permission query would list them and hide nothing —
     * we key off teaching roles, then keep anyone already assigned so an admin can still edit that row.
     *
     * @param  list<int|string|null>  $extraIds
     */
    protected function teachingStaff(array $extraIds = []): Collection
    {
        $extraIds = collect($extraIds)->filter()->map(fn ($id) => (int) $id)->unique()->values();

        return User::query()->where('school_id', $this->schoolId())
            ->where(function ($q) use ($extraIds) {
                $q->whereHas('roles', fn ($r) => $r->whereIn('name', ['teacher', 'class_teacher', 'head_of_department']));
                if ($extraIds->isNotEmpty()) {
                    $q->orWhereIn('id', $extraIds->all());
                }
            })
            ->orderBy('name')->get(['id', 'name'])->unique('id')->values();
    }
}
