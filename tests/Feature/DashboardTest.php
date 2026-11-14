<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Services\DashboardSummary;
use App\Services\ResidentDeactivationService;
use App\Services\ResidentRegistrationService;
use App\Services\ServiceRequestStatusService;
use App\Services\ServiceRequestSubmissionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesTemporaryDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use UsesTemporaryDatabase;

    /**
     * The legitimate T10 transitions that lead from Pending to each status.
     */
    private const PATH_TO_STATUS = [
        'Pending' => [],
        'In Progress' => ['In Progress'],
        'Completed' => ['In Progress', 'Completed'],
        'Cancelled' => ['Cancelled'],
    ];

    private int $residentSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryDatabase('csms_t14_');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDatabase();

        parent::tearDown();
    }

    private function registerResident(bool $active = true): Resident
    {
        $this->residentSequence++;

        $result = app(ResidentRegistrationService::class)->registerResident(
            new Resident([
                'first_name' => 'Resident',
                'last_name' => 'Number '.$this->residentSequence,
                'address' => 'Barangay Santo Tomas',
                'contact_number' => sprintf('0917%07d', $this->residentSequence),
                'email' => 'resident'.$this->residentSequence.'@example.com',
            ])
        );

        $this->assertTrue($result->success);

        if (! $active) {
            app(ResidentDeactivationService::class)
                ->deactivateResident($result->resident->id);
        }

        return $result->resident;
    }

    private function submitServiceRequest(
        Resident $resident,
        string $status = 'Pending',
        string $dateRequested = '2026-09-15',
        string $serviceType = 'Barangay Clearance'
    ): ServiceRequest {
        $result = app(ServiceRequestSubmissionService::class)->submitServiceRequest(
            new ServiceRequest([
                'resident_id' => $resident->id,
                'service_type' => $serviceType,
                'description' => 'Employment requirement',
                'date_requested' => $dateRequested,
            ])
        );

        $this->assertTrue($result->success);

        foreach (self::PATH_TO_STATUS[$status] as $nextStatus) {
            $this->changeStatus($result->serviceRequest, $nextStatus);
        }

        return $result->serviceRequest;
    }

    private function changeStatus(ServiceRequest $serviceRequest, string $status): void
    {
        $this->assertTrue(
            app(ServiceRequestStatusService::class)
                ->changeStatus($serviceRequest->id, $status)
                ->success
        );
    }

    private function dashboard(): TestResponse
    {
        $response = $this->get('/dashboard');

        $response->assertOk();

        return $response;
    }

    private function summary(): DashboardSummary
    {
        return $this->dashboard()->viewData('summary');
    }

    private function assertMetric(TestResponse $response, string $metric, int $value): void
    {
        $response->assertSee(
            'data-metric="'.$metric.'">'.$value.'<',
            false
        );
    }

    public function test_dashboard_loads_successfully(): void
    {
        $response = $this->dashboard();

        $response->assertSee('CSMS Dashboard');
        $response->assertSee('Resident Summary');
        $response->assertSee('Service Request Summary');
        $response->assertSee('Open Service Requests');
        $response->assertSee('Recent Service Requests');
        $response->assertSee('href="'.route('residents.index').'"', false);
        $response->assertSee('href="'.route('service-requests.index').'"', false);
        $response->assertSee('href="'.route('dashboard').'"', false);
    }

    public function test_module_pages_link_back_to_the_dashboard(): void
    {
        foreach (['/residents', '/service-requests'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('href="'.route('dashboard').'"', false);
            $response->assertSee('href="'.route('residents.index').'"', false);
            $response->assertSee('href="'.route('service-requests.index').'"', false);
        }
    }

    public function test_empty_dashboard_displays_zero_counts(): void
    {
        $response = $this->dashboard();

        foreach ([
            'total-residents',
            'active-residents',
            'inactive-residents',
            'total-service-requests',
            'open-service-requests',
            'pending-service-requests',
            'in-progress-service-requests',
            'completed-service-requests',
            'cancelled-service-requests',
        ] as $metric) {
            $this->assertMetric($response, $metric, 0);
        }

        $response->assertSee('No Service Requests available.');

        $this->assertTrue(
            $response->viewData('summary')->recentServiceRequests->isEmpty()
        );
    }

    public function test_total_resident_count_is_correct(): void
    {
        $this->registerResident();
        $this->registerResident();
        $this->registerResident(active: false);

        $response = $this->dashboard();

        $this->assertMetric($response, 'total-residents', 3);
        $this->assertSame(3, $response->viewData('summary')->totalResidents);
    }

    public function test_resident_status_counts_are_correct(): void
    {
        $this->registerResident();
        $this->registerResident();
        $this->registerResident();
        $this->registerResident(active: false);
        $this->registerResident(active: false);

        $response = $this->dashboard();

        $this->assertMetric($response, 'active-residents', 3);
        $this->assertMetric($response, 'inactive-residents', 2);
        $this->assertMetric($response, 'total-residents', 5);

        $summary = $response->viewData('summary');

        $this->assertSame(
            $summary->totalResidents,
            $summary->activeResidents + $summary->inactiveResidents
        );
    }

    public function test_total_service_request_count_is_correct(): void
    {
        $resident = $this->registerResident();

        $this->submitServiceRequest($resident, 'Pending');
        $this->submitServiceRequest($resident, 'Completed');
        $this->submitServiceRequest($resident, 'Cancelled');

        $response = $this->dashboard();

        $this->assertMetric($response, 'total-service-requests', 3);
        $this->assertSame(3, $response->viewData('summary')->totalServiceRequests);
    }

    public function test_service_request_status_counts_are_correct(): void
    {
        $resident = $this->registerResident();

        foreach ([
            'Pending' => 4,
            'In Progress' => 3,
            'Completed' => 2,
            'Cancelled' => 1,
        ] as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $this->submitServiceRequest($resident, $status);
            }
        }

        $response = $this->dashboard();

        $this->assertMetric($response, 'pending-service-requests', 4);
        $this->assertMetric($response, 'in-progress-service-requests', 3);
        $this->assertMetric($response, 'completed-service-requests', 2);
        $this->assertMetric($response, 'cancelled-service-requests', 1);
    }

    public function test_service_request_counts_reconcile(): void
    {
        $resident = $this->registerResident();

        foreach (['Pending', 'Pending', 'In Progress', 'Completed', 'Completed', 'Cancelled'] as $status) {
            $this->submitServiceRequest($resident, $status);
        }

        $summary = $this->summary();

        $this->assertSame(6, $summary->totalServiceRequests);

        $this->assertSame(
            $summary->totalServiceRequests,
            $summary->pendingServiceRequests
                + $summary->inProgressServiceRequests
                + $summary->completedServiceRequests
                + $summary->cancelledServiceRequests
        );
    }

    public function test_open_request_count_is_pending_plus_in_progress(): void
    {
        $resident = $this->registerResident();

        foreach (['Pending', 'Pending', 'In Progress', 'Completed', 'Completed', 'Cancelled'] as $status) {
            $this->submitServiceRequest($resident, $status);
        }

        $response = $this->dashboard();

        $this->assertMetric($response, 'open-service-requests', 3);

        $summary = $response->viewData('summary');

        $this->assertSame(
            $summary->pendingServiceRequests + $summary->inProgressServiceRequests,
            $summary->openServiceRequests
        );
    }

    public function test_resident_deactivation_is_reflected(): void
    {
        $resident = $this->registerResident();
        $this->registerResident();

        $before = $this->summary();

        app(ResidentDeactivationService::class)->deactivateResident($resident->id);

        $after = $this->summary();

        $this->assertSame($before->totalResidents, $after->totalResidents);
        $this->assertSame($before->activeResidents - 1, $after->activeResidents);
        $this->assertSame($before->inactiveResidents + 1, $after->inactiveResidents);
    }

    public function test_service_request_transition_is_reflected(): void
    {
        $resident = $this->registerResident();

        $serviceRequest = $this->submitServiceRequest($resident, 'Pending');
        $this->submitServiceRequest($resident, 'Pending');

        $before = $this->summary();

        $this->changeStatus($serviceRequest, 'In Progress');

        $after = $this->summary();

        $this->assertSame($before->pendingServiceRequests - 1, $after->pendingServiceRequests);
        $this->assertSame($before->inProgressServiceRequests + 1, $after->inProgressServiceRequests);
        $this->assertSame($before->totalServiceRequests, $after->totalServiceRequests);
        $this->assertSame($before->openServiceRequests, $after->openServiceRequests);
    }

    public function test_terminal_transition_updates_open_count(): void
    {
        $resident = $this->registerResident();

        $toComplete = $this->submitServiceRequest($resident, 'In Progress');
        $toCancel = $this->submitServiceRequest($resident, 'In Progress');

        $before = $this->summary();

        $this->assertSame(2, $before->openServiceRequests);

        $this->changeStatus($toComplete, 'Completed');

        $afterCompletion = $this->summary();

        $this->assertSame(1, $afterCompletion->openServiceRequests);
        $this->assertSame(1, $afterCompletion->completedServiceRequests);
        $this->assertSame($before->totalServiceRequests, $afterCompletion->totalServiceRequests);

        $this->changeStatus($toCancel, 'Cancelled');

        $afterCancellation = $this->summary();

        $this->assertSame(0, $afterCancellation->openServiceRequests);
        $this->assertSame(1, $afterCancellation->cancelledServiceRequests);
        $this->assertSame($before->totalServiceRequests, $afterCancellation->totalServiceRequests);
    }

    public function test_recent_requests_return_no_more_than_five(): void
    {
        $resident = $this->registerResident();

        for ($day = 1; $day <= 7; $day++) {
            $this->submitServiceRequest(
                $resident,
                dateRequested: sprintf('2026-09-%02d', $day)
            );
        }

        $response = $this->dashboard();

        $this->assertCount(5, $response->viewData('summary')->recentServiceRequests);
        $this->assertSame(
            5,
            substr_count($response->getContent(), 'data-recent-service-request=')
        );
        $this->assertMetric($response, 'total-service-requests', 7);

        $response->assertDontSee('2026-09-02');
        $response->assertDontSee('2026-09-01');
    }

    public function test_fewer_than_five_requests_are_all_displayed(): void
    {
        $resident = $this->registerResident();

        $this->submitServiceRequest($resident);
        $this->submitServiceRequest($resident);

        $this->assertCount(2, $this->summary()->recentServiceRequests);
    }

    public function test_recent_requests_use_correct_ordering(): void
    {
        $resident = $this->registerResident();

        $middle = $this->submitServiceRequest($resident, dateRequested: '2026-09-15');
        $newestLowerId = $this->submitServiceRequest($resident, dateRequested: '2026-09-18');
        $newestHigherId = $this->submitServiceRequest($resident, dateRequested: '2026-09-18');
        $oldest = $this->submitServiceRequest($resident, dateRequested: '2026-09-10');

        $response = $this->dashboard();

        $this->assertSame(
            [$newestHigherId->id, $newestLowerId->id, $middle->id, $oldest->id],
            $response->viewData('summary')->recentServiceRequests->pluck('id')->all()
        );

        $response->assertSeeInOrder([
            'data-recent-service-request="'.$newestHigherId->id.'"',
            'data-recent-service-request="'.$newestLowerId->id.'"',
            'data-recent-service-request="'.$middle->id.'"',
            'data-recent-service-request="'.$oldest->id.'"',
        ], false);
    }

    public function test_terminal_requests_may_appear_in_recent_results(): void
    {
        $resident = $this->registerResident();

        $this->submitServiceRequest($resident, 'Pending', '2026-09-10');
        $completed = $this->submitServiceRequest($resident, 'Completed', '2026-09-19', 'Completed Clearance');
        $cancelled = $this->submitServiceRequest($resident, 'Cancelled', '2026-09-20', 'Cancelled Clearance');

        $response = $this->dashboard();

        $this->assertSame(
            [$cancelled->id, $completed->id],
            $response->viewData('summary')->recentServiceRequests->take(2)->pluck('id')->all()
        );

        $response->assertSee('Completed Clearance');
        $response->assertSee('Cancelled Clearance');
        $response->assertSee(
            'href="'.route('service-requests.show', ['id' => $completed->id]).'"',
            false
        );

        $this->get('/service-requests/'.$completed->id)
            ->assertOk()
            ->assertSee('Completed Clearance');
    }

    public function test_dashboard_get_does_not_modify_persistence(): void
    {
        $active = $this->registerResident();
        $this->registerResident(active: false);

        $this->submitServiceRequest($active, 'Pending');
        $this->submitServiceRequest($active, 'In Progress');
        $this->submitServiceRequest($active, 'Completed');

        $residentsBefore = Resident::query()->orderBy('id')->get()->toArray();
        $serviceRequestsBefore = ServiceRequest::query()->orderBy('id')->get()->toArray();

        $this->dashboard();
        $this->dashboard();
        $this->dashboard();

        $this->assertSame(
            $residentsBefore,
            Resident::query()->orderBy('id')->get()->toArray()
        );

        $this->assertSame(
            $serviceRequestsBefore,
            ServiceRequest::query()->orderBy('id')->get()->toArray()
        );
    }

    public function test_dashboard_counts_use_aggregate_queries_only(): void
    {
        $resident = $this->registerResident();

        for ($i = 0; $i < 8; $i++) {
            $this->submitServiceRequest($resident);
        }

        DB::enableQueryLog();

        $this->dashboard();

        $queries = array_column(DB::getQueryLog(), 'query');

        DB::disableQueryLog();

        $this->assertNotEmpty($queries);

        foreach ($queries as $query) {
            $normalized = strtolower($query);

            $this->assertTrue(
                str_contains($normalized, 'count(') || str_contains($normalized, 'limit'),
                'Dashboard issued a non-aggregate, unbounded query: '.$query
            );
        }
    }
}
