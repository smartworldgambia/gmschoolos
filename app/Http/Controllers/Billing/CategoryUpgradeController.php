<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\SchoolCategoryUpgradeRequest;
use App\Services\School\CategoryUpgradeService;
use App\Support\SchoolCategoryCatalog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryUpgradeController extends Controller
{
    public function store(Request $request, CategoryUpgradeService $upgrades, TenantContext $tenant): RedirectResponse
    {
        $school = $tenant->school() ?? $request->user()->school;
        abort_unless($school, 404);

        $validated = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'string', 'in:'.implode(',', SchoolCategoryCatalog::EXCLUSIVE)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $upgrades->request(
            $school,
            $request->user(),
            $validated['categories'],
            $validated['reason'] ?? null
        );

        return back()->with('success', 'Category upgrade request submitted for platform review.');
    }

    public function cancel(Request $request, CategoryUpgradeService $upgrades, TenantContext $tenant): RedirectResponse
    {
        $school = $tenant->school() ?? $request->user()->school;
        abort_unless($school, 404);

        $pending = SchoolCategoryUpgradeRequest::query()
            ->where('school_id', $school->id)
            ->where('status', SchoolCategoryUpgradeRequest::PENDING)
            ->latest('id')
            ->firstOrFail();

        $upgrades->cancel($pending, $request->user());

        return back()->with('success', 'Category upgrade request cancelled.');
    }
}
