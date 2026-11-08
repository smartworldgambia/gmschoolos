<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Text encrypted at rest with the application key (safeguarding and welfare records).
 * Rows written before encryption was introduced are plain text: they read back unchanged and are encrypted the next time
 * they are saved. An unreadable value (changed APP_KEY) reads as null, never as the ciphertext.
 */
class SensitiveText implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (! str_starts_with((string) $value, 'eyJ')) {
            return (string) $value;   // legacy plain text (Laravel payloads are base64 JSON and always start with "eyJ")
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return null;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return Crypt::encryptString((string) $value);
    }
}
