<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolCategoryUpgradeRequest;
use App\Services\School\CategoryUpgradeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CategoryUpgradeController extends Controller
{
    public function approve(
        Request $request,
        School $school,
        SchoolCategoryUpgradeRequest $upgrade,
        CategoryUpgradeService $upgrades
    ): RedirectResponse {
        abort_unless((int) $upgrade->school_id === (int) $school->id, 404);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $upgrades->approve($upgrade, $request->user(), $validated['review_note'] ?? null);

        return back()->with('success', 'Category upgrade approved. Levels and billing type updated.');
    }

    public function reject(
        Request $request,
        School $school,
        SchoolCategoryUpgradeRequest $upgrade,
        CategoryUpgradeService $upgrades
    ): RedirectResponse {
        abort_unless((int) $upgrade->school_id === (int) $school->id, 404);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $upgrades->reject($upgrade, $request->user(), $validated['review_note'] ?? null);

        return back()->with('success', 'Category upgrade rejected.');
    }
}
