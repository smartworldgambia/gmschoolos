<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Payslip {{ $run?->period_label }}</title>
<style>
    @page { margin: 30px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #111827; }
    h1 { font-size: 17px; margin: 6px 0 2px; text-align: center; letter-spacing: .6px; }
    .school { text-align: center; margin-bottom: 6px; }
    .school .name { font-size: 15px; font-weight: bold; }
    .muted { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    td, th { padding: 5px 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; text-align: left; }
    th { background: #f3f4f6; font-size: 10px; text-transform: uppercase; }
    td.k { width: 30%; color: #6b7280; }
    td.r, th.r { text-align: right; }
    .net { font-size: 16px; font-weight: bold; text-align: right; border-top: 2px solid #111827; }
    .wm { position: fixed; top: 38%; left: 6%; width: 88%; text-align: center; font-size: 40px; color: #fecaca; transform: rotate(-24deg); z-index: -1; font-weight: bold; }
    .foot { margin-top: 18px; font-size: 9.5px; text-align: center; color: #6b7280; }
</style>
</head>
<body>
@if($watermark)<div class="wm">{{ $watermark }}</div>@endif
<div class="school">
    <div class="name">{{ $school?->name }}</div>
    <div class="muted">{{ $school?->address }}</div>
</div>
<h1>PAYSLIP</h1>
<div class="muted" style="text-align:center">{{ $run?->period_label }} ({{ $run?->period_start?->format('d M Y') }} &ndash; {{ $run?->period_end?->format('d M Y') }})</div>
<table>
    <tr><td class="k">Name</td><td>{{ $staff?->full_name }}</td><td class="k">Staff no.</td><td>{{ $staff?->staff_number }}</td></tr>
    <tr><td class="k">Position</td><td>{{ $staff?->position?->title ?? $staff?->role_title }}</td><td class="k">Department</td><td>{{ $staff?->department?->name }}</td></tr>
    @if($maskedAccount)<tr><td class="k">Paid to account</td><td colspan="3">{{ $maskedAccount }}</td></tr>@endif
</table>
<table>
    <tr><th>Description</th><th>Type</th><th class="r">Amount ({{ config('schoolos.currency.symbol', 'D') }})</th></tr>
    @foreach(($item->lines ?? []) as $l)
        <tr>
            <td>{{ $l['name'] }}@if(($l['calc'] ?? '') === 'percent_of_basic') <span class="muted">({{ rtrim(rtrim(number_format((float) $l['value'], 2), '0'), '.') }}% of basic)</span>@endif</td>
            <td>{{ ucfirst($l['kind']) }}</td>
            <td class="r">{{ ($l['kind'] === 'deduction' ? '-' : '') . number_format((int) $l['amount']) }}</td>
        </tr>
    @endforeach
    <tr><td colspan="2"><strong>Gross pay</strong></td><td class="r"><strong>{{ number_format((int) $item->gross_pay) }}</strong></td></tr>
    <tr><td colspan="2"><strong>Total deductions</strong></td><td class="r"><strong>-{{ number_format((int) $item->deductions) }}</strong></td></tr>
    <tr><td colspan="2" class="net">Net pay</td><td class="net">{{ config('schoolos.currency.symbol', 'D') }} {{ number_format((int) $item->net_pay) }}</td></tr>
</table>
@if($run?->status === 'paid')<div class="foot">Paid on {{ $run->paid_at?->format('d M Y') }}.</div>@endif
<div class="foot">Allowances and deductions are those set by the school. This payslip is not a tax document.</div>
</body>
</html>
