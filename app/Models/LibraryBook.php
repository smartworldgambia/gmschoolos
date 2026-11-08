<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryBook extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    public function loans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class);
    }

    public function copies(): HasMany
    {
        return $this->hasMany(LibraryCopy::class);
    }

    /** Keep the catalogue counters equal to the real copies (withdrawn, lost and damaged copies are not lendable). */
    public function syncCounts(): void
    {
        $total = $this->copies()->whereNotIn('status', ['withdrawn', 'lost'])->count();
        $available = $this->copies()->where('status', 'available')->count();
        $this->forceFill([
            'copies_total' => $total,
            'copies_available' => $available,
            'status' => $available > 0 ? 'available' : 'loaned_out',
        ])->save();
    }
}
