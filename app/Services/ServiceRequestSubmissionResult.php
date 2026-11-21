<?php

namespace App\Services;

use App\Models\ServiceRequest;

class ServiceRequestSubmissionResult
{
    public function __construct(
        public bool $success,
        public ?ServiceRequest $serviceRequest = null,
        public array $errors = [],
        public bool $residentNotFound = false,
        public bool $residentInactive = false
    ) {}

    public static function successful(
        ServiceRequest $serviceRequest
    ): self {
        return new self(
            success: true,
            serviceRequest: $serviceRequest
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

    public static function residentNotFound(): self
    {
        return new self(
            success: false,
            residentNotFound: true
        );
    }

    public static function residentInactive(): self
    {
        return new self(
            success: false,
            residentInactive: true
        );
    }
}
