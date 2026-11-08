<?php

namespace App\Http\Controllers\Notifications;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\NotificationDelivery;
use App\Services\Communications\CommunicationSettings;
use App\Services\Notifications\NotificationPreferences;
use App\Services\Sms\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in person's notification centre: the in-app list, their channel preferences per category, the phone number
 * used for SMS and the history of what was delivered to them. Only ever the caller's own rows.
 */
class NotificationController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $category = $request->query('category');
        $category = is_string($category) && array_key_exists($category, (array) config('schoolos.notifications.categories')) ? $category : null;
        $unreadOnly = $request->boolean('unread');

        $page = $user->notifications()
            ->when($unreadOnly, fn ($q) => $q->whereNull('read_at'))
            ->when($category, fn ($q) => $q->where('data', 'like', '%"category":"'.$category.'"%'))
            ->latest()->paginate(25)->withQueryString()
            ->through(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? class_basename($n->type),
                'body' => $n->data['body'] ?? '',
                'action_url' => $n->data['action_url'] ?? null,
                'level' => $n->data['level'] ?? 'info',
                'category' => $n->data['category'] ?? null,
                'read_at' => $n->read_at?->toDateTimeString(),
                'created_at' => $n->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Index', array_merge($this->shellProps(), [
            'notifications' => $page,
            'unreadCount' => $user->unreadNotifications()->count(),
            'filters' => ['category' => $category, 'unread' => $unreadOnly],
            'categories' => array_keys((array) config('schoolos.notifications.categories')),
        ]));
    }

    public function markRead(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->where('id', $notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'ok.notif.allRead');
    }

    public function preferences(Request $request, NotificationPreferences $prefs): Response
    {
        $user = $request->user();
        $school = $user->school;

        $history = NotificationDelivery::query()->where('user_id', $user->id)->latest('id')->limit(40)->get()
            ->map(fn (NotificationDelivery $d) => [
                'id' => $d->id, 'channel' => $d->channel, 'status' => $d->status, 'category' => $d->category, 'summary' => $d->summary,
                'at' => $d->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Preferences', array_merge($this->shellProps(), [
            'matrix' => $prefs->matrix($user),
            'phone' => $user->phone,
            'phoneValid' => PhoneNumber::normalise($user->phone) !== null,
            'emailVerified' => $user->email_verified_at !== null,
            'smsEnabled' => $school ? CommunicationSettings::smsEnabled($school) : false,
            'history' => $history,
        ]));
    }

    public function savePreferences(Request $request, NotificationPreferences $prefs): RedirectResponse
    {
        $data = $request->validate([
            'prefs' => ['required', 'array'],
            'prefs.*' => ['array'],
            'prefs.*.in_app' => ['sometimes', 'boolean'],
            'prefs.*.email' => ['sometimes', 'boolean'],
            'prefs.*.sms' => ['sometimes', 'boolean'],
        ]);

        $prefs->save($request->user(), $data['prefs']);

        return back()->with('success', 'ok.saved');
    }

    public function savePhone(Request $request): RedirectResponse
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30']]);

        return $this->attempt(function () use ($request, $data) {
            $phone = trim((string) ($data['phone'] ?? ''));
            if ($phone !== '' && PhoneNumber::normalise($phone) === null) {
                throw AcademicRuleException::because('err.sms.badPhone');
            }

            $request->user()->forceFill(['phone' => $phone === '' ? null : $phone])->save();
        });
    }
}
