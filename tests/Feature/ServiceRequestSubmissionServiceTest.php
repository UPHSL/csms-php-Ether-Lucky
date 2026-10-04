<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Repositories\ResidentRepository;
use App\Repositories\ServiceRequestRepository;
use App\Services\ServiceRequestSubmissionService;
use App\Services\ServiceRequestValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResidentRepository $residentRepository;

    private ServiceRequestRepository $serviceRequestRepository;

    private ServiceRequestSubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->residentRepository = new ResidentRepository;
        $this->serviceRequestRepository = new ServiceRequestRepository;
        $this->service = new ServiceRequestSubmissionService(
            new ServiceRequestValidator,
            $this->residentRepository,
            $this->serviceRequestRepository
        );
    }

    private function persistResident(array $overrides = []): Resident
    {
        return $this->residentRepository->save(new Resident(array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ], $overrides)));
    }

    private function makeServiceRequest(int $residentId, array $overrides = []): ServiceRequest
    {
        return new ServiceRequest(array_merge([
            'resident_id' => $residentId,
            'service_type' => 'Barangay Clearance',
            'description' => 'Requesting barangay clearance for employment requirements.',
            'date_requested' => '2026-09-15',
        ], $overrides));
    }

    public function test_valid_service_request_submission_succeeds(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->submitServiceRequest($this->makeServiceRequest($resident->id));

        $this->assertTrue($result->success);
        $this->assertNotNull($result->serviceRequest);
        $this->assertSame([], $result->errors);
        $this->assertFalse($result->residentNotFound);
        $this->assertFalse($result->residentInactive);
    }

    public function test_submitted_service_request_receives_a_generated_id(): void
    {
        $resident = $this->persistResident();
        $serviceRequest = $this->makeServiceRequest($resident->id);

        $this->assertNull($serviceRequest->id);

        $result = $this->service->submitServiceRequest($serviceRequest);

        $this->assertIsInt($result->serviceRequest->id);
        $this->assertGreaterThan(0, $result->serviceRequest->id);
    }

    public function test_submitted_service_request_is_persisted_and_retrievable(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->submitServiceRequest($this->makeServiceRequest($resident->id));

        $stored = $this->serviceRequestRepository->findById($result->serviceRequest->id);

        $this->assertNotNull($stored);
        $this->assertSame($result->serviceRequest->id, $stored->id);
        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_submitted_service_request_information_is_preserved(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->submitServiceRequest($this->makeServiceRequest($resident->id));

        $stored = $this->serviceRequestRepository->findById($result->serviceRequest->id);

        $this->assertSame($resident->id, $stored->resident_id);
        $this->assertSame('Barangay Clearance', $stored->service_type);
        $this->assertSame('Requesting barangay clearance for employment requirements.', $stored->description);
        $this->assertSame('2026-09-15', $stored->date_requested);
        $this->assertSame('Pending', $stored->status);
    }

    public function test_submitted_service_request_status_is_pending(): void
    {
        $resident = $this->persistResident();
        $serviceRequest = $this->makeServiceRequest($resident->id);

        $this->assertSame('Pending', $serviceRequest->status);

        $result = $this->service->submitServiceRequest($serviceRequest);

        $this->assertSame('Pending', $result->serviceRequest->status);
        $this->assertDatabaseHas('service_requests', [
            'id' => $result->serviceRequest->id,
            'status' => 'Pending',
        ]);
    }

    public function test_blank_service_type_fails_validation(): void
    {
        $resident = $this->persistResident();

        $empty = $this->service->submitServiceRequest(
            $this->makeServiceRequest($resident->id, ['service_type' => ''])
        );
        $whitespace = $this->service->submitServiceRequest(
            $this->makeServiceRequest($resident->id, ['service_type' => '   '])
        );

        $this->assertFalse($empty->success);
        $this->assertContains('service_type', $empty->errors);
        $this->assertFalse($whitespace->success);
        $this->assertContains('service_type', $whitespace->errors);
    }

    public function test_blank_description_fails_validation(): void
    {
        $resident = $this->persistResident();

        $empty = $this->service->submitServiceRequest(
            $this->makeServiceRequest($resident->id, ['description' => ''])
        );
        $whitespace = $this->service->submitServiceRequest(
            $this->makeServiceRequest($resident->id, ['description' => "  \t "])
        );

        $this->assertFalse($empty->success);
        $this->assertContains('description', $empty->errors);
        $this->assertFalse($whitespace->success);
        $this->assertContains('description', $whitespace->errors);
    }

    public function test_invalid_request_does_not_reach_persistence(): void
    {
        $resident = $this->persistResident();
        $countBefore = ServiceRequest::query()->count();

        $result = $this->service->submitServiceRequest(
            $this->makeServiceRequest($resident->id, ['service_type' => ' ', 'description' => ''])
        );

        $this->assertFalse($result->success);
        $this->assertNull($result->serviceRequest);
        $this->assertSame($countBefore, ServiceRequest::query()->count());
    }

    public function test_nonexistent_resident_prevents_submission(): void
    {
        $result = $this->service->submitServiceRequest($this->makeServiceRequest(999999));

        $this->assertFalse($result->success);
        $this->assertTrue($result->residentNotFound);
        $this->assertFalse($result->residentInactive);
        $this->assertSame([], $result->errors);
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_inactive_resident_cannot_submit_a_new_service_request(): void
    {
        $resident = $this->persistResident(['status' => 'Inactive']);

        $result = $this->service->submitServiceRequest($this->makeServiceRequest($resident->id));

        $this->assertFalse($result->success);
        $this->assertTrue($result->residentInactive);
        $this->assertFalse($result->residentNotFound);
        $this->assertSame('Inactive', $this->residentRepository->findById($resident->id)->status);
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_non_pending_initial_status_is_rejected(): void
    {
        $resident = $this->persistResident();

        foreach (['Completed', 'Cancelled', 'In Progress'] as $status) {
            $result = $this->service->submitServiceRequest(
                $this->makeServiceRequest($resident->id, ['status' => $status])
            );

            $this->assertFalse($result->success);
            $this->assertContains('status', $result->errors);
        }

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_service_request_persists_across_repository_access(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->submitServiceRequest($this->makeServiceRequest($resident->id));

        $stored = (new ServiceRequestRepository)->findById($result->serviceRequest->id);

        $this->assertNotNull($stored);
        $this->assertSame($result->serviceRequest->id, $stored->id);
        $this->assertSame('Barangay Clearance', $stored->service_type);
        $this->assertSame('Pending', $stored->status);
    }

    public function test_submission_does_not_modify_the_resident(): void
    {
        $resident = $this->persistResident();

        $this->service->submitServiceRequest($this->makeServiceRequest($resident->id));

        $stored = $this->residentRepository->findById($resident->id);

        $this->assertSame($resident->id, $stored->id);
        $this->assertSame('Juan', $stored->first_name);
        $this->assertSame('Dela Cruz', $stored->last_name);
        $this->assertSame('Barangay Santo Tomas', $stored->address);
        $this->assertSame('09171234567', $stored->contact_number);
        $this->assertSame('juan@example.com', $stored->email);
        $this->assertSame('Active', $stored->status);
    }

    public function test_missing_or_invalid_request_date_is_rejected(): void
    {
        $resident = $this->persistResident();

        foreach ([null, '', '2026-02-30', '15/09/2026', 'not-a-date'] as $date) {
            $result = $this->service->submitServiceRequest(
                $this->makeServiceRequest($resident->id, ['date_requested' => $date])
            );

            $this->assertFalse($result->success);
            $this->assertContains('date_requested', $result->errors);
        }

        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_assigned_id_or_invalid_resident_id_is_rejected(): void
    {
        $resident = $this->persistResident();

        $withId = $this->makeServiceRequest($resident->id);
        $withId->id = 5;

        $assignedId = $this->service->submitServiceRequest($withId);

        $this->assertFalse($assignedId->success);
        $this->assertContains('id', $assignedId->errors);

        foreach ([null, 0, -3] as $residentId) {
            $result = $this->service->submitServiceRequest(new ServiceRequest([
                'resident_id' => $residentId,
                'service_type' => 'Barangay Clearance',
                'description' => 'Requesting barangay clearance.',
                'date_requested' => '2026-09-15',
            ]));

            $this->assertFalse($result->success);
            $this->assertContains('resident_id', $result->errors);
            $this->assertFalse($result->residentNotFound);
        }

        $this->assertDatabaseCount('service_requests', 0);
    }
}
