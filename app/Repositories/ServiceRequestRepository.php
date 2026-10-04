<?php

namespace App\Repositories;

use App\Models\ServiceRequest;

/**
 * Persistence layer for Service Request records.
 *
 * Responsible only for storing and retrieving Service Requests. Validation
 * and Resident eligibility remain the responsibility of the service layer.
 */
class ServiceRequestRepository
{
    /**
     * Persist a Service Request and return it with its database-generated identifier.
     */
    public function save(ServiceRequest $serviceRequest): ServiceRequest
    {
        $serviceRequest->save();

        return $serviceRequest;
    }

    /**
     * Retrieve a Service Request by identifier, or null when no such record exists.
     */
    public function findById(int $id): ?ServiceRequest
    {
        return ServiceRequest::find($id);
    }

    /**
     * Persist a new status for one existing Service Request.
     *
     * Only the status column of the requested record changes. Whether the
     * transition is allowed is decided by ServiceRequestStatusService before
     * this method is called. Returns true when a Service Request row was updated.
     */
    public function updateStatus(int $id, string $status): bool
    {
        return ServiceRequest::query()
            ->whereKey($id)
            ->update(['status' => $status]) === 1;
    }
}
