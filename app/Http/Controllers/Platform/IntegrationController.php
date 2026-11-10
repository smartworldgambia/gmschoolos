<?php

namespace App\Http\Controllers\Platform;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\SharesPlatformShell;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Integrations\IntegrationSettings;
use App\Services\Sms\PhoneNumber;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform console: outgoing mail (SMTP) and SMS provider credentials. One set for the whole platform; schools never see
 * or hold them. Every change needs the administrator's own password again, secrets are write-only, and credentials only
 * go live after a successful test of exactly those values.
 */
class IntegrationController extends Controller
{
    use SharesPlatformShell;

    /** Error codes, translated by the page (`err.integ.*`). */
    private const MESSAGES = [
        'required' => 'err.integ.required', 'string' => 'err.integ.invalid', 'max' => 'err.integ.tooLong', 'email' => 'err.integ.email',
        'integer' => 'err.integ.port', 'between' => 'err.integ.port', 'in' => 'err.integ.invalid', 'regex' => 'err.integ.invalid',
        'url' => 'err.integ.url', 'current_password' => 'err.integ.password',
    ];

    public function __construct(protected IntegrationSettings $integrations) {}

    public function index(Request $request): Response
    {
        $items = [];
        foreach (array_keys(IntegrationSettings::KEYS) as $key) {
            $items[$key] = $this->integrations->status($key) + ['server' => $this->integrations->serverDefault($key)];
        }

        $activity = app(TenantContext::class)->runAsPlatform(fn () => ActivityLog::query()->with('user:id,name')
            ->where('event', 'like', 'platform.integration.%')->latest('id')->limit(15)->get()
            ->map(fn (ActivityLog $a) => [
                'id' => $a->id,
                'integration' => (string) data_get($a->properties, 'integration'),
                'event' => str_replace('platform.integration.', '', $a->event),
                'fields' => (array) data_get($a->properties, 'fields', []),
                'by' => $a->user?->name,
                'at' => $a->created_at?->toIso8601String(),
            ])->all());

        return Inertia::render('Platform/Integrations/Index', array_merge($this->platformShellProps(), [
            'items' => $items,
            'webhooks' => [
                'twilio' => url('/webhooks/sms/twilio'),
                'africastalking' => url('/webhooks/sms/africastalking/{secret}'),
            ],
            'testDefaults' => ['email' => $request->user()->email, 'phone' => $request->user()->phone],
            'activity' => $activity,
        ]));
    }

    public function save(Request $request, string $integration): RedirectResponse
    {
        $this->password($request);
        $data = $this->validated($request, $integration);

        $result = $this->integrations->save($integration, $data, $request->user());

        return $this->flashed($integration, $result['deactivated'] ? 'ok.integ.savedOff' : 'ok.integ.saved');
    }

    public function test(Request $request, string $integration): RedirectResponse
    {
        $key = $integration === 'mail' ? 'email' : 'phone';
        $request->validate([
            'target' => $integration === 'mail' ? ['required', 'email', 'max:190'] : ['required', 'string', 'max:30'],
        ], self::MESSAGES);

        if ($integration === 'sms' && PhoneNumber::normalise((string) $request->input('target')) === null) {
            throw ValidationException::withMessages(['target' => 'err.integ.phone']);
        }

        try {
            $result = $this->integrations->test($integration, (string) $request->input('target'), $request->user());
        } catch (AcademicRuleException $e) {
            return $this->flashed($integration, $e->getMessage(), error: true);
        }

        return $result['ok']
            ? $this->flashed($integration, $key === 'email' ? 'ok.integ.testSentMail' : 'ok.integ.testSentSms')
            : $this->flashed($integration, 'err.integ.testFailed', error: true);
    }

    public function activate(Request $request, string $integration): RedirectResponse
    {
        $this->password($request);

        return $this->guarded($integration, fn () => $this->integrations->activate($integration, $request->user()), 'ok.integ.activated');
    }

    public function deactivate(Request $request, string $integration): RedirectResponse
    {
        $this->password($request);

        return $this->guarded($integration, fn () => $this->integrations->deactivate($integration, $request->user()), 'ok.integ.deactivated');
    }

    public function confirm(Request $request, string $integration): RedirectResponse
    {
        return $this->guarded($integration, fn () => $this->integrations->confirm($integration, $request->user()), 'ok.integ.confirmed');
    }

    public function destroy(Request $request, string $integration): RedirectResponse
    {
        $this->password($request);

        return $this->guarded($integration, fn () => $this->integrations->clear($integration, $request->user()), 'ok.integ.cleared');
    }

    // ------------------------------------------------------------------

    private function guarded(string $integration, callable $fn, string $ok): RedirectResponse
    {
        try {
            $fn();
        } catch (AcademicRuleException $e) {
            return $this->flashed($integration, $e->getMessage(), error: true);
        }

        return $this->flashed($integration, $ok);
    }

    private function flashed(string $integration, string $message, bool $error = false): RedirectResponse
    {
        return back()->with($error ? 'error' : 'success', $message)->with('integration', $integration);
    }

    /** Every change that can redirect mail or SMS needs the administrator's own password again. */
    private function password(Request $request): void
    {
        Validator::make($request->all(), ['current_password' => ['required', 'string', 'current_password']], self::MESSAGES)->validate();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $integration): array
    {
        if ($integration === 'mail') {
            $data = Validator::make($request->all(), [
                'host' => ['required', 'string', 'max:190', 'regex:/^[A-Za-z0-9.\-]+$/'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'encryption' => ['required', 'in:tls,ssl'],
                'username' => ['nullable', 'string', 'max:190'],
                'password' => ['nullable', 'string', 'max:500'],
                'from_address' => ['required', 'email', 'max:190'],
                'from_name' => ['required', 'string', 'max:120'],
            ], self::MESSAGES)->validate();

            return $data;
        }

        $data = Validator::make($request->all(), [
            'driver' => ['required', 'in:twilio,africastalking'],
            'twilio_sid' => ['nullable', 'string', 'max:190'],
            'twilio_token' => ['nullable', 'string', 'max:500'],
            'twilio_from' => ['nullable', 'string', 'max:40'],
            'twilio_messaging_service_sid' => ['nullable', 'string', 'max:190'],
            'twilio_status_callback' => ['nullable', 'url', 'max:300'],
            'at_username' => ['nullable', 'string', 'max:190'],
            'at_api_key' => ['nullable', 'string', 'max:500'],
            'at_sender_id' => ['nullable', 'string', 'max:40'],
            'at_sandbox' => ['nullable', 'boolean'],
            'at_webhook_secret' => ['nullable', 'string', 'max:190', 'regex:/^[A-Za-z0-9_\-]*$/'],
        ], self::MESSAGES)->validate();

        // The chosen provider must be complete. A secret left blank is acceptable only when one is already stored.
        $stored = $this->integrations->plain('sms') ?? [];
        $need = fn (string $field, bool $secret = false) => blank($data[$field] ?? null) && ! ($secret && filled($stored[$field] ?? null));

        $missing = [];
        if ($data['driver'] === 'twilio') {
            $need('twilio_sid') && $missing[] = 'twilio_sid';
            $need('twilio_token', true) && $missing[] = 'twilio_token';
            if (blank($data['twilio_from'] ?? null) && blank($data['twilio_messaging_service_sid'] ?? null)) {
                $missing[] = 'twilio_from';
            }
        } else {
            $need('at_username') && $missing[] = 'at_username';
            $need('at_api_key', true) && $missing[] = 'at_api_key';
        }

        if ($missing) {
            throw ValidationException::withMessages(array_fill_keys($missing, 'err.integ.required'));
        }

        return $data;
    }
}
