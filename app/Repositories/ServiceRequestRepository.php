<?php

namespace App\Repositories;

use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Retrieve every persisted Service Request, newest request date first,
     * then highest ID first.
     */
    public function findAll(): Collection
    {
        return $this->orderedQuery()->get();
    }

    /**
     * Retrieve Service Requests matching the supplied conditions, using the
     * same ordering as findAll().
     *
     * A search term matches when it appears, ignoring letter case, anywhere in
     * the service type or the description. A status restricts the result to
     * that exact status. When both are supplied a Service Request must satisfy
     * both. A null condition is not applied. All values are bound as query
     * parameters and the filtering is performed by the database.
     */
    public function search(
        ?string $searchTerm,
        ?string $status
    ): Collection {
        $query = $this->orderedQuery();

        if ($searchTerm !== null) {
            $pattern = '%'.$searchTerm.'%';

            $query->where(
                function (Builder $query) use ($pattern) {
                    $query
                        ->whereRaw(
                            'LOWER(service_type) LIKE LOWER(?)',
                            [$pattern]
                        )
                        ->orWhereRaw(
                            'LOWER(description) LIKE LOWER(?)',
                            [$pattern]
                        );
                }
            );
        }

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    /**
     * Base query carrying the one ordering shared by every Service Request
     * read operation: date_requested DESC, then id DESC.
     */
    private function orderedQuery(): Builder
    {
        return ServiceRequest::query()
            ->orderBy('date_requested', 'desc')
            ->orderBy('id', 'desc');
    }
}
