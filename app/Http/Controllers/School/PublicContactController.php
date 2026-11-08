<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolInquiry;
use App\Models\User;
use App\Notifications\SchoolInquiryReceived;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PublicContactController extends Controller
{
    public function store(Request $request, TenantContext $tenant, string $school_slug): RedirectResponse
    {
        $school = $tenant->school() ?? School::query()->where('slug', $school_slug)->first();

        if (! $school || ! $school->isActive() || ! $school->website_published) {
            throw new NotFoundHttpException('School website is not available.');
        }

        // Honeypot: real visitors never fill this. Pretend success so bots learn nothing.
        if (filled($request->input('company_website'))) {
            return back()->with('success', 'Message sent.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $inquiry = SchoolInquiry::query()->create([
            ...$validated,
            'school_id' => $school->id,
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        $admins = User::query()
            ->withoutGlobalScopes()
            ->where('school_id', $school->id)
            ->role('school_admin')
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SchoolInquiryReceived($inquiry));
        }

        $mailTo = filled($school->email)
            ? [$school->email]
            : $admins->pluck('email')->filter()->unique()->all();

        if ($mailTo !== []) {
            Notification::route('mail', $mailTo)->notify(new SchoolInquiryReceived($inquiry));
        }

        return back()->with('success', 'Message sent.');
    }
}
