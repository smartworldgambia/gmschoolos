<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): RedirectResponse|Response
    {
        $user = auth()->user();

        if ($user->isPlatformAdmin()) {
            return redirect()->route('platform.dashboard');
        }

        if ($user->can('portal.admin')) {
            // A school that has just registered continues in the setup wizard until it is finished.
            $school = $user->school;
            if ($school && $school->terms_accepted_at !== null && $school->setup_completed_at === null
                && $user->can('school.settings.manage')) {
                return redirect()->route('setup.index');
            }

            return redirect()->route('admin.dashboard');
        }

        if ($user->can('portal.teacher')) {
            return redirect()->route('teacher.dashboard');
        }

        if ($user->can('portal.student')) {
            return redirect()->route('student.dashboard');
        }

        if ($user->can('portal.parent')) {
            return redirect()->route('parent.dashboard');
        }

        return Inertia::render('Dashboard', [
            'home' => [
                ['key' => 'admin', 'href' => route('admin.dashboard')],
                ['key' => 'teacher', 'href' => route('teacher.dashboard')],
                ['key' => 'student', 'href' => route('student.dashboard')],
                ['key' => 'parent', 'href' => route('parent.dashboard')],
            ],
        ]);
    }
}
