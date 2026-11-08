# Payment Integration

**Status (Phase 3): cash and bank transfer are Implemented + Tested. Wave and Stripe are Implemented and Tested against FAKED provider HTTP and
locally-signed webhooks only. They are `Requires External Configuration` and are NOT Verified.**

"Verified" is used only after a real sandbox transaction, a real provider webhook delivery and a reconciliation run have succeeded and been recorded by an
operator. Nothing in code, and no screen in the application, marks an integration Verified automatically. The *Platform > Payments > Payment settings* page
reports the most it can honestly know: `Requires configuration` / `Configured, not verified` / `Confirmed payments seen, not verified`.

## Status per method

| Method | Flow | Status |
|---|---|---|
| Cash | School generates a reference, pays at the office, a finance officer records amount/date/receipt-book number, an authorised confirmation activates | **Implemented, Tested** |
| Bank transfer | Platform admin publishes bank details, school uploads evidence, `pending_verification`, finance checks the REAL bank statement, approve/reject, receipt | **Implemented, Tested** |
| Wave | Official Wave Checkout API, unique `client_reference`, signed webhook, server-side re-fetch, reconciliation | **Tested (faked HTTP)**, **Requires External Configuration**, not Verified |
| Stripe | Hosted Checkout Session, signed webhook (`Stripe-Signature` HMAC-SHA256, 300 s tolerance), server-side re-fetch, refund detection, reconciliation | **Tested (faked HTTP)**, **Requires External Configuration**, not Verified |
| Receipts | Printable HTML receipt (browser print / save as PDF) for verified and refunded payments, school and platform | **Implemented, Tested** (no server-side PDF library is installed) |

## Core rules (enforced in `PaymentService`, tested in `PaymentWorkflowTest`)

* **Only `SubscriptionLifecycle::recordVerifiedPayment()` activates/renews/reactivates.** Every method funnels into it.
* Only the **full annual amount** activates. Partial, excess and duplicate transaction references are refused (and the refusal is audited). Instalments and
  monthly billing do not exist.
* Status machine: `pending -> pending_verification -> verified | rejected`, plus `failed`, `cancelled`, `expired`, `refunded`. One open payment per school.
* **An uploaded receipt is not proof of transfer.** Evidence moves a payment to `pending_verification` only. A finance user must confirm they checked the
  real statement and enter the bank transaction reference.
* **A browser return is not proof of payment.** The return URL and its query string are ignored; the provider is asked server-to-server.
* **A webhook is not trusted by itself.** After the signature is verified, the payment is re-fetched from the provider and the amount, currency and
  client reference must match what we created. A mismatch never activates; it marks the payment `failed` with `meta.mismatch` and escalates.
* Webhooks are idempotent (`billing_webhook_events` unique `(provider, event_id)`); a replay returns 200 and applies nothing twice. A provider outage
  returns 503 so the provider retries. Only a hash of the payload is stored.
* A payment confirmed by the provider after the school cancelled or it expired is honoured and flagged `late`.
* Refunds are **detected and flagged** (Stripe `charge.refunded`); access is not withdrawn automatically (see `known-limitations.md`).
* Evidence files live on a private disk, are sniffed server-side (PDF/JPG/PNG only, 5 MB), stored under a random name and served only as a download with
  `nosniff` to authorised finance roles; every view is audited.
* No card number, CVV or provider secret is ever stored. Card entry happens only on Stripe's hosted page.

## Currency strategy (never silent)

Prices are in GMD. Stripe does not list GMD and Wave documents XOF, so each gateway has an explicit `currency` and fixed `fx_rate`
(`SCHOOLOS_STRIPE_*`, `SCHOOLOS_WAVE_*`). Without both, the method is **unavailable** (reason shown). The school sees the converted charge, the fixed rate
and the original total before paying, must submit the amount it was shown (`expected_charge`), and the server refuses if it changed. Conversion rounds
up to the minor unit. The rate is a business decision that has not been supplied: **an approved rate is required before enabling either gateway.**
Whether Wave can charge GMD directly must be confirmed with Wave.

## Roles

| Action | Permission |
|---|---|
| Start / cancel a payment, upload evidence (school) | `subscriptions.manage` (school_admin, finance_officer) |
| View school payments / receipts | `subscriptions.view` |
| View platform payments, export, reconciliation, settings | `platform.finance.view` |
| Confirm cash, approve/reject bank, re-check, run reconciliation | `platform.payments.verify` (platform_admin, platform_finance) |
| Add / toggle bank accounts | `platform.payments.configure` (platform_admin only) |

`platform_support` and `platform_auditor` are read-only. Finance approval is single-step (see limitations).

## Endpoints

School (tenant, reachable while blocked): `POST billing/payments`, `GET billing/payments/{reference}`, `POST .../evidence`, `POST .../cancel`,
`GET .../return`, `POST .../refresh`, `GET .../receipt`.
Platform: `platform/payments` (list, export, show, cash, bank, reject, recheck, evidence, receipt, reconciliation, reconciliation/run, settings, banks).
Public, stateless, no CSRF, throttled 600/min: `POST /webhooks/stripe`, `POST /webhooks/wave` (404 when the provider is off or unconfigured).

## What an operator must do before going live (Requires External Configuration)

1. **Stripe**: create the account, set `STRIPE_SECRET` and `STRIPE_WEBHOOK_SECRET`; add `https://<host>/webhooks/stripe` for events
   `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `checkout.session.expired`,
   `charge.refunded`; set `SCHOOLOS_STRIPE_CURRENCY` + `SCHOOLOS_STRIPE_FX_RATE`; set `SCHOOLOS_PAY_STRIPE=true`. Use **test keys first**.
2. **Wave**: obtain a merchant API key (`WAVE_API_KEY`), choose the webhook strategy (`signing` with `WAVE_WEBHOOK_SECRET`, or `shared` secret),
   register `https://<host>/webhooks/wave`, set `SCHOOLOS_WAVE_CURRENCY` + `SCHOOLOS_WAVE_FX_RATE`, then `SCHOOLOS_PAY_WAVE=true`. Wave sandbox access must be
   confirmed with Wave.
3. Run a sandbox payment end to end for each gateway: checkout -> webhook delivered (see *Webhook deliveries* on the payment) -> activation -> receipt ->
   `schoolos:billing:reconcile`. Only then may the integration be recorded as Verified in `implementation-progress.md`.
4. Local development needs an HTTPS tunnel for provider webhooks.
5. Publish at least one bank account (*Payment settings*) and set `SCHOOLOS_CASH_OFFICE`.

Secrets must be supplied through environment variables and must never be committed. `.env.example` lists names only.
