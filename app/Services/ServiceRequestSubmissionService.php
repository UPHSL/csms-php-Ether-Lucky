<?php

namespace App\Services;

use App\Models\ServiceRequest;
use App\Repositories\ResidentRepository;
use App\Repositories\ServiceRequestRepository;

class ServiceRequestSubmissionService
{
    public function __construct(
        private ServiceRequestValidator $validator,
        private ResidentRepository $residentRepository,
        private ServiceRequestRepository $serviceRequestRepository
    ) {}

    public function submitServiceRequest(
        ServiceRequest $serviceRequest
    ): ServiceRequestSubmissionResult {
        $validation =
            $this->validator->validate(
                $serviceRequest
            );

        if ($validation->fails()) {
            return ServiceRequestSubmissionResult::validationFailed(
                $validation
                    ->errors()
                    ->keys()
            );
        }

        $resident =
            $this->residentRepository->findById(
                $serviceRequest->resident_id
            );

        if ($resident === null) {
            return ServiceRequestSubmissionResult::residentNotFound();
        }

        if ($resident->status !== 'Active') {
            return ServiceRequestSubmissionResult::residentInactive();
        }

        $persistedServiceRequest =
            $this->serviceRequestRepository->save(
                $serviceRequest
            );

        return ServiceRequestSubmissionResult::successful(
            $persistedServiceRequest
        );
    }
}
