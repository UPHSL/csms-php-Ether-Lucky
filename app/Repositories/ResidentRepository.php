<?php

namespace App\Repositories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Collection;

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

    /**
     * Retrieve every persisted Resident ordered by last name, first name, then ID.
     */
    public function findAll(): Collection
    {
        return Resident::query()
            ->orderByRaw(
                'LOWER(last_name) ASC'
            )
            ->orderByRaw(
                'LOWER(first_name) ASC'
            )
            ->orderBy(
                'id',
                'asc'
            )
            ->get();
    }

    /**
     * Retrieve Residents whose first or last name contains the search term,
     * ignoring letter case, using the same ordering as findAll().
     */
    public function searchByName(
        string $searchTerm
    ): Collection {
        $pattern =
            '%'.$searchTerm.'%';

        return Resident::query()
            ->where(
                function ($query) use ($pattern) {
                    $query
                        ->whereRaw(
                            'LOWER(first_name) LIKE LOWER(?)',
                            [$pattern]
                        )
                        ->orWhereRaw(
                            'LOWER(last_name) LIKE LOWER(?)',
                            [$pattern]
                        );
                }
            )
            ->orderByRaw(
                'LOWER(last_name) ASC'
            )
            ->orderByRaw(
                'LOWER(first_name) ASC'
            )
            ->orderBy(
                'id',
                'asc'
            )
            ->get();
    }
}
