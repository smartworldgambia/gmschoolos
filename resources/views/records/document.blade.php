<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $doc->reference }}</title>
<style>
    @page { margin: 28px 34px 40px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #111827; }
    h1 { font-size: 17px; margin: 0; text-align: center; letter-spacing: .5px; }
    h2 { font-size: 12px; margin: 14px 0 4px; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: middle; }
    .school { text-align: center; }
    .school .name { font-size: 16px; font-weight: bold; }
    .muted { color: #6b7280; }
    .banner { border: 1px solid #9ca3af; background: #f3f4f6; padding: 5px 8px; text-align: center; font-size: 9.5px; margin: 8px 0; }
    .meta td { padding: 2px 4px; }
    .grid th, .grid td { border: 1px solid #d1d5db; padding: 3px 5px; text-align: left; }
    .grid th { background: #f3f4f6; font-size: 9.5px; }
    .right { text-align: right; }
    .term { margin-top: 10px; page-break-inside: avoid; }
    .missing { color: #b91c1c; font-style: italic; }
    .sign td { width: 33%; padding-top: 34px; text-align: center; font-size: 9.5px; }
    .line { border-top: 1px solid #111827; padding-top: 3px; margin: 0 14px; }
    .wm { position: fixed; top: 38%; left: 8%; width: 84%; text-align: center; font-size: 46px; color: #e5e7eb; transform: rotate(-24deg); z-index: -1; font-weight: bold; }
    .body { white-space: pre-line; line-height: 1.55; font-size: 11.5px; margin-top: 14px; }
    .foot { margin-top: 16px; }
    .foot td { vertical-align: bottom; font-size: 9px; }
</style>
</head>
<body>
@if($watermark)<div class="wm">{{ $watermark }}</div>@endif

<table class="head">
    <tr>
        <td style="width:70px">@if($logo)<img src="{{ $logo }}" style="max-width:64px;max-height:64px">@endif</td>
        <td class="school">
            <div class="name">{{ $school['name'] ?? '' }}</div>
            <div class="muted">{{ $school['address'] ?? '' }}</div>
            <div class="muted">{{ trim(($school['phone'] ?? '').'  '.($school['email'] ?? '')) }}</div>
        </td>
        <td style="width:70px; text-align:right">@if($photo)<img src="{{ $photo }}" style="max-width:62px;max-height:76px">@endif</td>
    </tr>
</table>

<h1>
    @switch($doc->type)
        @case('transcript_g9') SCHOOL TRANSCRIPT - GRADE 9 @break
        @case('transcript_g12') SCHOOL TRANSCRIPT - GRADE 12 @break
        @case('testimonial') TESTIMONIAL @break
        @case('cover_letter') SCHOOL COVER LETTER @break
        @default ADMISSION LETTER
    @endswitch
</h1>
<div class="banner">{{ $disclaimer }}</div>

<table class="meta">
    <tr><td><strong>Name:</strong> {{ $payload['student']['full_name'] ?? '' }}</td><td><strong>Permanent student ID:</strong> {{ $payload['student']['student_number'] ?? '' }}</td></tr>
    <tr>
        <td><strong>Programme / level:</strong> {{ $payload['student']['programme'] ?? '' }}</td>
        <td><strong>Document reference:</strong> {{ $doc->reference }}</td>
    </tr>
    <tr>
        <td><strong>Academic years:</strong> {{ implode(', ', $payload['academic_years'] ?? []) ?: '-' }}</td>
        <td><strong>Issue date:</strong> {{ $doc->issue_date?->format('j F Y') ?? 'Not issued' }}</td>
    </tr>
</table>

@if(str_starts_with($doc->type, 'transcript'))
    @foreach(($payload['terms'] ?? []) as $term)
        <div class="term">
            <h2>Grade {{ $term['grade_number'] }} - Term {{ $term['term_sequence'] }}@if($term['academic_year']) <span class="muted">({{ $term['academic_year'] }})</span>@endif</h2>
            @if($term['status'] === 'missing')
                <div class="missing">No record available for this term.</div>
            @else
                <table class="grid">
                    <tr><th>Subject</th><th class="right" style="width:50px">Mark</th><th style="width:44px">Grade</th><th>Grade description</th></tr>
                    @foreach($term['entries'] as $e)
                        <tr>
                            <td>{{ $e['subject'] }}</td>
                            <td class="right">{{ $e['mark'] !== null ? rtrim(rtrim(number_format($e['mark'], 2), '0'), '.') : '-' }}</td>
                            <td>{{ $e['grade'] ?? '-' }}</td>
                            <td>{{ $e['description'] ?? '' }}</td>
                        </tr>
                    @endforeach
                    <tr><td colspan="4" class="muted">Term average: {{ $term['average'] !== null ? $term['average'] : '-' }} over {{ $term['subjects_count'] }} subject(s)</td></tr>
                </table>
            @endif
        </div>
    @endforeach

    <h2>Performance summary</h2>
    <div>
        Terms recorded: {{ $payload['summary']['terms_recorded'] ?? 0 }} of {{ $payload['summary']['terms_required'] ?? 0 }}.
        Overall average: {{ $payload['summary']['overall_average'] ?? '-' }}.
        @if(!empty($payload['exception']))
            <br><span class="muted">Approved exception: {{ $payload['exception']['reason'] ?? '' }} ({{ $payload['exception']['approved_by_name'] ?? '' }})</span>
        @endif
    </div>
@else
    <div class="body">{{ $doc->body }}</div>
@endif

<table class="sign">
    <tr>
        @forelse($signatories as $s)
            <td><div class="line"><strong>{{ $s['name'] }}</strong><br>{{ $s['title'] ?? '' }}</div></td>
        @empty
            <td><div class="line">Authorised signature</div></td>
        @endforelse
        <td>
            @if($stamp)<img src="{{ $stamp }}" style="max-width:90px;max-height:90px"><br>@endif
            <div class="line">Official school stamp</div>
        </td>
    </tr>
</table>

<table class="foot">
    <tr>
        <td>
            Verify this document: {{ $verifyUrl }}<br>
            Reference {{ $doc->reference }} · {{ $disclaimer }}
        </td>
        <td style="width:90px; text-align:right"><img src="{{ $qr }}" style="width:84px;height:84px"></td>
    </tr>
</table>
</body>
</html>
