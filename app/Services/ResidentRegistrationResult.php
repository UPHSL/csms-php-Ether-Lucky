<?php

namespace App\Services;

use App\Models\Resident;

class ResidentRegistrationResult
{
    public function __construct(
        public bool $success,
        public ?Resident $resident = null,
        public array $errors = []
    ) {}

    public static function successful(
        Resident $resident
    ): self {
        return new self(
            success: true,
            resident: $resident,
            errors: []
        );
    }

    public static function failed(
        array $errors
    ): self {
        return new self(
            success: false,
            resident: null,
            errors: $errors
        );
    }
}
