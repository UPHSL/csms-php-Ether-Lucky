<?php

namespace App\Services;

use App\Models\Resident;

class ResidentUpdateResult
{
    public function __construct(
        public bool $success,
        public ?Resident $resident = null,
        public array $errors = [],
        public bool $notFound = false
    ) {}

    public static function successful(
        Resident $resident
    ): self {
        return new self(
            success: true,
            resident: $resident
        );
    }

    public static function validationFailed(
        array $errors
    ): self {
        return new self(
            success: false,
            errors: $errors
        );
    }

    public static function notFound(): self
    {
        return new self(
            success: false,
            notFound: true
        );
    }
}
