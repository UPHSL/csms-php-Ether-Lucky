<?php

namespace App\Services;

use App\Repositories\ResidentRepository;

class ResidentDeactivationService
{
    public function __construct(
        private ResidentRepository $repository
    ) {}

    public function deactivateResident(
        int $residentId
    ): ResidentDeactivationResult {
        $resident =
            $this->repository->findById(
                $residentId
            );

        if ($resident === null) {
            return ResidentDeactivationResult::notFound();
        }

        if ($resident->status === 'Inactive') {
            return ResidentDeactivationResult::alreadyInactive(
                $resident
            );
        }

        $this->repository->deactivateById(
            $residentId
        );

        return ResidentDeactivationResult::deactivated(
            $this->repository->findById(
                $residentId
            )
        );
    }
}
