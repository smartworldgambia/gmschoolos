<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule refusal in the academic modules. The message is a stable translation key (`err.*`) that the
 * front end renders in the user's language; it is never free text and never contains another tenant's data.
 */
class AcademicRuleException extends RuntimeException
{
    public static function because(string $key): self
    {
        return new self($key);
    }
}
