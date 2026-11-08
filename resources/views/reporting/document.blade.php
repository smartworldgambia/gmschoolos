<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $report->reference }}</title>
<style>
    @page { margin: 24px 30px 38px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #111827; }
    h1 { font-size: 16px; margin: 6px 0 2px; text-align: center; letter-spacing: .4px; color: #082B50; }
    h2 { font-size: 11.5px; margin: 12px 0 4px; color: #082B50; border-bottom: 1px solid #d1d5db; padding-bottom: 2px; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: middle; }
    .school { text-align: center; }
    .school .name { font-size: 16px; font-weight: bold; color: #082B50; }
    .muted { color: #6b7280; }
    .banner { border: 1px solid #c5d4e8; background: #f4f7fb; padding: 5px 8px; text-align: center; font-size: 9px; margin: 8px 0; color: #334155; }
    .meta td { padding: 3px 4px; vertical-align: top; }
    .grid th, .grid td { border: 1px solid #94a3b8; padding: 4px 6px; text-align: left; }
    .grid th { background: #082B50; color: #fff; font-size: 9.5px; }
    .grid tr:nth-child(even) td { background: #f8fafc; }
    .right { text-align: right; }
    .center { text-align: center; }
    .missing { color: #b91c1c; font-style: italic; }
    .kpi td { width: 25%; padding: 6px; border: 1px solid #e2e8f0; background: #f8fafc; }
    .kpi .lbl { font-size: 8.5px; color: #64748b; text-transform: uppercase; }
    .kpi .val { font-size: 13px; font-weight: bold; color: #082B50; }
    .sign td { width: 33%; padding-top: 32px; text-align: center; font-size: 9.5px; }
    .line { border-top: 1px solid #111827; padding-top: 3px; margin: 0 12px; }
    .wm { position: fixed; top: 38%; left: 6%; width: 88%; text-align: center; font-size: 42px; color: #e5e7eb; transform: rotate(-24deg); z-index: -1; font-weight: bold; }
    .foot { margin-top: 14px; }
    .foot td { vertical-align: bottom; font-size: 8.5px; color: #475569; }
    .comment { border: 1px solid #e2e8f0; padding: 6px 8px; min-height: 28px; margin-bottom: 6px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>
@if($watermark)<div class="wm">{{ $watermark }}</div>@endif
@php
    $student = $payload['student'] ?? [];
    $class = $payload['class'] ?? [];
    $summary = $payload['summary'] ?? [];
    $cfg = $payload['template']['config'] ?? [];
    $title = $cfg['title'] ?? match($report->type) {
        'annual' => 'ANNUAL ACADEMIC REPORT',
        'final' => 'FINAL ACADEMIC REPORT',
        default => 'TERMLY REPORT CARD',
    };
    $fmt = fn ($n) => $n === null || $n === '' ? '—' : (is_numeric($n) ? rtrim(rtrim(number_format((float) $n, 2), '0'), '.') : $n);
@endphp

<table class="head">
    <tr>
        <td style="width:72px">@if(!empty($cfg['show_logo']) && $logo)<img src="{{ $logo }}" style="max-width:66px;max-height:66px">@endif</td>
        <td class="school">
            <div class="name">{{ $school['name'] ?? '' }}</div>
            <div class="muted">{{ $school['address'] ?? '' }}</div>
            <div class="muted">{{ trim(($school['phone'] ?? '').'  '.($school['email'] ?? '')) }}</div>
            @if(!empty($school['motto']))<div class="muted"><em>{{ $school['motto'] }}</em></div>@endif
        </td>
        <td style="width:72px; text-align:right">@if(!empty($cfg['show_photo']) && $photo)<img src="{{ $photo }}" style="max-width:62px;max-height:76px">@endif</td>
    </tr>
</table>

<h1>{{ $title }}</h1>
<div class="banner">{{ $disclaimer }}</div>

<table class="meta">
    <tr>
        <td><strong>Student:</strong> {{ $student['full_name'] ?? '' }}</td>
        <td><strong>Admission no.:</strong> {{ $student['student_number'] ?? '' }}</td>
    </tr>
    <tr>
        <td><strong>Class:</strong> {{ $class['name'] ?? '' }}@if(!empty($class['level'])) ({{ $class['level'] }})@endif</td>
        <td><strong>Academic year:</strong> {{ $payload['academic_year'] ?? '' }}</td>
    </tr>
    <tr>
        <td>
            @if($report->type === 'termly')<strong>Term:</strong> {{ $payload['term']['name'] ?? '' }}
            @elseif($report->type === 'final')<strong>Programme:</strong> {{ $payload['programme'] ?? ($class['level'] ?? '') }}
            @else<strong>Class teacher:</strong> {{ $class['teacher'] ?? '—' }}
            @endif
        </td>
        <td><strong>Reference:</strong> {{ $report->reference }} · v{{ $report->version }}</td>
    </tr>
    <tr>
        <td><strong>Class teacher:</strong> {{ $class['teacher'] ?? '—' }}</td>
        <td><strong>Issue date:</strong> {{ $report->issue_date?->format('j F Y') ?? 'Not published' }}</td>
    </tr>
</table>

@if($report->type === 'termly')
    <h2>Academic results</h2>
    <table class="grid">
        <tr>
            <th>Subject</th>
            <th class="right" style="width:60px">CA</th>
            <th class="right" style="width:60px">Exam</th>
            <th class="right" style="width:60px">Total</th>
            <th style="width:48px">Grade</th>
            <th>Remarks</th>
        </tr>
        @foreach(($payload['subjects'] ?? []) as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ $fmt($row['ca_score'] ?? null) }}</td>
                <td class="right">{{ $fmt($row['exam_score'] ?? null) }}</td>
                <td class="right">@if(($row['status'] ?? '') === 'missing')<span class="missing">Incomplete</span>@else{{ $fmt($row['total'] ?? null) }}@endif</td>
                <td class="center">{{ $row['grade'] ?? '—' }}</td>
                <td>{{ $row['remark'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@elseif($report->type === 'annual')
    <h2>Term-by-term performance</h2>
    @php $termHeads = $payload['terms'] ?? []; @endphp
    <table class="grid">
        <tr>
            <th>Subject</th>
            @foreach($termHeads as $t)<th class="center">{{ $t['name'] }}</th>@endforeach
            <th class="center">Annual avg</th>
            <th style="width:48px">Grade</th>
        </tr>
        @foreach(($payload['subjects'] ?? []) as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                @foreach(($row['terms'] ?? []) as $tr)
                    <td class="center">@if(($tr['status'] ?? '') === 'missing')<span class="missing">—</span>@else{{ $fmt($tr['total'] ?? null) }}@endif</td>
                @endforeach
                <td class="center">{{ $row['incomplete'] ? '—' : $fmt($row['annual_average'] ?? null) }}</td>
                <td class="center">{{ $row['grade'] ?? '—' }}</td>
            </tr>
        @endforeach
    </table>
    <p class="muted">Weighting: {{ ($payload['weighting'] ?? '') === 'weighted' ? 'school term weights applied (all terms present).' : 'average of available terms only — missing terms are not counted as zero.' }}</p>
@else
    <h2>Academic history</h2>
    @foreach(($payload['history']['terms'] ?? []) as $term)
        <div style="margin-top:8px; page-break-inside:avoid;">
            <strong>{{ $term['label'] ?? ('Grade '.($term['grade_number'] ?? '').' Term '.($term['term_sequence'] ?? '')) }}</strong>
            @if(($term['status'] ?? '') === 'missing')
                <div class="missing">No record available for this term.</div>
            @else
                <table class="grid">
                    <tr><th>Subject</th><th class="right" style="width:60px">Mark</th><th style="width:48px">Grade</th></tr>
                    @foreach(($term['entries'] ?? []) as $e)
                        <tr>
                            <td>{{ $e['subject'] }}</td>
                            <td class="right">{{ $fmt($e['mark'] ?? null) }}</td>
                            <td class="center">{{ $e['grade'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </table>
                <div class="muted">Term average: {{ $fmt($term['average'] ?? null) }}</div>
            @endif
        </div>
    @endforeach
@endif

<h2>Summary</h2>
<table class="kpi">
    <tr>
        <td><div class="lbl">Average</div><div class="val">{{ $fmt($summary['average'] ?? null) }}</div></td>
        <td><div class="lbl">Grade</div><div class="val">{{ $summary['grade'] ?? '—' }}</div></td>
        <td><div class="lbl">{{ $report->type === 'termly' && !empty($summary['position']) ? 'Position' : 'Subjects' }}</div><div class="val">
            @if($report->type === 'termly' && !empty($summary['position']))
                {{ $summary['position'] }} / {{ $summary['out_of'] ?? '—' }}
            @else
                {{ $summary['recorded_count'] ?? $summary['subjects_count'] ?? ($summary['terms_recorded'] ?? '—') }}
            @endif
        </div></td>
        <td><div class="lbl">Attendance</div><div class="val">{{ $fmt(($payload['attendance']['percentage'] ?? null)) }}@if(isset($payload['attendance']['percentage']))%@endif</div></td>
    </tr>
</table>

@if(!empty($cfg['show_attendance']) && !empty($payload['attendance']))
    <p>Days present {{ $payload['attendance']['present'] ?? 0 }}, absent {{ $payload['attendance']['absent'] ?? 0 }}, excused {{ $payload['attendance']['excused'] ?? 0 }}.</p>
@endif

@if($payload['behaviour'] || $payload['participation'])
    <p><strong>Behaviour:</strong> {{ $payload['behaviour'] ?? '—' }} &nbsp; <strong>Participation:</strong> {{ $payload['participation'] ?? '—' }}</p>
@endif

@if(!empty($cfg['show_comments']))
    <h2>Comments</h2>
    <div class="comment"><strong>Class teacher:</strong><br>{{ $report->class_teacher_comment ?: ($payload['comments']['class_teacher'] ?? '—') }}</div>
    <div class="comment"><strong>Headteacher:</strong><br>{{ $report->headteacher_comment ?: ($payload['comments']['headteacher'] ?? '—') }}</div>
@endif

@if($report->promotion_status || $report->promotion_decision)
    <p><strong>Promotion:</strong> {{ $report->promotion_status ?? '' }} {{ $report->promotion_decision ? '— '.$report->promotion_decision : '' }}</p>
@endif

@if(!empty($cfg['show_scale']) && !empty($payload['scale']))
    <h2>Grading scale</h2>
    <table class="grid">
        <tr><th>Grade</th><th>Minimum</th><th>Description</th></tr>
        @foreach($payload['scale'] as $band)
            <tr><td>{{ $band['letter'] }}</td><td>{{ $fmt($band['min'] ?? null) }}</td><td>{{ $band['remark'] ?? '' }}</td></tr>
        @endforeach
    </table>
@endif

@if(!empty($cfg['show_signatures']))
<table class="sign">
    <tr>
        @forelse($signatories as $s)
            <td><div class="line"><strong>{{ $s['name'] }}</strong><br>{{ $s['title'] ?? '' }}</div></td>
        @empty
            <td><div class="line">Class teacher</div></td>
            <td><div class="line">Headteacher</div></td>
        @endforelse
        <td>
            @if(!empty($cfg['show_stamp']) && $stamp)<img src="{{ $stamp }}" style="max-width:86px;max-height:86px"><br>@endif
            <div class="line">Official school stamp</div>
        </td>
    </tr>
</table>
@endif

<table class="foot">
    <tr>
        <td>
            {{ $cfg['footer'] ?? $disclaimer }}<br>
            Verify: {{ $verifyUrl }} · {{ $report->reference }}
        </td>
        <td style="width:90px; text-align:right"><img src="{{ $qr }}" style="width:80px;height:80px"></td>
    </tr>
</table>
</body>
</html>
