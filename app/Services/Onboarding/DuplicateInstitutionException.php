<?php

namespace App\Services\Onboarding;

use RuntimeException;

/** Raised when a registration is certainly an institution that is already on the platform. */
class DuplicateInstitutionException extends RuntimeException
{
    /** @param list<string> $reasons */
    public function __construct(public array $reasons)
    {
        parent::__construct('This institution appears to be registered already.');
    }
}