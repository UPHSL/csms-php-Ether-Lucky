<?php

namespace App\Services;

use App\Models\ServiceRequest;

class ServiceRequestStatusResult
{
    public function __construct(
        public bool $success,
        public ?ServiceRequest $serviceRequest = null,
        public bool $notFound = false,
        public bool $unsupportedStatus = false,
        public bool $invalidTransition = false
    ) {}

    public static function successful(
        ServiceRequest $serviceRequest
    ): self {
        return new self(
            success: true,
            serviceRequest: $serviceRequest
        );
    }

    public static function notFound(): self
    {
        return new self(
            success: false,
            notFound: true
        );
    }

    public static function unsupportedStatus(
        ServiceRequest $serviceRequest
    ): self {
        return new self(
            success: false,
            serviceRequest: $serviceRequest,
            unsupportedStatus: true
        );
    }

    public static function invalidTransition(
        ServiceRequest $serviceRequest
    ): self {
        return new self(
            success: false,
            serviceRequest: $serviceRequest,
            invalidTransition: true
        );
    }
}
