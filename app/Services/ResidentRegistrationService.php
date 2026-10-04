<?php

namespace App\Services;

use App\Models\Resident;
use App\Repositories\ResidentRepository;

class ResidentRegistrationService
{
    public function __construct(
        private ResidentValidator $validator,
        private ResidentRepository $repository
    ) {}

    public function registerResident(
        Resident $resident
    ): ResidentRegistrationResult {
        $validation =
            $this->validator->validate(
                $resident
            );

        if ($validation->fails()) {
            return ResidentRegistrationResult::failed(
                $validation
                    ->errors()
                    ->keys()
            );
        }

        $persistedResident =
            $this->repository->save(
                $resident
            );

        return ResidentRegistrationResult::successful(
            $persistedResident
        );
    }
}
