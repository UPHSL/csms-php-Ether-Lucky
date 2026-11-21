<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Repositories\ResidentRepository;
use App\Repositories\ServiceRequestRepository;
use App\Services\ResidentDeactivationService;
use App\Services\ServiceRequestStatusService;
use App\Services\ServiceRequestSubmissionService;
use App\Services\ServiceRequestValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResidentRepository $residentRepository;

    private ServiceRequestRepository $serviceRequestRepository;

    private ServiceRequestStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->residentRepository = new ResidentRepository;
        $this->serviceRequestRepository = new ServiceRequestRepository;
        $this->service = new ServiceRequestStatusService($this->serviceRequestRepository);
    }

    /**
     * Submit a new Pending Service Request through the T09 workflow.
     */
    private function submitServiceRequest(array $overrides = []): ServiceRequest
    {
        $resident = $this->residentRepository->save(new Resident([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ]));

        $submission = new ServiceRequestSubmissionService(
            new ServiceRequestValidator,
            $this->residentRepository,
            $this->serviceRequestRepository
        );

        $result = $submission->submitServiceRequest(new ServiceRequest(array_merge([
            'resident_id' => $resident->id,
            'service_type' => 'Barangay Clearance',
            'description' => 'Employment requirement',
            'date_requested' => '2026-09-15',
        ], $overrides)));

        $this->assertTrue($result->success);

        return $result->serviceRequest;
    }

    /**
     * Submit a Service Request and move it through the given T10 transitions.
     */
    private function serviceRequestWithStatus(string ...$transitions): ServiceRequest
    {
        $serviceRequest = $this->submitServiceRequest();

        foreach ($transitions as $status) {
            $this->assertTrue($this->service->changeStatus($serviceRequest->id, $status)->success);
        }

        return $this->serviceRequestRepository->findById($serviceRequest->id);
    }

    private function storedStatus(int $id): string
    {
        return $this->serviceRequestRepository->findById($id)->status;
    }

    public function test_pending_can_move_to_in_progress(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus();

        $result = $this->service->changeStatus($serviceRequest->id, 'In Progress');

        $this->assertTrue($result->success);
        $this->assertSame('In Progress', $result->serviceRequest->status);
        $this->assertSame('In Progress', $this->storedStatus($serviceRequest->id));
    }

    public function test_pending_can_move_to_cancelled(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus();

        $result = $this->service->changeStatus($serviceRequest->id, 'Cancelled');

        $this->assertTrue($result->success);
        $this->assertSame('Cancelled', $this->storedStatus($serviceRequest->id));
    }

    public function test_in_progress_can_move_to_completed(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus('In Progress');

        $result = $this->service->changeStatus($serviceRequest->id, 'Completed');

        $this->assertTrue($result->success);
        $this->assertSame('Completed', $this->storedStatus($serviceRequest->id));
    }

    public function test_in_progress_can_move_to_cancelled(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus('In Progress');

        $result = $this->service->changeStatus($serviceRequest->id, 'Cancelled');

        $this->assertTrue($result->success);
        $this->assertSame('Cancelled', $this->storedStatus($serviceRequest->id));
    }

    public function test_pending_cannot_move_directly_to_completed(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus();

        $result = $this->service->changeStatus($serviceRequest->id, 'Completed');

        $this->assertFalse($result->success);
        $this->assertTrue($result->invalidTransition);
        $this->assertSame('Pending', $this->storedStatus($serviceRequest->id));
    }

    public function test_in_progress_cannot_return_to_pending(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus('In Progress');

        $result = $this->service->changeStatus($serviceRequest->id, 'Pending');

        $this->assertFalse($result->success);
        $this->assertTrue($result->invalidTransition);
        $this->assertSame('In Progress', $this->storedStatus($serviceRequest->id));
    }

    public function test_completed_is_terminal(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus('In Progress', 'Completed');

        foreach (['Pending', 'In Progress', 'Cancelled'] as $status) {
            $result = $this->service->changeStatus($serviceRequest->id, $status);

            $this->assertFalse($result->success);
            $this->assertTrue($result->invalidTransition);
            $this->assertSame('Completed', $this->storedStatus($serviceRequest->id));
        }
    }

    public function test_cancelled_is_terminal(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus('Cancelled');

        foreach (['Pending', 'In Progress', 'Completed'] as $status) {
            $result = $this->service->changeStatus($serviceRequest->id, $status);

            $this->assertFalse($result->success);
            $this->assertTrue($result->invalidTransition);
            $this->assertSame('Cancelled', $this->storedStatus($serviceRequest->id));
        }
    }

    public function test_unsupported_status_is_rejected(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus();

        foreach (['Approved', 'Rejected', 'Processing', 'Done', 'in progress', ''] as $status) {
            $result = $this->service->changeStatus($serviceRequest->id, $status);

            $this->assertFalse($result->success);
            $this->assertTrue($result->unsupportedStatus);
            $this->assertFalse($result->invalidTransition);
            $this->assertSame('Pending', $this->storedStatus($serviceRequest->id));
        }
    }

    public function test_nonexistent_service_request_is_handled_safely(): void
    {
        $existing = $this->serviceRequestWithStatus();
        $countBefore = ServiceRequest::query()->count();

        $result = $this->service->changeStatus(999999, 'In Progress');

        $this->assertFalse($result->success);
        $this->assertTrue($result->notFound);
        $this->assertNull($result->serviceRequest);
        $this->assertSame($countBefore, ServiceRequest::query()->count());
        $this->assertNull($this->serviceRequestRepository->findById(999999));
        $this->assertSame('Pending', $this->storedStatus($existing->id));
    }

    public function test_successful_transition_preserves_service_request_information(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus();

        $this->service->changeStatus($serviceRequest->id, 'In Progress');

        $stored = $this->serviceRequestRepository->findById($serviceRequest->id);

        $this->assertSame($serviceRequest->id, $stored->id);
        $this->assertSame($serviceRequest->resident_id, $stored->resident_id);
        $this->assertSame('Barangay Clearance', $stored->service_type);
        $this->assertSame('Employment requirement', $stored->description);
        $this->assertSame('2026-09-15', $stored->date_requested);
        $this->assertSame('In Progress', $stored->status);
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_invalid_transition_does_not_modify_persistence(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus('In Progress');
        $before = $this->serviceRequestRepository->findById($serviceRequest->id)->getAttributes();

        $result = $this->service->changeStatus($serviceRequest->id, 'Pending');

        $after = $this->serviceRequestRepository->findById($serviceRequest->id)->getAttributes();

        $this->assertFalse($result->success);
        $this->assertSame($before, $after);
    }

    public function test_same_status_request_is_rejected(): void
    {
        $pending = $this->serviceRequestWithStatus();
        $inProgress = $this->serviceRequestWithStatus('In Progress');

        $pendingResult = $this->service->changeStatus($pending->id, 'Pending');
        $inProgressResult = $this->service->changeStatus($inProgress->id, 'In Progress');

        $this->assertFalse($pendingResult->success);
        $this->assertTrue($pendingResult->invalidTransition);
        $this->assertFalse($inProgressResult->success);
        $this->assertTrue($inProgressResult->invalidTransition);
        $this->assertSame('Pending', $this->storedStatus($pending->id));
        $this->assertSame('In Progress', $this->storedStatus($inProgress->id));
    }

    public function test_existing_request_can_still_be_processed_after_its_resident_is_deactivated(): void
    {
        $serviceRequest = $this->serviceRequestWithStatus();
        $residentId = $serviceRequest->resident_id;

        (new ResidentDeactivationService($this->residentRepository))->deactivateResident($residentId);

        $toInProgress = $this->service->changeStatus($serviceRequest->id, 'In Progress');
        $toCompleted = $this->service->changeStatus($serviceRequest->id, 'Completed');

        $this->assertTrue($toInProgress->success);
        $this->assertTrue($toCompleted->success);
        $this->assertSame('Completed', (new ServiceRequestRepository)->findById($serviceRequest->id)->status);

        $resident = $this->residentRepository->findById($residentId);

        $this->assertSame('Inactive', $resident->status);
        $this->assertSame('Juan', $resident->first_name);
        $this->assertSame('09171234567', $resident->contact_number);
    }
}
