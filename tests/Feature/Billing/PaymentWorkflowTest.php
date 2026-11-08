<?php

namespace Tests\Feature\Billing;

use App\Models\BillingEvent;
use App\Models\BillingNotificationLog;
use App\Models\BillingPayment;
use App\Models\BillingWebhookEvent;
use App\Models\PlatformBankAccount;
use App\Models\School;
use App\Models\SchoolSubscription;
use App\Models\SchoolTypePrice;
use App\Models\User;
use App\Services\Billing\PaymentReconciler;
use App\Services\Billing\PaymentService;
use App\Services\Billing\PricingService;
use App\Services\Billing\SubscriptionLifecycle;
use App\Support\Currency;
use App\Support\TenantContext;
use Database\Seeders\RolesAndPlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3 acceptance: cash, bank transfer, Wave and Stripe payment workflows.
 *
 * Gateway HTTP is faked (Http::fake) and webhooks are signed locally with test secrets. These tests prove OUR side
 * of each integration. They are NOT a substitute for sandbox transactions with real Stripe / Wave accounts.
 */
class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const STRIPE_SECRET = 'whsec_test_secret';

    private const WAVE_SECRET = 'wave_sn_WHS_test_secret';

    protected School $school;

    protected User $admin;

    /** @var array<string, mixed> what the faked Stripe "retrieve session" call returns */
    protected array $stripeSession = [];

    /** @var array<string, mixed> what the faked Wave "retrieve checkout" call returns */
    protected array $waveSession = [];

    protected bool $providerDown = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPlansSeeder::class);

        SchoolTypePrice::query()->where('school_type', 'primary')->delete();
        app(PricingService::class)->set('primary', 20000, 'GMD', '2000-01-01', 0.0, 'test price');

        $this->school = School::query()->create([
            'name' => 'Pay School', 'slug' => 'pay-school', 'status' => 'active', 'category' => 'Primary',
        ]);
        $this->admin = $this->userFor($this->school, 'school_admin');
        app(SubscriptionLifecycle::class)->startTrial($this->school);

        Storage::fake('local');

        config([
            'schoolos.billing.payment_methods.cash.enabled' => true,
            'schoolos.billing.payment_methods.bank_transfer.enabled' => true,
            'schoolos.billing.payment_methods.stripe.enabled' => true,
            'schoolos.billing.payment_methods.wave.enabled' => true,
            'schoolos.billing.stripe.secret_key' => 'sk_test_dummy',
            'schoolos.billing.stripe.webhook_secret' => self::STRIPE_SECRET,
            'schoolos.billing.stripe.currency' => 'USD',
            'schoolos.billing.stripe.fx_rate' => 0.0145,
            'schoolos.billing.wave.api_key' => 'wave_sn_prod_dummy',
            'schoolos.billing.wave.webhook_secret' => self::WAVE_SECRET,
            'schoolos.billing.wave.webhook_strategy' => 'signing',
            'schoolos.billing.wave.request_signing_secret' => null,
            'schoolos.billing.wave.currency' => 'XOF',
            'schoolos.billing.wave.fx_rate' => 8.5,
        ]);

        Http::fake(fn ($request) => $this->fakeProviders($request));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    protected function userFor(?School $school, ?string $role): User
    {
        $user = User::factory()->create(['school_id' => $school?->id, 'email_verified_at' => now()]);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    protected function as(User $user): static
    {
        app(TenantContext::class)->clear();

        return $this->actingAs($user);
    }

    protected function platform(string $role = 'platform_finance'): User
    {
        return $this->userFor(null, $role);
    }

    protected function sub(): SchoolSubscription
    {
        app(TenantContext::class)->clear();

        return app(TenantContext::class)->runAsPlatform(fn () => SchoolSubscription::query()->where('school_id', $this->school->id)->firstOrFail());
    }

    protected function payment(?string $reference = null): BillingPayment
    {
        app(TenantContext::class)->clear();

        return app(TenantContext::class)->runAsPlatform(fn () => $reference
            ? BillingPayment::query()->where('reference', $reference)->firstOrFail()
            : BillingPayment::query()->latest('id')->firstOrFail());
    }

    protected function events(BillingPayment $p): array
    {
        app(TenantContext::class)->clear();

        return app(TenantContext::class)->runAsPlatform(fn () => BillingEvent::query()
            ->where('billing_payment_id', $p->id)->orderBy('id')->pluck('type')->all());
    }

    protected function bank(): PlatformBankAccount
    {
        return PlatformBankAccount::query()->create([
            'bank_name' => 'Test Bank', 'account_name' => 'Gambia SchoolOS Ltd', 'account_number' => '0011223344',
            'branch' => 'Kairaba', 'currency' => 'GMD', 'is_active' => true,
        ]);
    }

    protected function start(string $method, ?User $as = null, array $extra = []): BillingPayment
    {
        $this->as($as ?? $this->admin)->post(route('billing.payments.store'), ['method' => $method] + $extra);

        return $this->payment();
    }

    protected function startStripe(): BillingPayment
    {
        $payment = $this->start('stripe', null, ['expected_charge' => 29000]);
        $this->stripeSession = $this->stripePaidSession($payment);

        return $payment;
    }

    protected function startWave(): BillingPayment
    {
        $payment = $this->start('wave', null, ['expected_charge' => 170000]);
        $this->waveSession = $this->waveSucceededSession($payment);

        return $payment;
    }

    // ---- provider fakes

    protected function fakeProviders($request)
    {
        $url = $request->url();

        if (str_starts_with($url, 'https://api.stripe.com/')) {
            if ($this->providerDown) {
                throw new \Illuminate\Http\Client\ConnectionException('Stripe down');
            }
            if ($request->method() === 'POST' && str_ends_with($url, '/v1/checkout/sessions')) {
                return Http::response(['id' => 'cs_test_123', 'url' => 'https://checkout.stripe.com/c/pay/cs_test_123', 'expires_at' => time() + 3600]);
            }
            if ($request->method() === 'POST') {
                return Http::response(['id' => 'cs_test_123', 'status' => 'expired']);
            }

            return Http::response($this->stripeSession);
        }

        if (str_starts_with($url, 'https://api.wave.com/')) {
            if ($this->providerDown) {
                throw new \Illuminate\Http\Client\ConnectionException('Wave down');
            }
            if ($request->method() === 'POST' && str_ends_with($url, '/v1/checkout/sessions')) {
                return Http::response(['id' => 'cos-test-1', 'wave_launch_url' => 'https://pay.wave.com/c/cos-test-1', 'when_expires' => now()->addMinutes(30)->toIso8601String()]);
            }
            if ($request->method() === 'POST') {
                return Http::response([]);
            }

            return Http::response($this->waveSession);
        }

        return Http::response([], 500);
    }

    /** @return array<string, mixed> */
    protected function stripePaidSession(BillingPayment $p, array $override = []): array
    {
        return array_merge([
            'id' => 'cs_test_123', 'status' => 'complete', 'payment_status' => 'paid',
            'amount_total' => (int) $p->charged_amount, 'currency' => 'usd',
            'client_reference_id' => $p->reference, 'payment_intent' => 'pi_test_123',
        ], $override);
    }

    /** @return array<string, mixed> */
    protected function waveSucceededSession(BillingPayment $p, array $override = []): array
    {
        return array_merge([
            'id' => 'cos-test-1', 'amount' => (string) $p->charged_amount, 'currency' => 'XOF',
            'checkout_status' => 'complete', 'payment_status' => 'succeeded', 'client_reference' => $p->reference,
            'transaction_id' => 'TWAVE123', 'last_payment_error' => null,
        ], $override);
    }

    // ---- webhook helpers

    protected function stripeEvent(string $type, array $object, string $id = 'evt_1'): string
    {
        return json_encode(['id' => $id, 'type' => $type, 'data' => ['object' => $object]]);
    }

    protected function postStripe(string $body, ?string $signature = null, ?int $timestamp = null)
    {
        $ts = $timestamp ?? time();
        $signature ??= 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$body, self::STRIPE_SECRET);

        return $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $body);
    }

    protected function waveEvent(string $type, string $sessionId = 'cos-test-1', string $id = 'AE_1'): string
    {
        return json_encode(['id' => $id, 'type' => $type, 'data' => ['id' => $sessionId]]);
    }

    protected function postWave(string $body, ?string $signature = null, ?int $timestamp = null, array $server = [])
    {
        $ts = $timestamp ?? time();
        $signature ??= 't='.$ts.',v1='.hash_hmac('sha256', $ts.$body, self::WAVE_SECRET);

        return $this->call('POST', '/webhooks/wave', [], [], [], array_merge([
            'CONTENT_TYPE' => 'application/json', 'HTTP_WAVE_SIGNATURE' => $signature,
        ], $server), $body);
    }

    // ================================================================== CASH

    public function test_school_generates_a_cash_reference_and_nothing_activates(): void
    {
        $payment = $this->start('cash');

        $this->assertSame('pending', $payment->status);
        $this->assertSame('cash', $payment->method);
        $this->assertStringStartsWith('SOS-'.$this->school->id.'-', $payment->reference);
        $this->assertSame(20000, $payment->total_amount);
        $this->assertNull($payment->receipt_number);
        $this->assertSame('trial', $this->sub()->status);

        // clicking again returns the SAME open payment - never a second one
        $again = $this->start('cash');
        $this->assertSame($payment->id, $again->id);
        $this->assertSame(1, BillingPayment::withoutGlobalScopes()->count());
    }

    public function test_cash_confirmation_by_authorised_officer_activates_and_issues_receipt(): void
    {
        $payment = $this->start('cash');
        $officer = $this->platform();

        $this->as($officer)->post(route('platform.payments.cash.confirm', $payment->id), [
            'amount' => 20000, 'paid_at' => now()->toDateString(), 'receipt_number' => 'BOOK-0042', 'notes' => 'Paid at head office',
        ])->assertSessionHasNoErrors();

        $payment = $this->payment($payment->reference);
        $this->assertSame('verified', $payment->status);
        $this->assertSame('BOOK-0042', $payment->receipt_number);
        $this->assertSame($officer->id, $payment->verified_by);
        $this->assertSame('active', $this->sub()->status);
        $this->assertSame(
            $payment->period_start->copy()->addYearNoOverflow()->toDateString(),
            $payment->period_end->toDateString(),
            'cash buys a 12-month period, never monthly instalments'
        );

        $this->assertSame(['payment_initiated', 'activated', 'payment_verified'], $this->events($payment));
    }

    public function test_partial_cash_payment_never_activates_and_is_audited(): void
    {
        $payment = $this->start('cash');

        $this->as($this->platform())->post(route('platform.payments.cash.confirm', $payment->id), [
            'amount' => 10000, 'paid_at' => now()->toDateString(),
        ])->assertSessionHasErrors('payment');

        $payment = $this->payment($payment->reference);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('trial', $this->sub()->status);
        $this->assertContains('payment_confirmation_refused', $this->events($payment));

        // excess is refused as well - it is not "instalments" and not an unbounded credit
        $this->as($this->platform())->post(route('platform.payments.cash.confirm', $payment->id), [
            'amount' => 25000, 'paid_at' => now()->toDateString(),
        ])->assertSessionHasErrors('payment');
        $this->assertSame('trial', $this->sub()->status);
    }

    public function test_only_authorised_finance_roles_can_confirm_cash(): void
    {
        $payment = $this->start('cash');
        $body = ['amount' => 20000, 'paid_at' => now()->toDateString()];

        $this->as($this->admin)->post(route('platform.payments.cash.confirm', $payment->id), $body)->assertForbidden();
        $this->as($this->platform('platform_support'))->post(route('platform.payments.cash.confirm', $payment->id), $body)->assertForbidden();
        $this->as($this->platform('platform_auditor'))->post(route('platform.payments.cash.confirm', $payment->id), $body)->assertForbidden();

        $this->assertSame('pending', $this->payment($payment->reference)->status);
        $this->assertSame('trial', $this->sub()->status);
    }

    public function test_cash_receipt_number_cannot_be_reused(): void
    {
        $other = School::query()->create(['name' => 'Other', 'slug' => 'other-school', 'status' => 'active', 'category' => 'Primary']);
        app(SubscriptionLifecycle::class)->startTrial($other);
        app(SubscriptionLifecycle::class)->recordVerifiedPayment($other, ['method' => 'cash', 'receipt_number' => 'BOOK-1']);

        $payment = $this->start('cash');
        $this->as($this->platform())->post(route('platform.payments.cash.confirm', $payment->id), [
            'amount' => 20000, 'paid_at' => now()->toDateString(), 'receipt_number' => 'BOOK-1',
        ])->assertSessionHasErrors('payment');

        $this->assertSame('trial', $this->sub()->status);
    }

    public function test_direct_office_recording_settles_the_open_cash_payment_instead_of_duplicating(): void
    {
        $payment = $this->start('cash');

        $this->as($this->platform())->post(route('platform.schools.payments.store', $this->school), ['method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, BillingPayment::withoutGlobalScopes()->count());
        $this->assertSame('verified', $this->payment($payment->reference)->status);
    }

    public function test_receipts_render_for_the_school_and_platform_and_not_for_unpaid_payments(): void
    {
        $payment = $this->start('cash');
        $this->as($this->admin)->get(route('billing.payments.receipt', $payment->reference))->assertNotFound();

        $this->as($this->platform())->post(route('platform.payments.cash.confirm', $payment->id), [
            'amount' => 20000, 'paid_at' => now()->toDateString(),
        ]);
        $payment = $this->payment($payment->reference);

        $this->as($this->admin)->get(route('billing.payments.receipt', $payment->reference))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Receipt')
                ->where('receipt.receipt_number', $payment->receipt_number)
                ->where('receipt.school', 'Pay School')
                ->where('receipt.total_label', 'GMD 20,000')
                ->where('receipt.method', 'cash')
                ->where('receipt.period_end', $payment->period_end->toDateString())
                ->where('receipt.verified_by', $payment->verifier->name));

        $this->as($this->platform())->get(route('platform.payments.receipt', $payment->id))->assertOk();
    }

    // ================================================================== BANK TRANSFER

    public function test_bank_transfer_is_unavailable_until_an_account_is_configured(): void
    {
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'bank_transfer'])->assertSessionHasErrors('payment');
        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count());

        $this->bank();
        $this->assertSame('pending', $this->start('bank_transfer')->status);
    }

    public function test_bank_flow_evidence_is_not_proof_and_never_activates(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');

        $this->as($this->admin)->get(route('billing.payments.show', $payment->reference))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Payment')
                ->has('bankAccounts', 1)
                ->where('bankAccounts.0.account_number', '0011223344')
                ->where('payment.reference', $payment->reference));

        $this->as($this->admin)->post(route('billing.payments.evidence', $payment->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 120, 'application/pdf'),
            'claimed_reference' => 'FT-1234', 'transferred_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $payment = $this->payment($payment->reference);
        $this->assertSame('pending_verification', $payment->status);
        $this->assertTrue($payment->hasEvidence());
        $this->assertStringStartsWith('billing-evidence/'.$this->school->id.'/', $payment->evidence_path);
        Storage::disk('local')->assertExists($payment->evidence_path);
        $this->assertNull($payment->receipt_number);
        $this->assertSame('trial', $this->sub()->status, 'an uploaded receipt must never activate the subscription');
        $this->assertSame('FT-1234', $payment->meta['claimed']['reference']);
    }

    public function test_evidence_must_be_a_real_pdf_or_image_within_the_size_limit(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');

        // A genuinely disguised file: plain text on disk, labelled "proof.png" / image/png by the client.
        // (UploadedFile::fake() would report the mime from the extension, which would not test content sniffing.)
        $disguised = tempnam(sys_get_temp_dir(), 'ev');
        file_put_contents($disguised, 'this is plain text, not a png');

        foreach ([
            'exe' => UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'),
            'php' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'disguised' => new UploadedFile($disguised, 'proof.png', 'image/png', null, true),
            'huge' => UploadedFile::fake()->create('huge.pdf', 9000, 'application/pdf'),
        ] as $case => $file) {
            $this->as($this->admin)->post(route('billing.payments.evidence', $payment->reference), ['evidence' => $file])
                ->assertSessionHasErrors(null, 'default', "case [{$case}] must be refused");
        }

        @unlink($disguised);

        $this->assertSame('pending', $this->payment($payment->reference)->status);
    }

    public function test_bank_approval_requires_the_real_statement_reference_and_the_full_amount(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');
        $this->as($this->admin)->post(route('billing.payments.evidence', $payment->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ]);
        $officer = $this->platform();
        $good = ['amount' => 20000, 'transaction_reference' => 'BANK-777', 'paid_at' => now()->toDateString(), 'confirm_funds' => '1'];

        // not confirmed against the statement
        $this->as($officer)->post(route('platform.payments.bank.approve', $payment->id), array_diff_key($good, ['confirm_funds' => 1]))
            ->assertSessionHasErrors('confirm_funds');
        // no bank transaction reference
        $this->as($officer)->post(route('platform.payments.bank.approve', $payment->id), array_diff_key($good, ['transaction_reference' => 1]))
            ->assertSessionHasErrors('transaction_reference');
        // partial amount found on the statement
        $this->as($officer)->post(route('platform.payments.bank.approve', $payment->id), ['amount' => 12000] + $good)
            ->assertSessionHasErrors('payment');
        $this->assertSame('pending_verification', $this->payment($payment->reference)->status);
        $this->assertSame('trial', $this->sub()->status);

        $this->as($officer)->post(route('platform.payments.bank.approve', $payment->id), $good)->assertSessionHasNoErrors();

        $payment = $this->payment($payment->reference);
        $this->assertSame('verified', $payment->status);
        $this->assertSame('BANK-777', $payment->transaction_reference);
        $this->assertNotNull($payment->receipt_number);
        $this->assertSame('active', $this->sub()->status);
        $this->assertContains('evidence_uploaded', $this->events($payment));
        $this->assertContains('payment_verified', $this->events($payment));
    }

    public function test_the_same_bank_transaction_cannot_pay_two_schools(): void
    {
        $this->bank();
        $first = $this->start('bank_transfer');
        $this->as($this->platform())->post(route('platform.payments.bank.approve', $first->id), [
            'amount' => 20000, 'transaction_reference' => 'BANK-DUP', 'paid_at' => now()->toDateString(), 'confirm_funds' => '1',
        ])->assertSessionHasNoErrors();

        $other = School::query()->create(['name' => 'Second', 'slug' => 'second-school', 'status' => 'active', 'category' => 'Primary']);
        app(SubscriptionLifecycle::class)->startTrial($other);
        $otherAdmin = $this->userFor($other, 'school_admin');
        $second = $this->start('bank_transfer', $otherAdmin);

        $this->as($this->platform())->post(route('platform.payments.bank.approve', $second->id), [
            'amount' => 20000, 'transaction_reference' => 'BANK-DUP', 'paid_at' => now()->toDateString(), 'confirm_funds' => '1',
        ])->assertSessionHasErrors('payment');

        $this->assertSame('pending', $this->payment($second->reference)->status);
    }

    public function test_rejection_needs_a_reason_notifies_the_school_and_changes_nothing(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');
        $this->as($this->admin)->post(route('billing.payments.evidence', $payment->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ]);

        $this->as($this->platform())->post(route('platform.payments.reject', $payment->id), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->as($this->platform())->post(route('platform.payments.reject', $payment->id), ['reason' => 'No matching credit on the statement'])
            ->assertSessionHasNoErrors();

        $payment = $this->payment($payment->reference);
        $this->assertSame('rejected', $payment->status);
        $this->assertSame('No matching credit on the statement', $payment->rejection_reason);
        $this->assertNotNull($payment->rejected_by);
        $this->assertSame('trial', $this->sub()->status);
        $this->assertTrue(app(TenantContext::class)->runAsPlatform(fn () => BillingNotificationLog::query()->where('kind', 'payment_rejected')->exists()));

        // the school can start a fresh payment afterwards
        $this->assertNotSame($payment->id, $this->start('cash')->id);
    }

    public function test_evidence_is_downloaded_only_by_finance_and_the_view_is_audited(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');
        $this->as($this->admin)->post(route('billing.payments.evidence', $payment->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ]);

        $this->as($this->admin)->get(route('platform.payments.evidence', $payment->id))->assertForbidden();
        $this->as($this->platform('platform_support'))->get(route('platform.payments.evidence', $payment->id))->assertForbidden();

        $response = $this->as($this->platform())->get(route('platform.payments.evidence', $payment->id));
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertContains('evidence_viewed', $this->events($payment));
    }

    public function test_pending_can_be_cancelled_but_not_once_under_verification(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');
        $this->as($this->admin)->post(route('billing.payments.evidence', $payment->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ]);
        $this->as($this->admin)->post(route('billing.payments.cancel', $payment->reference))->assertSessionHasErrors('payment');
        $this->assertSame('pending_verification', $this->payment($payment->reference)->status);

        $cash = $this->platformRejectAndStartCash($payment);
        $this->as($this->admin)->post(route('billing.payments.cancel', $cash->reference))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->payment($cash->reference)->status);
    }

    private function platformRejectAndStartCash(BillingPayment $payment): BillingPayment
    {
        $this->as($this->platform())->post(route('platform.payments.reject', $payment->id), ['reason' => 'Not found on statement']);

        return $this->start('cash');
    }

    // ================================================================== TENANT ISOLATION / PERMISSIONS

    public function test_another_schools_payment_is_invisible_and_untouchable(): void
    {
        $this->bank();
        $payment = $this->start('bank_transfer');

        $other = School::query()->create(['name' => 'Rival', 'slug' => 'rival', 'status' => 'active', 'category' => 'Primary']);
        app(SubscriptionLifecycle::class)->startTrial($other);
        $rival = $this->userFor($other, 'school_admin');

        $this->as($rival)->get(route('billing.payments.show', $payment->reference))->assertNotFound();
        $this->as($rival)->post(route('billing.payments.cancel', $payment->reference))->assertNotFound();
        $this->as($rival)->post(route('billing.payments.refresh', $payment->reference))->assertNotFound();
        $this->as($rival)->post(route('billing.payments.evidence', $payment->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ])->assertNotFound();
        $this->assertSame('pending', $this->payment($payment->reference)->status);
    }

    public function test_only_school_billing_managers_can_start_payments(): void
    {
        $this->as($this->userFor($this->school, 'teacher'))->post(route('billing.payments.store'), ['method' => 'cash'])->assertForbidden();
        $this->as($this->userFor($this->school, 'accountant'))->post(route('billing.payments.store'), ['method' => 'cash'])->assertForbidden();
        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count());

        $this->as($this->userFor($this->school, 'finance_officer'))->post(route('billing.payments.store'), ['method' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame(1, BillingPayment::withoutGlobalScopes()->count());
    }

    public function test_platform_payment_pages_respect_finance_permissions(): void
    {
        $this->start('cash');

        $this->as($this->admin)->get(route('platform.payments.index'))->assertForbidden();
        $this->as($this->platform('platform_support'))->get(route('platform.payments.index'))->assertForbidden();
        $this->as($this->platform('platform_auditor'))->get(route('platform.payments.index'))->assertOk();
        $this->as($this->platform())->get(route('platform.payments.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Platform/Payments/Index')->has('payments.data', 1)->where('stats.open_cash', 1));
    }

    public function test_one_open_payment_at_a_time(): void
    {
        $this->bank();
        $this->start('cash');
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'bank_transfer'])->assertSessionHasErrors('payment');
        $this->assertSame(1, BillingPayment::withoutGlobalScopes()->count());
    }

    // ================================================================== STRIPE

    public function test_stripe_is_refused_when_not_configured_or_without_a_currency_strategy(): void
    {
        config(['schoolos.billing.stripe.secret_key' => null]);
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'stripe', 'expected_charge' => 29000])->assertSessionHasErrors('payment');

        config(['schoolos.billing.stripe.secret_key' => 'sk_test_dummy', 'schoolos.billing.stripe.fx_rate' => null]);
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'stripe', 'expected_charge' => 29000])->assertSessionHasErrors('payment');

        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count(), 'never converts silently');
        Http::assertNothingSent();
    }

    public function test_stripe_checkout_charges_the_disclosed_converted_amount(): void
    {
        $response = $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'stripe', 'expected_charge' => 29000]);
        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_123');

        $payment = $this->payment();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('cs_test_123', $payment->provider_reference);
        $this->assertSame(29000, $payment->charged_amount);
        $this->assertSame('USD', $payment->charged_currency);
        $this->assertSame(0.0145, $payment->fx_rate);
        $this->assertSame(20000, $payment->total_amount);
        $this->assertSame('GMD', $payment->currency);
        $this->assertSame('trial', $this->sub()->status, 'a checkout session alone never activates');

        Http::assertSent(function ($request) use ($payment) {
            if (! str_ends_with($request->url(), '/v1/checkout/sessions') || $request->method() !== 'POST') {
                return false;
            }
            $data = $request->data();

            return $data['mode'] === 'payment'
                && $data['client_reference_id'] === $payment->reference
                && $data['line_items'][0]['price_data']['unit_amount'] === 29000
                && $data['line_items'][0]['price_data']['currency'] === 'usd'
                && $request->hasHeader('Idempotency-Key')
                && ! str_contains(json_encode($data), 'card');
        });
    }

    public function test_a_changed_charge_is_refused_rather_than_silently_applied(): void
    {
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'stripe', 'expected_charge' => 12345])
            ->assertSessionHasErrors('payment');
        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count());
    }

    public function test_gateway_failure_at_session_creation_is_reported_and_leaves_a_failed_payment(): void
    {
        $this->providerDown = true;
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'stripe', 'expected_charge' => 29000])
            ->assertSessionHasErrors('payment');

        $payment = $this->payment();
        $this->assertSame('failed', $payment->status);
        $this->assertContains('payment_initiation_failed', $this->events($payment));
        $this->assertSame('trial', $this->sub()->status);

        $this->providerDown = false;
        $this->assertNotSame($payment->id, $this->startStripe()->id, 'the school can retry');
    }

    public function test_verified_stripe_webhook_activates_after_server_side_confirmation(): void
    {
        $payment = $this->startStripe();

        $response = $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']));
        $response->assertOk();

        $payment = $this->payment($payment->reference);
        $this->assertSame('verified', $payment->status);
        $this->assertSame('pi_test_123', $payment->transaction_reference);
        $this->assertNotNull($payment->provider_confirmed_at);
        $this->assertNotNull($payment->receipt_number);
        $this->assertSame('active', $this->sub()->status);
        $this->assertSame('processed', BillingWebhookEvent::query()->where('event_id', 'evt_1')->value('status'));
        $this->assertContains('payment_verified', $this->events($payment));
    }

    public function test_invalid_stale_or_tampered_stripe_signatures_are_rejected(): void
    {
        $this->startStripe();
        $body = $this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']);

        $this->postStripe($body, 't='.time().',v1='.str_repeat('a', 64))->assertStatus(400);
        $this->postStripe($body, 'garbage')->assertStatus(400);
        $this->postStripe($body, null, time() - 3600)->assertStatus(400); // valid HMAC, but replayed outside the window

        $ts = time();
        $sig = 't='.$ts.',v1='.hash_hmac('sha256', $ts.'.'.$body, self::STRIPE_SECRET);
        $this->postStripe(str_replace('cs_test_123', 'cs_test_999', $body), $sig)->assertStatus(400); // body altered after signing

        $this->call('POST', '/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400); // unsigned

        $this->assertSame('trial', $this->sub()->status);
        $this->assertSame(0, BillingWebhookEvent::query()->count());
    }

    public function test_replayed_stripe_event_is_acknowledged_but_applied_once(): void
    {
        $payment = $this->startStripe();
        $body = $this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']);

        $this->postStripe($body)->assertOk();
        $end = $this->sub()->ends_at->toDateTimeString();

        $this->postStripe($body)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123'], 'evt_other'))->assertOk();

        $this->assertSame(1, app(TenantContext::class)->runAsPlatform(fn () => BillingPayment::query()->where('status', 'verified')->count()));
        $this->assertSame($end, $this->sub()->ends_at->toDateTimeString(), 'a replay must never extend the subscription');
        $this->assertSame(1, BillingWebhookEvent::query()->where('event_id', 'evt_1')->count());
        $this->assertSame(1, count(array_filter($this->events($this->payment($payment->reference)), fn ($t) => $t === 'activated')));
    }

    public function test_a_webhook_alone_is_not_trusted_the_provider_must_confirm_payment(): void
    {
        $payment = $this->startStripe();
        $this->stripeSession = $this->stripePaidSession($payment, ['payment_status' => 'unpaid', 'status' => 'open']);

        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123', 'payment_status' => 'paid']))->assertOk();

        $this->assertSame('pending', $this->payment($payment->reference)->status);
        $this->assertSame('trial', $this->sub()->status);
    }

    public function test_amount_or_currency_mismatch_never_activates_and_escalates(): void
    {
        foreach ([['amount_total' => 100], ['currency' => 'eur'], ['client_reference_id' => 'SOMEONE-ELSE']] as $i => $override) {
            $payment = $this->startStripe();
            $this->stripeSession = $this->stripePaidSession($payment, $override);

            $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123'], 'evt_m'.$i))->assertOk();

            $payment = $this->payment($payment->reference);
            $this->assertSame('failed', $payment->status, json_encode($override));
            $this->assertTrue($payment->meta['mismatch']);
            $this->assertContains('payment_mismatch', $this->events($payment));
            $this->assertSame('trial', $this->sub()->status);

            // clear the failed payment's unique session id so the next iteration can reuse the fake id
            BillingPayment::withoutGlobalScopes()->whereKey($payment->id)->update(['provider_reference' => 'old_'.$i]);
        }
    }

    public function test_browser_return_never_activates_only_the_providers_answer_does(): void
    {
        $payment = $this->startStripe();
        $this->stripeSession = $this->stripePaidSession($payment, ['payment_status' => 'unpaid', 'status' => 'open']);

        // forged "success" in the query string
        $this->as($this->admin)->get(route('billing.payments.return', ['reference' => $payment->reference, 'result' => 'success']))
            ->assertRedirect(route('billing.payments.show', $payment->reference));
        $this->assertSame('pending', $this->payment($payment->reference)->status);
        $this->assertSame('trial', $this->sub()->status);

        // once the provider really reports payment, the same return trip settles it (server-side verification)
        $this->stripeSession = $this->stripePaidSession($payment);
        $this->as($this->admin)->get(route('billing.payments.return', ['reference' => $payment->reference, 'result' => 'cancelled']));
        $this->assertSame('verified', $this->payment($payment->reference)->status);
        $this->assertSame('active', $this->sub()->status);
    }

    public function test_provider_outage_during_webhook_returns_5xx_and_a_retry_succeeds(): void
    {
        $payment = $this->startStripe();
        $body = $this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']);

        $this->providerDown = true;
        $this->postStripe($body)->assertStatus(503);
        $this->assertSame('failed', BillingWebhookEvent::query()->where('event_id', 'evt_1')->value('status'));
        $this->assertSame('trial', $this->sub()->status);

        $this->providerDown = false;
        $this->postStripe($body)->assertOk();
        $this->assertSame('verified', $this->payment($payment->reference)->status);
        $this->assertSame(2, BillingWebhookEvent::query()->where('event_id', 'evt_1')->value('attempts'));
    }

    public function test_stripe_expired_unknown_and_unhandled_events(): void
    {
        $payment = $this->startStripe();
        $this->stripeSession = ['id' => 'cs_test_123', 'status' => 'expired', 'payment_status' => 'unpaid', 'client_reference_id' => $payment->reference];

        $this->postStripe($this->stripeEvent('checkout.session.expired', ['id' => 'cs_test_123'], 'evt_e'))->assertOk();
        $this->assertSame('expired', $this->payment($payment->reference)->status);
        $this->assertSame('trial', $this->sub()->status);

        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_unknown'], 'evt_u'))->assertOk()->assertJson(['status' => 'ignored']);
        $this->postStripe($this->stripeEvent('customer.created', ['id' => 'cus_1'], 'evt_x'))->assertOk()->assertJson(['status' => 'ignored']);
    }

    public function test_a_payment_confirmed_after_cancellation_is_still_honoured_and_flagged_late(): void
    {
        $payment = $this->startStripe();
        $this->as($this->admin)->post(route('billing.payments.cancel', $payment->reference))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $this->payment($payment->reference)->status);

        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']))->assertOk();

        $this->assertSame('verified', $this->payment($payment->reference)->status);
        $this->assertSame('active', $this->sub()->status);
        $late = app(TenantContext::class)->runAsPlatform(fn () => BillingEvent::query()->where('billing_payment_id', $payment->id)->where('type', 'activated')->first());
        $this->assertTrue($late->meta['late_confirmation']);
    }

    public function test_full_stripe_refund_is_recorded_without_silently_changing_access(): void
    {
        $payment = $this->startStripe();
        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']))->assertOk();
        $endsAt = $this->sub()->ends_at->toDateTimeString();

        $partial = ['id' => 'ch_1', 'payment_intent' => 'pi_test_123', 'amount' => 29000, 'amount_refunded' => 1000];
        $this->postStripe($this->stripeEvent('charge.refunded', $partial, 'evt_r1'))->assertOk();
        $this->assertSame('verified', $this->payment($payment->reference)->status);

        $this->postStripe($this->stripeEvent('charge.refunded', ['amount_refunded' => 29000] + $partial, 'evt_r2'))->assertOk();
        $payment = $this->payment($payment->reference);
        $this->assertSame('refunded', $payment->status);
        $this->assertNotNull($payment->refunded_at);
        $this->assertContains('payment_refunded', $this->events($payment));
        $this->assertSame('active', $this->sub()->status, 'access change after a refund is a human decision');
        $this->assertSame($endsAt, $this->sub()->ends_at->toDateTimeString());
        $this->assertSame(1, count(app(PaymentReconciler::class)->report()['attention']));
    }

    public function test_webhook_endpoint_is_public_stateless_and_hidden_when_a_provider_is_off(): void
    {
        $route = Route::getRoutes()->getByName('webhooks.billing');
        $middleware = $route->gatherMiddleware();
        $this->assertNotContains('web', $middleware);
        $this->assertNotContains('auth', $middleware);
        $this->assertEmpty(array_filter($middleware, fn ($m) => is_string($m) && str_contains(strtolower($m), 'csrf')));

        config(['schoolos.billing.payment_methods.stripe.enabled' => false]);
        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']))->assertNotFound();
        $this->call('POST', '/webhooks/paypal', [], [], [], [], '{}')->assertNotFound();
    }

    public function test_no_card_data_is_ever_stored(): void
    {
        $columns = implode(' ', Schema::getColumnListing('billing_payments'));
        foreach (['card', 'cvv', 'cvc', 'pan', 'expiry'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $columns);
        }
    }

    // ================================================================== WAVE

    public function test_wave_checkout_is_created_with_bearer_auth_reference_and_disclosed_conversion(): void
    {
        config(['schoolos.billing.wave.request_signing_secret' => 'wave_sn_AKS_secret']);

        $response = $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'wave', 'expected_charge' => 170000]);
        $response->assertRedirect('https://pay.wave.com/c/cos-test-1');

        $payment = $this->payment();
        $this->assertSame(170000, $payment->charged_amount);
        $this->assertSame('XOF', $payment->charged_currency);
        $this->assertSame('cos-test-1', $payment->provider_reference);
        $this->assertSame('trial', $this->sub()->status);

        Http::assertSent(function ($request) use ($payment) {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/v1/checkout/sessions')) {
                return false;
            }
            $body = $request->body();
            [$t, $v1] = array_map(fn ($p) => explode('=', $p, 2)[1], explode(',', $request->header('Wave-Signature')[0]));

            return $request->header('Authorization')[0] === 'Bearer wave_sn_prod_dummy'
                && $request->data()['amount'] === '170000'
                && $request->data()['currency'] === 'XOF'
                && $request->data()['client_reference'] === $payment->reference
                && str_starts_with($request->data()['success_url'], 'http')
                && hash_equals(hash_hmac('sha256', $t.$body, 'wave_sn_AKS_secret'), $v1);
        });
    }

    public function test_wave_without_a_currency_strategy_is_refused(): void
    {
        config(['schoolos.billing.wave.fx_rate' => null]);
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'wave', 'expected_charge' => 170000])->assertSessionHasErrors('payment');
        $this->assertSame(0, BillingPayment::withoutGlobalScopes()->count());

        config(['schoolos.billing.wave.currency' => 'GMD']); // only if Wave enabled GMD for the merchant: no conversion needed
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'wave', 'expected_charge' => 2000000])->assertSessionHasNoErrors();
        $this->assertSame('GMD', $this->payment()->charged_currency);
    }

    public function test_verified_wave_webhook_activates_after_the_checkout_is_retrieved(): void
    {
        $payment = $this->startWave();

        $this->postWave($this->waveEvent('checkout.session.completed'))->assertOk();

        $payment = $this->payment($payment->reference);
        $this->assertSame('verified', $payment->status);
        $this->assertSame('TWAVE123', $payment->transaction_reference);
        $this->assertSame('active', $this->sub()->status);
        Http::assertSent(fn ($r) => $r->method() === 'GET' && str_ends_with($r->url(), '/v1/checkout/sessions/cos-test-1'));
    }

    public function test_wave_signature_rules(): void
    {
        $this->startWave();
        $body = $this->waveEvent('checkout.session.completed');

        $this->postWave($body, 't='.time().',v1='.str_repeat('0', 64))->assertStatus(400);
        $this->postWave($body, null, time() - 3600)->assertStatus(400);
        $this->postWave($body, null, null, ['HTTP_AUTHORIZATION' => 'Bearer '.self::WAVE_SECRET], )->assertOk(); // valid signature wins; header irrelevant
        $this->assertSame('active', $this->sub()->status);
    }

    public function test_wave_shared_secret_strategy_requires_the_bearer_secret_and_cannot_be_downgraded_to(): void
    {
        $payment = $this->startWave();
        $body = $this->waveEvent('checkout.session.completed');

        // signing strategy configured: the shared-secret form is not accepted
        $this->call('POST', '/webhooks/wave', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.self::WAVE_SECRET], $body)->assertStatus(400);

        config(['schoolos.billing.wave.webhook_strategy' => 'shared']);
        $this->call('POST', '/webhooks/wave', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer wrong'], $body)->assertStatus(400);
        $this->call('POST', '/webhooks/wave', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertStatus(400);
        $this->assertSame('pending', $this->payment($payment->reference)->status);

        $this->call('POST', '/webhooks/wave', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.self::WAVE_SECRET], $body)->assertOk();
        $this->assertSame('verified', $this->payment($payment->reference)->status);
    }

    public function test_wave_payment_failed_event_is_an_attempt_not_the_end_of_the_session(): void
    {
        $payment = $this->startWave();
        $this->waveSession = $this->waveSucceededSession($payment, [
            'payment_status' => 'cancelled', 'checkout_status' => 'open', 'last_payment_error' => ['code' => 'insufficient-funds'],
        ]);

        $this->postWave($this->waveEvent('checkout.session.payment_failed', 'cos-test-1', 'AE_f1'))->assertOk();
        $payment = $this->payment($payment->reference);
        $this->assertSame('pending', $payment->status);
        $this->assertSame('insufficient-funds', $payment->meta['last_provider_error']);
        $this->assertContains('provider_attempt_failed', $this->events($payment));

        // the payer retries and succeeds
        $this->waveSession = $this->waveSucceededSession($payment);
        $this->postWave($this->waveEvent('checkout.session.completed', 'cos-test-1', 'AE_ok'))->assertOk();
        $this->assertSame('verified', $this->payment($payment->reference)->status);
    }

    public function test_wave_amount_mismatch_expiry_and_unverified_webhooks(): void
    {
        $payment = $this->startWave();

        // webhook says completed, Wave API says nothing was paid
        $this->waveSession = $this->waveSucceededSession($payment, ['payment_status' => 'processing', 'checkout_status' => 'open']);
        $this->postWave($this->waveEvent('checkout.session.completed', 'cos-test-1', 'AE_a'))->assertOk();
        $this->assertSame('pending', $this->payment($payment->reference)->status);

        // expiry
        $this->waveSession = $this->waveSucceededSession($payment, ['payment_status' => 'processing', 'checkout_status' => 'expired']);
        $this->postWave($this->waveEvent('checkout.session.completed', 'cos-test-1', 'AE_b'))->assertOk();
        $this->assertSame('expired', $this->payment($payment->reference)->status);

        // wrong amount on a fresh payment (free the fake's fixed session id used by the expired one first)
        BillingPayment::withoutGlobalScopes()->whereKey($payment->id)->update(['provider_reference' => 'cos-old']);
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'wave', 'expected_charge' => 170000]);
        $fresh = $this->payment();
        $this->waveSession = $this->waveSucceededSession($fresh, ['amount' => '5000']);
        $this->postWave($this->waveEvent('checkout.session.completed', 'cos-test-1', 'AE_c'))->assertOk();
        $this->assertSame('failed', $this->payment($fresh->reference)->status);
        $this->assertSame('trial', $this->sub()->status);
    }

    // ================================================================== RECONCILIATION

    public function test_reconciliation_settles_a_paid_session_whose_webhook_never_arrived(): void
    {
        $payment = $this->startStripe();

        $this->artisan('schoolos:billing:reconcile')->expectsOutputToContain('checked: 0')->assertSuccessful(); // too fresh to check

        Carbon::setTestNow(now()->addMinutes(15));
        $this->artisan('schoolos:billing:reconcile')->expectsOutputToContain('settled: 1')->assertSuccessful();

        $this->assertSame('verified', $this->payment($payment->reference)->status);
        $this->assertSame('active', $this->sub()->status);
        $this->assertContains('payment_verified', $this->events($this->payment($payment->reference)));

        // idempotent
        $this->artisan('schoolos:billing:reconcile')->expectsOutputToContain('checked: 0')->assertSuccessful();
    }

    public function test_reconciliation_reports_provider_outages_as_errors_and_changes_nothing(): void
    {
        $payment = $this->startWave();
        Carbon::setTestNow(now()->addMinutes(15));
        $this->providerDown = true;

        $this->artisan('schoolos:billing:reconcile')->expectsOutputToContain('errors: 1')->assertFailed();
        $this->assertSame('pending', $this->payment($payment->reference)->status);
    }

    public function test_stale_manual_payments_expire_with_an_audit_entry(): void
    {
        $pending = $this->start('cash');

        Carbon::setTestNow(now()->addDays(29));
        $this->artisan('schoolos:billing:reconcile')->assertSuccessful();
        $this->assertSame('pending', $this->payment($pending->reference)->status);

        Carbon::setTestNow(now()->addDays(2));
        $this->artisan('schoolos:billing:reconcile')->expectsOutputToContain('expired: 1')->assertSuccessful();
        $this->assertSame('expired', $this->payment($pending->reference)->status);
        $this->assertContains('payment_expired', $this->events($this->payment($pending->reference)));
        $this->assertSame('trial', $this->sub()->status);

        // the school simply starts again
        $this->assertNotSame($pending->id, $this->start('cash')->id);
    }

    public function test_under_verification_payments_do_not_expire_and_surface_when_overdue(): void
    {
        $this->bank();
        $bank = $this->start('bank_transfer');
        $this->as($this->admin)->post(route('billing.payments.evidence', $bank->reference), [
            'evidence' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ]);

        Carbon::setTestNow(now()->addDays(40));
        $this->artisan('schoolos:billing:reconcile')->assertSuccessful();
        $this->assertSame('pending_verification', $this->payment($bank->reference)->status);

        $report = app(PaymentReconciler::class)->report();
        $this->assertSame('verification_overdue', $report['attention'][0]['reason']);

        $this->as($this->platform())->get(route('platform.payments.reconciliation'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Platform/Payments/Reconciliation')->has('report.attention', 1));
    }

    public function test_platform_can_run_reconciliation_and_recheck_a_gateway_payment(): void
    {
        $payment = $this->startStripe();

        $this->as($this->platform('platform_auditor'))->post(route('platform.payments.reconcile'))->assertForbidden();
        $this->as($this->platform('platform_auditor'))->post(route('platform.payments.recheck', $payment->id))->assertForbidden();

        $this->as($this->platform())->post(route('platform.payments.recheck', $payment->id))->assertSessionHasNoErrors();
        $this->assertSame('verified', $this->payment($payment->reference)->status);
        $this->assertContains('provider_rechecked', $this->events($this->payment($payment->reference)));
    }

    // ================================================================== SETTINGS / EXPORT / CURRENCY

    public function test_bank_accounts_are_configured_by_the_platform_administrator_only(): void
    {
        $body = ['bank_name' => 'GT Bank', 'account_name' => 'SchoolOS', 'account_number' => '123', 'currency' => 'GMD'];

        $this->as($this->platform())->post(route('platform.payments.banks.store'), $body)->assertForbidden(); // finance can verify, not configure
        $this->as($this->admin)->post(route('platform.payments.banks.store'), $body)->assertForbidden();

        $this->as($this->platform('platform_admin'))->post(route('platform.payments.banks.store'), $body)->assertSessionHasNoErrors();
        $account = PlatformBankAccount::query()->firstOrFail();
        $this->assertTrue($account->is_active);

        $this->as($this->platform('platform_admin'))->post(route('platform.payments.banks.toggle', $account))->assertSessionHasNoErrors();
        $this->assertFalse($account->fresh()->is_active);
        $this->as($this->admin)->post(route('billing.payments.store'), ['method' => 'bank_transfer'])->assertSessionHasErrors('payment');
    }

    public function test_settings_page_reports_honest_status_and_never_exposes_secrets(): void
    {
        $response = $this->as($this->platform())->get(route('platform.payments.settings'));
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('Platform/Payments/Settings')
            ->where('methods.2.key', 'wave')
            ->where('methods.2.status', 'configured_untested')
            ->where('methods.3.key', 'stripe')
            ->where('methods.3.mode', 'test')
            ->where('methods.3.status', 'configured_untested'));

        $html = $response->getContent();
        foreach (['sk_test_dummy', self::STRIPE_SECRET, 'wave_sn_prod_dummy', self::WAVE_SECRET] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        config(['schoolos.billing.stripe.secret_key' => null]);
        $this->as($this->platform())->get(route('platform.payments.settings'))
            ->assertInertia(fn ($page) => $page->where('methods.3.status', 'requires_configuration'));
    }

    public function test_gateway_is_only_marked_as_having_traffic_after_a_confirmed_payment_never_verified_automatically(): void
    {
        $this->startStripe();
        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']))->assertOk();

        $this->as($this->platform())->get(route('platform.payments.settings'))
            ->assertInertia(fn ($page) => $page->where('methods.3.status', 'traffic_observed')->where('methods.3.verified_transactions', 1));
    }

    public function test_csv_export_is_scoped_to_finance_and_neutralises_formulas(): void
    {
        $this->school->forceFill(['name' => '=HYPERLINK("http://evil")'])->save();
        $this->start('cash');

        $this->as($this->admin)->get(route('platform.payments.export'))->assertForbidden();

        $csv = $this->as($this->platform())->get(route('platform.payments.export'))->streamedContent();
        $this->assertStringContainsString('reference,school,method', $csv);
        $this->assertStringContainsString("\"'=HYPERLINK", $csv);
        $this->assertStringContainsString(',cash,pending,GMD,20000', $csv);
    }

    public function test_school_billing_page_offers_only_available_methods_and_shows_the_disclosed_charge(): void
    {
        $this->as($this->admin)->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Billing/Index')
                ->where('canManage', true)
                ->where('methods.0.key', 'cash')->where('methods.0.enabled', true)
                ->where('methods.1.key', 'bank_transfer')->where('methods.1.enabled', false)->where('methods.1.reason', 'no_bank_account')
                ->where('methods.3.key', 'stripe')->where('methods.3.charge.amount', 29000)->where('methods.3.charge.currency', 'USD')
                ->where('methods.2.key', 'wave')->where('methods.2.charge.label', 'XOF 170,000'));
    }

    public function test_currency_helper_is_exact_and_rounds_up_only_with_an_explicit_rate(): void
    {
        $this->assertSame(2000000, Currency::convertToMinor(20000, 'GMD', 'GMD', null));
        $this->assertSame(170000, Currency::convertToMinor(20000, 'GMD', 'XOF', 8.5));
        $this->assertSame(29000, Currency::convertToMinor(20000, 'GMD', 'USD', 0.0145));
        $this->assertSame(1, Currency::convertToMinor(1, 'GMD', 'XOF', 0.2), 'rounds up, never short');
        $this->assertNull(Currency::convertToMinor(20000, 'GMD', 'USD', null), 'no rate, no conversion');
        $this->assertNull(Currency::convertToMinor(20000, 'GMD', 'USD', 0.0));
        $this->assertSame('14.50', Currency::minorToDecimalString(1450, 'USD'));
        $this->assertSame('170000', Currency::minorToDecimalString(170000, 'XOF'));
        $this->assertSame(1450, Currency::decimalStringToMinor('14.5', 'USD'));
    }

    public function test_every_payment_leaves_an_ordered_audit_trail(): void
    {
        $payment = $this->startStripe();
        $this->postStripe($this->stripeEvent('checkout.session.completed', ['id' => 'cs_test_123']))->assertOk();

        $this->assertSame(
            ['payment_initiated', 'gateway_session_created', 'activated', 'payment_verified'],
            $this->events($this->payment($payment->reference))
        );

        $this->as($this->platform())->get(route('platform.payments.show', $payment->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Platform/Payments/Show')
                ->has('events', 4)
                ->has('webhookEvents', 1)
                ->where('payment.status', 'verified')
                ->where('payment.provider', 'stripe'));
    }

    public function test_nothing_in_phase_three_introduces_monthly_billing(): void
    {
        foreach (['cash', 'stripe', 'wave'] as $method) {
            $this->assertSame('annual', SchoolSubscription::BILLING_CYCLE);
        }
        $payment = $this->start('cash');
        $this->as($this->platform())->post(route('platform.payments.cash.confirm', $payment->id), ['amount' => 20000, 'paid_at' => now()->toDateString()]);
        $p = $this->payment($payment->reference);
        $this->assertGreaterThanOrEqual(365, $p->period_start->diffInDays($p->period_end));
        $this->assertSame('annual', $this->sub()->billing_cycle);
    }
}
