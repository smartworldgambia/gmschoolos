<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\AcademicRuleException;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
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
}
