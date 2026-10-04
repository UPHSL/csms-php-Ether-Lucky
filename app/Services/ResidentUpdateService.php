<?php

namespace App\Services;

use App\Repositories\ResidentRepository;
use Illuminate\Support\Arr;

class ResidentUpdateService
{
    /**
     * Resident fields that the general update operation may change.
     * The ID and status are intentionally excluded.
     */
    public const EDITABLE_FIELDS = [
        'first_name',
        'last_name',
        'address',
        'contact_number',
        'email',
    ];

    public function __construct(
        private ResidentValidator $validator,
        private ResidentRepository $repository
    ) {}

    public function updateResident(
        int $residentId,
        array $changes
    ): ResidentUpdateResult {
        $resident =
            $this->repository->findById(
                $residentId
            );

        if ($resident === null) {
            return ResidentUpdateResult::notFound();
        }

        $resident->fill(
            Arr::only(
                $changes,
                self::EDITABLE_FIELDS
            )
        );

        $validation =
            $this->validator->validate(
                $resident
            );

        if ($validation->fails()) {
            return ResidentUpdateResult::validationFailed(
                $validation
                    ->errors()
                    ->keys()
            );
        }

        $updatedResident =
            $this->repository->update(
                $resident
            );

        return ResidentUpdateResult::successful(
            $updatedResident
        );
    }
}
