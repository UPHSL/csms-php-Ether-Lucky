<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Repositories\ServiceRequestRepository;
use Illuminate\Support\Collection;

/**
 * Read side of Service Request management: listing, viewing one record,
 * text search and status filtering.
 *
 * Every operation here only reads. The queries themselves are executed by
 * ServiceRequestRepository; this service normalizes the search input and
 * decides which repository query applies.
 */
class ServiceRequestQueryService
{
    public function __construct(
        private ServiceRequestRepository $repository
    ) {}

    /**
     * The statuses a Service Request query may be filtered by. They are the
     * statuses defined by the Service Request workflow.
     *
     * @return list<string>
     */
    public static function supportedStatuses(): array
    {
        return array_keys(
            ServiceRequestStatusService::ALLOWED_TRANSITIONS
        );
    }

    public function listServiceRequests(): Collection
    {
        return $this->repository->findAll();
    }

    /**
     * Return the Service Request with the given ID, or null when none exists.
     */
    public function viewServiceRequest(
        int $serviceRequestId
    ): ?ServiceRequest {
        return $this->repository->findById(
            $serviceRequestId
        );
    }

    /**
     * Search by text and/or status.
     *
     * A blank search term and a blank status filter each mean "no condition",
     * so a call with both blank behaves as a normal listing.
     *
     * @throws UnsupportedStatusFilterException when a non-blank status filter
     *                                          is not a supported status
     */
    public function searchServiceRequests(
        ?string $searchTerm,
        ?string $statusFilter = null
    ): Collection {
        $normalizedSearchTerm =
            trim(
                $searchTerm ?? ''
            );

        $normalizedStatus =
            trim(
                $statusFilter ?? ''
            );

        if (
            $normalizedStatus !== ''
            && ! in_array($normalizedStatus, self::supportedStatuses(), true)
        ) {
            throw UnsupportedStatusFilterException::forStatus(
                $normalizedStatus
            );
        }

        if (
            $normalizedSearchTerm === ''
            && $normalizedStatus === ''
        ) {
            return $this->listServiceRequests();
        }

        return $this->repository->search(
            $normalizedSearchTerm === '' ? null : $normalizedSearchTerm,
            $normalizedStatus === '' ? null : $normalizedStatus
        );
    }
}
