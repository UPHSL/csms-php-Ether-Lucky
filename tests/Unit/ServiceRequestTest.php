<?php

namespace Tests\Unit;

use App\Models\ServiceRequest;
use PHPUnit\Framework\TestCase;

class ServiceRequestTest extends TestCase
{
    private function makeServiceRequest(array $overrides = []): ServiceRequest
    {
        return new ServiceRequest(array_merge([
            'resident_id' => 25,
            'service_type' => 'Barangay Clearance',
            'description' => 'Request for employment requirement',
            'date_requested' => '2026-09-15',
        ], $overrides));
    }

    public function test_service_request_can_be_created(): void
    {
        $serviceRequest = $this->makeServiceRequest();

        $this->assertInstanceOf(ServiceRequest::class, $serviceRequest);
    }

    public function test_service_request_information_is_accessible(): void
    {
        $serviceRequest = $this->makeServiceRequest();

        $this->assertSame(25, $serviceRequest->resident_id);
        $this->assertSame('Barangay Clearance', $serviceRequest->service_type);
        $this->assertSame('Request for employment requirement', $serviceRequest->description);
        $this->assertSame('2026-09-15', $serviceRequest->date_requested);
    }

    public function test_resident_id_is_preserved(): void
    {
        $serviceRequest = $this->makeServiceRequest(['resident_id' => 25]);

        $this->assertSame(25, $serviceRequest->resident_id);
    }

    public function test_new_service_request_has_an_unassigned_id(): void
    {
        $serviceRequest = $this->makeServiceRequest();

        $this->assertNull($serviceRequest->id);
    }

    public function test_new_service_request_defaults_to_pending(): void
    {
        $serviceRequest = $this->makeServiceRequest();

        $this->assertSame('Pending', $serviceRequest->status);
    }

    public function test_service_request_information_is_independent_between_objects(): void
    {
        $first = $this->makeServiceRequest();
        $second = $this->makeServiceRequest([
            'resident_id' => 30,
            'service_type' => 'Certificate Request',
            'description' => 'Requesting certificate of residency',
            'date_requested' => '2026-09-20',
        ]);

        $first->description = 'Updated description for the first request';

        $this->assertSame(25, $first->resident_id);
        $this->assertSame('Barangay Clearance', $first->service_type);
        $this->assertSame('2026-09-15', $first->date_requested);

        $this->assertSame(30, $second->resident_id);
        $this->assertSame('Certificate Request', $second->service_type);
        $this->assertSame('Requesting certificate of residency', $second->description);
        $this->assertSame('2026-09-20', $second->date_requested);
        $this->assertSame('Pending', $second->status);
    }
}
