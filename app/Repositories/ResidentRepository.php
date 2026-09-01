<?php

namespace App\Repositories;

use App\Models\Resident;

/**
 * Persistence layer for Resident records.
 *
 * Responsible only for storing and retrieving Residents. Validation remains
 * the responsibility of App\Services\ResidentValidator.
 */
class ResidentRepository
{
    /**
     * Persist a Resident and return it with its database-generated identifier.
     */
    public function save(Resident $resident): Resident
    {
        $resident->save();

        return $resident;
    }

    /**
     * Retrieve a Resident by identifier, or null when no such record exists.
     */
    public function findById(int $id): ?Resident
    {
        return Resident::find($id);
    }
}
