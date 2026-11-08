<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Invoices that can still be paid (issued or part paid), whatever their due date. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partial', 'overdue']);
    }

    /** Open and past the due date. */
    public function scopeLate(Builder $query): Builder
    {
        return $query->open()->whereNotNull('due_on')->whereDate('due_on', '<', now()->toDateString());
    }

    public function balance(): int
    {
        if ($this->status === 'cancelled') {
            return 0;
        }

        return max(0, (int) $this->total_amount - (int) $this->amount_paid);
    }

    /** What the screens show: an open invoice past its due date reads "overdue" without a nightly job having to flip it. */
    public function displayStatus(): string
    {
        if (in_array($this->status, ['issued', 'partial'], true) && $this->due_on && $this->due_on->lt(today())) {
            return 'overdue';
        }

        return (string) $this->status;
    }
}
