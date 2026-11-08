<?php

namespace App\Services\Reporting;

use App\Models\User;

class ReportingAccess
{
    public static function tabs(User $user): array
    {
        if ($user->isFamilyPortal()) {
            return [
                ['key' => 'dashboard', 'route' => 'reporting.index'],
            ];
        }

        $tabs = [
            ['key' => 'dashboard', 'route' => 'reporting.index'],
            ['key' => 'termly', 'route' => 'reporting.termly'],
            ['key' => 'annual', 'route' => 'reporting.annual'],
            ['key' => 'transcripts', 'route' => 'reporting.transcripts'],
            ['key' => 'final', 'route' => 'reporting.final'],
        ];
        if ($user->can('exams.manage') || $user->can('results.publish')) {
            $tabs[] = ['key' => 'approval', 'route' => 'reporting.approval'];
            $tabs[] = ['key' => 'register', 'route' => 'reporting.register'];
        }
        if ($user->can('exams.manage')) {
            $tabs[] = ['key' => 'templates', 'route' => 'reporting.templates'];
            $tabs[] = ['key' => 'grading', 'route' => 'reporting.grading'];
        }

        return $tabs;
    }

    public static function can(User $user): array
    {
        $family = $user->isFamilyPortal();

        return [
            'prepare' => ! $family && ($user->can('exams.mark') || $user->can('exams.manage')),
            'approve' => ! $family && ($user->can('exams.manage') || $user->can('results.publish')),
            'publish' => ! $family && $user->can('results.publish'),
            'configure' => ! $family && $user->can('exams.manage'),
            'transcripts' => $user->can('records.manage') || $user->can('exams.view') || $user->can('exams.manage'),
            'recordsDraft' => $user->can('records.manage'),
            'family' => $family,
        ];
    }
}
