<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/** History tables are write-once: application code can insert but never update or delete a row. */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        static::updating(fn (Model $m) => throw new \LogicException(class_basename($m).' is append-only.'));
        static::deleting(fn (Model $m) => throw new \LogicException(class_basename($m).' is append-only.'));
    }
}
