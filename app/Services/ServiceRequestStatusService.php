<?php

namespace App\Services;

use App\Repositories\ServiceRequestRepository;

class ServiceRequestStatusService
{
    /**
     * Every supported Service Request status, mapped to the statuses it may
     * move to next. Completed and Cancelled are terminal.
     */
    public const ALLOWED_TRANSITIONS = [
        'Pending' => ['In Progress', 'Cancelled'],
        'In Progress' => ['Completed', 'Cancelled'],
        'Completed' => [],
        'Cancelled' => [],
    ];

    public function __construct(
        private ServiceRequestRepository $repository
    ) {}

    public function changeStatus(
        int $serviceRequestId,
        string $requestedStatus
    ): ServiceRequestStatusResult {
        $serviceRequest =
            $this->repository->findById(
                $serviceRequestId
            );

        if ($serviceRequest === null) {
            return ServiceRequestStatusResult::notFound();
        }

        if (! array_key_exists($requestedStatus, self::ALLOWED_TRANSITIONS)) {
            return ServiceRequestStatusResult::unsupportedStatus(
                $serviceRequest
            );
        }

        if (! $this->isAllowedTransition($serviceRequest->status, $requestedStatus)) {
            return ServiceRequestStatusResult::invalidTransition(
                $serviceRequest
            );
        }

        $this->repository->updateStatus(
            $serviceRequestId,
            $requestedStatus
        );

        return ServiceRequestStatusResult::successful(
            $this->repository->findById(
                $serviceRequestId
            )
        );
    }

    public function isAllowedTransition(
        string $currentStatus,
        string $requestedStatus
    ): bool {
        return in_array(
            $requestedStatus,
            self::ALLOWED_TRANSITIONS[$currentStatus] ?? [],
            true
        );
    }
}
