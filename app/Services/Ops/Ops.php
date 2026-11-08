<?php

namespace App\Services\Ops;

use App\Models\ActivityLog;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Small shared helpers for the Phase 9 operations modules. */
class Ops
{
    /** Audit trail entry for the acting user in the current school. Never put secrets or welfare detail in $props. */
    public static function audit(string $event, string $description, array $props = []): void
    {
        $tenant = app(TenantContext::class);
        $schoolId = $tenant->id() ?? auth()->user()?->school_id;
        if (! $schoolId) {
            return;
        }

        ActivityLog::query()->create([
            'school_id' => $schoolId,
            'user_id' => auth()->id(),
            'event' => $event,
            'description' => mb_substr($description, 0, 250),
            'properties' => $props ?: null,
        ]);
    }

    /**
     * Next number "PREFIX-YYYY-00001" for a school-scoped table (per school, per year, never reused while rows exist).
     * Call inside the transaction that inserts the row; the unique (school_id, number) index is the final guard.
     */
    public static function nextNumber(string $table, string $prefix, string $column = 'number', ?int $year = null): string
    {
        $schoolId = (int) (app(TenantContext::class)->id() ?? auth()->user()?->school_id);
        $year ??= (int) now()->format('Y');
        $stem = $prefix.'-'.$year.'-';

        $last = DB::table($table)->where('school_id', $schoolId)->where($column, 'like', $stem.'%')->orderByDesc($column)->value($column);
        $n = $last ? ((int) substr((string) $last, strlen($stem))) + 1 : 1;

        return $stem.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    /** Run a numbered insert, retrying if two requests picked the same number at once. */
    public static function numbered(callable $insert, int $tries = 4): mixed
    {
        for ($i = 1; ; $i++) {
            try {
                return DB::transaction($insert);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($i >= $tries) {
                    throw $e;
                }
            }
        }
    }

    /** CSV download. Cells that a spreadsheet would execute as a formula are neutralised. */
    public static function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], array_values((array) $row)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    public static function cell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($text) ? "'".$text : $text;
    }

    /**
     * In-app + e-mail notice to people of the current school. SMS is never offered for operations notices: they carry no
     * reliable short text. Silently does nothing when there is nobody to tell.
     *
     * @param  iterable<\App\Models\User>  $users
     */
    public static function notify(iterable $users, string $title, string $body, ?string $url = null, string $level = 'info', string $category = 'announcement'): void
    {
        $list = collect($users)->filter()->unique('id')->values();
        if ($list->isEmpty()) {
            return;
        }
        \Illuminate\Support\Facades\Notification::send($list, new \App\Notifications\SchoolAnnouncement($title, $body, $url, $level, $category, ['in_app', 'email']));
    }

    /** Everyone in this school who holds the permission (direct or through a role). */
    public static function usersWith(string $permission, ?int $exceptId = null): \Illuminate\Support\Collection
    {
        $schoolId = (int) (app(TenantContext::class)->id() ?? auth()->user()?->school_id);

        try {
            return \App\Models\User::query()->permission($permission)->where('school_id', $schoolId)->where('users.id', '!=', $exceptId ?? 0)->get();
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return collect();
        }
    }

    /** Round half up for percentages of whole Dalasi. */
    public static function percentOf(int $amount, float $percent): int
    {
        return (int) round($amount * $percent / 100, 0, PHP_ROUND_HALF_UP);
    }
}
