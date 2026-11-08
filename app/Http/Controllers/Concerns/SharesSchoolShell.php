<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AcademicYear;
use App\Support\SchoolNav;
use App\Support\SchoolTypes;
use App\Support\TenantContext;
use Illuminate\Support\Facades\Storage;

trait SharesSchoolShell
{
    protected function shellProps(): array
    {
        $user = auth()->user();
        $tenant = app(TenantContext::class);

        if ($user?->school_id && ! $tenant->has()) {
            $tenant->set($user->school);
        }

        $school = $tenant->school() ?? $user?->school;
        $year = null;

        if ($school) {
            $year = AcademicYear::query()
                ->where('school_id', $school->id)
                ->where('is_current', true)
                ->first();
        }

        return [
            'finTabs' => $user && request()->routeIs('fees.*') ? \App\Services\Finance\FinanceAccess::tabs($user) : [],
            'hrTabs' => $user && request()->routeIs('hr.*', 'my.*') ? \App\Services\Hr\HrAccess::tabs($user) : [],
            'procTabs' => $user && request()->routeIs('procurement.*') ? \App\Services\Procurement\ProcurementAccess::tabs($user) : [],
            'invTabs' => $user && request()->routeIs('inventory.*') ? \App\Services\Inventory\InventoryAccess::tabs($user) : [],
            'nav' => $user ? SchoolNav::for($user) : [],
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
            ] : null,
            'school' => $school ? [
                'id' => $school->id,
                'name' => $school->name,
                'slug' => $school->slug,
                'category' => SchoolTypes::normalise($school->category),
                'primary_color' => $school->primary_color ?: '#082B50',
                'accent_color' => $school->accent_color ?: '#08B7A5',
                'logo_url' => $school->logo_path ? Storage::disk('public')->url($school->logo_path) : null,
            ] : null,
            'academicYear' => $year?->name,
            'badges' => $user && $school ? [
                'messages' => app(\App\Services\Messaging\MessagingService::class)->unreadCount($user),
                'announcements' => app(\App\Services\Communications\AnnouncementService::class)->unreadCount($user),
                'notifications' => $user->unreadNotifications()->count(),
            ] : (object) [],
        ];
    }
}
