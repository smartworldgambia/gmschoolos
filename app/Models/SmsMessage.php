<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsMessage extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime', 'delivered_at' => 'datetime', 'failed_at' => 'datetime', 'cost_amount' => 'decimal:4'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** +2207123456 -> +220***456 : the log never needs to show a whole number. */
    public function maskedPhone(): string
    {
        $p = (string) $this->to_phone;

        return strlen($p) > 7 ? substr($p, 0, 4).str_repeat('*', strlen($p) - 7).substr($p, -3) : '***';
    }
}
