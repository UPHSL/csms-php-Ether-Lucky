<?php

namespace App\Services;

use App\Repositories\ResidentRepository;
use App\Repositories\ServiceRequestRepository;

/**
 * Composes the dashboard summary from aggregate queries.
 *
 * Counting, grouping, ordering and limiting are performed by the
 * repositories at the database. This service only reads: it combines the
 * aggregate results and derives the Open Service Requests metric.
 */
class DashboardService
{
    /**
     * How many of the most recent Service Requests the dashboard shows.
     */
    public const RECENT_SERVICE_REQUEST_LIMIT = 5;

    public function __construct(
        private ResidentRepository $residentRepository,
        private ServiceRequestRepository $serviceRequestRepository
    ) {}

    public function getSummary(): DashboardSummary
    {
        $residentCounts =
            $this->residentRepository->countByStatus();

        $serviceRequestCounts =
            $this->serviceRequestRepository->countByStatus();

        $pending = $serviceRequestCounts['Pending'] ?? 0;
        $inProgress = $serviceRequestCounts['In Progress'] ?? 0;

        return new DashboardSummary(
            totalResidents: $this->residentRepository->countAll(),
            activeResidents: $residentCounts['Active'] ?? 0,
            inactiveResidents: $residentCounts['Inactive'] ?? 0,
            totalServiceRequests: $this->serviceRequestRepository->countAll(),
            pendingServiceRequests: $pending,
            inProgressServiceRequests: $inProgress,
            completedServiceRequests: $serviceRequestCounts['Completed'] ?? 0,
            cancelledServiceRequests: $serviceRequestCounts['Cancelled'] ?? 0,
            openServiceRequests: $pending + $inProgress,
            recentServiceRequests: $this->serviceRequestRepository->findRecent(
                self::RECENT_SERVICE_REQUEST_LIMIT
            )
        );
    }
}
