<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * A read-only snapshot of the current operational state of the CSMS.
 */
class DashboardSummary
{
    public function __construct(
        public readonly int $totalResidents,
        public readonly int $activeResidents,
        public readonly int $inactiveResidents,
        public readonly int $totalServiceRequests,
        public readonly int $pendingServiceRequests,
        public readonly int $inProgressServiceRequests,
        public readonly int $completedServiceRequests,
        public readonly int $cancelledServiceRequests,
        public readonly int $openServiceRequests,
        public readonly Collection $recentServiceRequests
    ) {}
}
