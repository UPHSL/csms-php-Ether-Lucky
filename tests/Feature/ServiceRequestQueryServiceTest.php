<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Repositories\ResidentRepository;
use App\Repositories\ServiceRequestRepository;
use App\Services\ServiceRequestQueryService;
use App\Services\UnsupportedStatusFilterException;
use Tests\Concerns\UsesTemporaryDatabase;
use Tests\TestCase;

class ServiceRequestQueryServiceTest extends TestCase
{
    use UsesTemporaryDatabase;

    private ServiceRequestRepository $repository;

    private ServiceRequestQueryService $service;

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryDatabase('csms_t11_');

        $this->repository = new ServiceRequestRepository;

        $this->service = new ServiceRequestQueryService(
            $this->repository
        );

        $this->resident = (new ResidentRepository)->save(
            new Resident([
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'address' => 'Barangay Santo Tomas',
                'contact_number' => '09171234567',
                'email' => 'juan@example.com',
            ])
        );
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDatabase();

        parent::tearDown();
    }

    private function saveServiceRequest(
        string $serviceType = 'Barangay Clearance',
        string $description = 'Employment requirement',
        string $dateRequested = '2026-09-15',
        string $status = 'Pending'
    ): ServiceRequest {
        $serviceRequest = new ServiceRequest([
            'resident_id' => $this->resident->id,
            'service_type' => $serviceType,
            'description' => $description,
            'date_requested' => $dateRequested,
        ]);

        if ($status !== 'Pending') {
            $serviceRequest->status = $status;
        }

        return $this->repository->save(
            $serviceRequest
        );
    }

    public function test_lists_all_persisted_service_requests(): void
    {
        $first = $this->saveServiceRequest('Barangay Clearance');
        $second = $this->saveServiceRequest('Community Assistance');
        $third = $this->saveServiceRequest('Certificate of Residency');

        $serviceRequests = $this->service->listServiceRequests();

        $this->assertCount(3, $serviceRequests);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id, $third->id],
            $serviceRequests->pluck('id')->all()
        );
    }

    public function test_empty_listing_returns_empty_collection(): void
    {
        $serviceRequests = $this->service->listServiceRequests();

        $this->assertNotNull($serviceRequests);
        $this->assertCount(0, $serviceRequests);
        $this->assertTrue($serviceRequests->isEmpty());
    }

    public function test_listing_orders_by_date_requested_then_id_descending(): void
    {
        $middle = $this->saveServiceRequest(dateRequested: '2026-09-15');
        $newestLowerId = $this->saveServiceRequest(dateRequested: '2026-09-18');
        $newestHigherId = $this->saveServiceRequest(dateRequested: '2026-09-18');
        $oldest = $this->saveServiceRequest(dateRequested: '2026-09-10');

        $serviceRequests = $this->service->listServiceRequests();

        $this->assertSame(
            [
                $newestHigherId->id,
                $newestLowerId->id,
                $middle->id,
                $oldest->id,
            ],
            $serviceRequests->pluck('id')->all()
        );
    }

    public function test_views_existing_service_request_by_id(): void
    {
        $this->saveServiceRequest('Community Assistance');
        $saved = $this->saveServiceRequest('Barangay Clearance');

        $viewed = $this->service->viewServiceRequest($saved->id);

        $this->assertNotNull($viewed);
        $this->assertSame($saved->id, $viewed->id);
        $this->assertSame('Barangay Clearance', $viewed->service_type);
    }

    public function test_viewing_unknown_id_returns_null_and_creates_nothing(): void
    {
        $this->saveServiceRequest();

        $viewed = $this->service->viewServiceRequest(999999);

        $this->assertNull($viewed);
        $this->assertSame(1, ServiceRequest::count());
        $this->assertNull($this->repository->findById(999999));
    }

    public function test_partial_service_type_search_is_case_insensitive(): void
    {
        $clearance = $this->saveServiceRequest(
            'Barangay Clearance',
            'Employment requirement'
        );

        $this->saveServiceRequest(
            'Community Assistance',
            'Medical support'
        );

        $results = $this->service->searchServiceRequests('  cLeAr  ');

        $this->assertCount(1, $results);
        $this->assertSame($clearance->id, $results[0]->id);
    }

    public function test_partial_description_search_is_case_insensitive(): void
    {
        $this->saveServiceRequest(
            'Community Assistance',
            'Medical support'
        );

        $employment = $this->saveServiceRequest(
            'Barangay Clearance',
            'Requesting barangay clearance for employment requirements.'
        );

        $results = $this->service->searchServiceRequests('EMPLOY');

        $this->assertCount(1, $results);
        $this->assertSame($employment->id, $results[0]->id);
    }

    public function test_blank_search_returns_all_service_requests_in_listing_order(): void
    {
        $this->saveServiceRequest(dateRequested: '2026-09-10');
        $this->saveServiceRequest(dateRequested: '2026-09-18');
        $this->saveServiceRequest(dateRequested: '2026-09-15');

        $listed = $this->service->listServiceRequests();

        foreach (['', ' ', '      ', null] as $blank) {
            $searched = $this->service->searchServiceRequests($blank);

            $this->assertSame(
                $listed->pluck('id')->all(),
                $searched->pluck('id')->all()
            );
        }
    }

    public function test_search_with_no_match_returns_empty_collection(): void
    {
        $this->saveServiceRequest();

        $results = $this->service->searchServiceRequests(
            'ZzzNoSuchServiceRequest'
        );

        $this->assertNotNull($results);
        $this->assertTrue($results->isEmpty());
    }

    public function test_status_filter_returns_only_matching_status(): void
    {
        $pendingOne = $this->saveServiceRequest(status: 'Pending');
        $this->saveServiceRequest(status: 'In Progress');
        $this->saveServiceRequest(status: 'Completed');
        $this->saveServiceRequest(status: 'Cancelled');
        $pendingTwo = $this->saveServiceRequest(status: 'Pending');

        $results = $this->service->searchServiceRequests(null, 'Pending');

        $this->assertSame(
            [$pendingTwo->id, $pendingOne->id],
            $results->pluck('id')->all()
        );

        foreach ($results as $serviceRequest) {
            $this->assertSame('Pending', $serviceRequest->status);
        }
    }

    public function test_every_supported_status_can_be_used_as_a_filter(): void
    {
        foreach (['Pending', 'In Progress', 'Completed', 'Cancelled'] as $status) {
            $this->saveServiceRequest(status: $status);
        }

        foreach (['Pending', 'In Progress', 'Completed', 'Cancelled'] as $status) {
            $results = $this->service->searchServiceRequests('', $status);

            $this->assertCount(1, $results);
            $this->assertSame($status, $results[0]->status);
        }
    }

    public function test_unfiltered_query_includes_all_statuses(): void
    {
        $this->saveServiceRequest(status: 'Pending');
        $this->saveServiceRequest(status: 'In Progress');
        $this->saveServiceRequest(status: 'Completed');
        $this->saveServiceRequest(status: 'Cancelled');

        $listed = $this->service->listServiceRequests();
        $searched = $this->service->searchServiceRequests(null, null);

        foreach ([$listed, $searched] as $serviceRequests) {
            $this->assertEqualsCanonicalizing(
                ['Pending', 'In Progress', 'Completed', 'Cancelled'],
                $serviceRequests->pluck('status')->all()
            );
        }
    }

    public function test_unsupported_status_filter_is_rejected(): void
    {
        $this->saveServiceRequest();

        foreach (['Approved', 'Rejected', 'Closed', 'Done', 'Processing', 'pending'] as $status) {
            try {
                $this->service->searchServiceRequests('clearance', $status);

                $this->fail(
                    'Unsupported status filter was accepted: '.$status
                );
            } catch (UnsupportedStatusFilterException $exception) {
                $this->assertStringContainsString(
                    $status,
                    $exception->getMessage()
                );
            }
        }
    }

    public function test_combined_text_search_and_status_filter_requires_both(): void
    {
        $matchesBoth = $this->saveServiceRequest(
            'Barangay Clearance',
            'Employment requirement',
            status: 'Pending'
        );

        $this->saveServiceRequest(
            'Barangay Clearance',
            'Scholarship requirement',
            status: 'Completed'
        );

        $this->saveServiceRequest(
            'Community Assistance',
            'Employment support',
            status: 'Pending'
        );

        $this->saveServiceRequest(
            'Community Assistance',
            'Medical support',
            status: 'Cancelled'
        );

        $results = $this->service->searchServiceRequests(
            'clearance',
            'Pending'
        );

        $this->assertCount(1, $results);
        $this->assertSame($matchesBoth->id, $results[0]->id);
    }

    public function test_search_results_use_the_listing_order(): void
    {
        $older = $this->saveServiceRequest(dateRequested: '2026-09-10');
        $sameDateLowerId = $this->saveServiceRequest(dateRequested: '2026-09-18');
        $sameDateHigherId = $this->saveServiceRequest(dateRequested: '2026-09-18');

        $this->saveServiceRequest(
            'Community Assistance',
            'Medical support',
            '2026-09-20'
        );

        $results = $this->service->searchServiceRequests(
            'clearance',
            'Pending'
        );

        $this->assertSame(
            [$sameDateHigherId->id, $sameDateLowerId->id, $older->id],
            $results->pluck('id')->all()
        );
    }

    public function test_service_request_matching_both_fields_appears_once(): void
    {
        $saved = $this->saveServiceRequest(
            'Community Assistance',
            'Assistance requested for medical support'
        );

        $results = $this->service->searchServiceRequests('assistance');

        $this->assertCount(1, $results);
        $this->assertSame($saved->id, $results[0]->id);
    }

    public function test_query_results_preserve_complete_service_request_information(): void
    {
        $saved = $this->saveServiceRequest(
            'Barangay Clearance',
            'Requesting barangay clearance for employment requirements.',
            '2026-09-18',
            'In Progress'
        );

        $results = [
            $this->service->listServiceRequests()->first(),
            $this->service->searchServiceRequests('clearance')->first(),
            $this->service->searchServiceRequests(null, 'In Progress')->first(),
            $this->service->viewServiceRequest($saved->id),
        ];

        foreach ($results as $serviceRequest) {
            $this->assertSame($saved->id, $serviceRequest->id);
            $this->assertSame($this->resident->id, $serviceRequest->resident_id);
            $this->assertSame('Barangay Clearance', $serviceRequest->service_type);
            $this->assertSame(
                'Requesting barangay clearance for employment requirements.',
                $serviceRequest->description
            );
            $this->assertSame('2026-09-18', $serviceRequest->date_requested);
            $this->assertSame('In Progress', $serviceRequest->status);
        }
    }

    public function test_query_operations_do_not_modify_persistence(): void
    {
        $saved = $this->saveServiceRequest(status: 'Pending');

        $before = ServiceRequest::query()->orderBy('id')->get()->toArray();

        $this->service->listServiceRequests();
        $this->service->viewServiceRequest($saved->id);
        $this->service->viewServiceRequest(999999);
        $this->service->searchServiceRequests('clearance', 'Pending');
        $this->service->searchServiceRequests('nothing-matches');

        $this->assertSame(
            $before,
            ServiceRequest::query()->orderBy('id')->get()->toArray()
        );
    }
}
