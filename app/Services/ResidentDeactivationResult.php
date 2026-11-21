<?php

namespace App\Services;

use App\Models\Resident;

class ResidentDeactivationResult
{
    public function __construct(
        public bool $success,
        public ?Resident $resident = null,
        public bool $alreadyInactive = false,
        public bool $notFound = false
    ) {}

    public static function deactivated(
        Resident $resident
    ): self {
        return new self(
            success: true,
            resident: $resident
        );
    }

    public static function alreadyInactive(
        Resident $resident
    ): self {
        return new self(
            success: true,
            resident: $resident,
            alreadyInactive: true
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
