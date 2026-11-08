<?php

namespace App\Http\Controllers\Communications;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\NotificationDelivery;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\Communications\CommunicationSettings;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Communications centre for administrators (communications.manage): SMS switch and provider state, message templates,
 * the SMS log with retry, a test message, and the e-mail / in-app delivery history.
 *
 * Honesty rule: the page says what the provider state is. `log` means nothing leaves the server; "sent" means the provider
 * accepted the message; only a provider delivery report makes it "delivered".
 */
class CommunicationsController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    public function index(Request $request, SmsService $sms, SmsTemplates $templates): Response
    {
        $school = $request->user()->school;
        $tab = in_array($request->query('tab'), ['settings', 'templates', 'sms', 'deliveries'], true) ? $request->query('tab') : 'settings';
        $status = in_array($request->query('status'), ['queued', 'sent', 'delivered', 'failed', 'logged'], true) ? $request->query('status') : null;

        $since = now()->subDays(30);
        $counts = SmsMessage::query()->where('created_at', '>=', $since)->selectRaw('status, count(*) as n, coalesce(sum(cost_amount),0) as cost, sum(segments) as segments')
            ->groupBy('status')->get()->keyBy('status');

        $smsLog = $tab === 'sms'
            ? SmsMessage::query()->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(20)->withQueryString()
                ->through(fn (SmsMessage $m) => [
                    'id' => $m->id, 'to' => $m->maskedPhone(), 'template' => $m->template_key, 'category' => $m->category, 'status' => $m->status,
                    'segments' => $m->segments, 'attempts' => $m->attempts, 'error' => $m->last_error, 'provider' => $m->provider,
                    'cost' => $m->cost_amount !== null ? (string) $m->cost_amount : null, 'currency' => $m->cost_currency,
                    'at' => $m->created_at?->toIso8601String(), 'can_retry' => $m->status === 'failed' && $m->last_error !== 'invalid_phone',
                ])
            : null;

        $deliveries = $tab === 'deliveries'
            ? NotificationDelivery::query()->with('user:id,name')->latest('id')->paginate(25)->withQueryString()
                ->through(fn (NotificationDelivery $d) => [
                    'id' => $d->id, 'user' => $d->user?->name, 'channel' => $d->channel, 'status' => $d->status, 'category' => $d->category,
                    'summary' => $d->summary, 'error' => $d->error, 'at' => $d->created_at?->toIso8601String(),
                ])
            : null;

        $own = SmsTemplate::query()->get()->groupBy('key');
        $tpl = collect($templates->keys())->map(fn (string $key) => [
            'key' => $key,
            'locales' => collect(SmsTemplates::LOCALES)->map(fn (string $l) => [
                'locale' => $l,
                'default' => (string) data_get(config("schoolos.sms.templates.{$key}"), $l, ''),
                'custom' => (string) ($own->get($key)?->firstWhere('locale', $l)?->body ?? ''),
            ])->values(),
        ])->values();

        return Inertia::render('Communications/Index', array_merge($this->shellProps(), [
            'tab' => $tab,
            'settings' => CommunicationSettings::all($school),
            'provider' => $sms->providerState(),
            'stats' => [
                'sent' => (int) ($counts->get('sent')?->n ?? 0), 'delivered' => (int) ($counts->get('delivered')?->n ?? 0),
                'failed' => (int) ($counts->get('failed')?->n ?? 0), 'logged' => (int) ($counts->get('logged')?->n ?? 0),
                'queued' => (int) ($counts->get('queued')?->n ?? 0),
                'segments' => (int) $counts->sum('segments'), 'cost' => round((float) $counts->sum('cost'), 4),
            ],
            'templates' => $tpl,
            'smsLog' => $smsLog,
            'deliveries' => $deliveries,
            'filters' => ['status' => $status],
            'callbacks' => [
                'twilio' => url('/webhooks/sms/twilio'),
                'africastalking' => url('/webhooks/sms/africastalking/{secret}'),
            ],
        ]));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sms_enabled' => ['required', 'boolean'],
            'sms_sender' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9 ]+$/'],
            'sms_daily_cap' => ['required', 'integer', 'min:0', 'max:20000'],
            'parent_messaging' => ['required', 'boolean'],
            'student_messaging' => ['required', 'boolean'],
        ]);

        $school = $request->user()->school;
        CommunicationSettings::save($school, [
            'sms_enabled' => (bool) $data['sms_enabled'], 'sms_sender' => filled($data['sms_sender'] ?? null) ? trim($data['sms_sender']) : null,
            'sms_daily_cap' => (int) $data['sms_daily_cap'], 'parent_messaging' => (bool) $data['parent_messaging'],
            'student_messaging' => (bool) $data['student_messaging'],
        ]);

        ActivityLog::query()->create(['school_id' => $school->id, 'user_id' => $request->user()->id, 'event' => 'communications.settings_saved',
            'description' => 'sms '.($data['sms_enabled'] ? 'on' : 'off'), 'properties' => ['sms_enabled' => (bool) $data['sms_enabled'], 'cap' => (int) $data['sms_daily_cap']]]);

        return back()->with('success', 'ok.saved');
    }

    public function saveTemplate(Request $request, SmsTemplates $templates): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'in:'.implode(',', $templates->keys())],
            'locale' => ['required', 'string', 'in:'.implode(',', SmsTemplates::LOCALES)],
            'body' => ['nullable', 'string', 'max:480'],
        ]);

        $existing = SmsTemplate::query()->where('key', $data['key'])->where('locale', $data['locale'])->first();

        if (blank($data['body'] ?? null)) {
            $existing?->delete();                       // empty text = back to the platform default

            return back()->with('success', 'ok.saved');
        }

        // No control characters: a template must stay one single text message
        $body = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $data['body']));
        SmsTemplate::query()->updateOrCreate(
            ['school_id' => $request->user()->school_id, 'key' => $data['key'], 'locale' => $data['locale']],
            ['body' => $body, 'active' => true],
        );

        return back()->with('success', 'ok.saved');
    }

    public function test(Request $request, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);
        $school = $request->user()->school;

        return $this->attempt(function () use ($sms, $school, $data, $request) {
            if (! CommunicationSettings::smsEnabled($school)) {
                throw AcademicRuleException::because('err.sms.disabled');
            }
            if (PhoneNumber::normalise($data['phone']) === null) {
                throw AcademicRuleException::because('err.sms.badPhone');
            }

            $sms->queueTemplate($school, $request->user(), $data['phone'], 'test', [], 'account');

            return back()->with('success', 'ok.sms.testQueued');
        });
    }

    public function retry(Request $request, int $message, SmsService $sms): RedirectResponse
    {
        $m = SmsMessage::query()->findOrFail($message);

        return $this->attempt(function () use ($sms, $m) {
            if ($m->status !== 'failed') {
                throw AcademicRuleException::because('err.sms.notFailed');
            }
            $sms->retry($m);
        }, 'ok.sms.retried');
    }
}
