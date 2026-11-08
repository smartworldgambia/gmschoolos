<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $payment->receipt_number }}</title>
<style>
    @page { margin: 26px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 16px; margin: 0 0 2px; text-align: center; letter-spacing: .6px; }
    .school { text-align: center; margin-bottom: 8px; }
    .school .name { font-size: 15px; font-weight: bold; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    td { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    td.k { width: 38%; color: #6b7280; }
    .amount { font-size: 18px; font-weight: bold; text-align: center; margin: 14px 0; border: 1px solid #9ca3af; padding: 8px; }
    .wm { position: fixed; top: 36%; left: 6%; width: 88%; text-align: center; font-size: 44px; color: #fecaca; transform: rotate(-24deg); z-index: -1; font-weight: bold; }
    .foot { margin-top: 18px; font-size: 9.5px; text-align: center; color: #6b7280; }
</style>
</head>
<body>
@if($watermark)<div class="wm">{{ $watermark }}</div>@endif
<div class="school">
    <div class="name">{{ $school?->name }}</div>
    <div class="muted">{{ $school?->address }}</div>
    <div class="muted">{{ trim(($school?->phone ?? '').'  '.($school?->email ?? '')) }}</div>
</div>
<h1>RECEIPT</h1>
<div class="amount">{{ config('schoolos.currency.symbol', 'D') }} {{ number_format((int) $payment->amount) }}</div>
<table>
    <tr><td class="k">Receipt no.</td><td>{{ $payment->receipt_number }}</td></tr>
    <tr><td class="k">Date</td><td>{{ $payment->paid_at?->format('d M Y') }}</td></tr>
    <tr><td class="k">Student</td><td>{{ $student?->full_name }} ({{ $student?->student_number }})</td></tr>
    <tr><td class="k">Invoice</td><td>{{ $invoice?->invoice_number }}</td></tr>
    <tr><td class="k">Method</td><td>{{ str_replace('_', ' ', ucfirst($payment->method)) }}@if($payment->external_reference) &mdash; {{ $payment->external_reference }}@endif</td></tr>
    @if($payment->payer_name)<tr><td class="k">Paid by</td><td>{{ $payment->payer_name }}</td></tr>@endif
    <tr><td class="k">Invoice balance</td><td>{{ config('schoolos.currency.symbol', 'D') }} {{ number_format($invoice?->balance() ?? 0) }}</td></tr>
    <tr><td class="k">Status</td><td>{{ ucfirst($payment->status) }}@if($payment->status === 'voided' && $payment->void_reason) &mdash; {{ $payment->void_reason }}@endif</td></tr>
    @if($payment->recorder)<tr><td class="k">Received by</td><td>{{ $payment->recorder->name }}</td></tr>@endif
</table>
<div class="foot">{{ $footer }}</div>
<div class="foot">This is a school fee receipt. It is not an official WAEC or ministry document.</div>
</body>
</html>
