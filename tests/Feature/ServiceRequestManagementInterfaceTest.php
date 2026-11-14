<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Repositories\ServiceRequestRepository;
use App\Services\ResidentDeactivationService;
use App\Services\ResidentRegistrationService;
use App\Services\ServiceRequestStatusService;
use App\Services\ServiceRequestSubmissionService;
use Tests\Concerns\UsesTemporaryDatabase;
use Tests\TestCase;

class ServiceRequestManagementInterfaceTest extends TestCase
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

    private Resident $resident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryDatabase('csms_t13_');

        $this->resident = $this->registerResident();
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDatabase();

        parent::tearDown();
    }

    private function registerResident(
        string $contactNumber = '09171234567',
        string $email = 'maria@example.com'
    ): Resident {
        $result = app(ResidentRegistrationService::class)->registerResident(
            new Resident([
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'address' => 'Barangay Santo Tomas',
                'contact_number' => $contactNumber,
                'email' => $email,
            ])
        );

        $this->assertTrue($result->success);

        return $result->resident;
    }

    /**
     * Submit a Service Request through T09, then move it to the wanted
     * status through the T10 workflow.
     */
    private function submitServiceRequest(
        string $serviceType = 'Barangay Clearance',
        string $description = 'Employment requirement',
        string $dateRequested = '2026-09-15',
        string $status = 'Pending'
    ): ServiceRequest {
        $result = app(ServiceRequestSubmissionService::class)->submitServiceRequest(
            new ServiceRequest([
                'resident_id' => $this->resident->id,
                'service_type' => $serviceType,
                'description' => $description,
                'date_requested' => $dateRequested,
            ])
        );

        $this->assertTrue($result->success);

        foreach (self::PATH_TO_STATUS[$status] as $nextStatus) {
            $this->assertTrue(
                app(ServiceRequestStatusService::class)
                    ->changeStatus($result->serviceRequest->id, $nextStatus)
                    ->success
            );
        }

        return $this->findServiceRequest($result->serviceRequest->id);
    }

    private function findServiceRequest(int $id): ?ServiceRequest
    {
        return (new ServiceRequestRepository)->findById($id);
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge(
            [
                'resident_id' => (string) $this->resident->id,
                'service_type' => 'Barangay Clearance',
                'description' => 'Requesting barangay clearance for employment requirements.',
                'date_requested' => '2026-09-18',
            ],
            $overrides
        );
    }

    private function snapshot(): array
    {
        return ServiceRequest::query()->orderBy('id')->get()->toArray();
    }

    public function test_service_request_management_page_loads(): void
    {
        $response = $this->get('/service-requests');

        $response->assertOk();
        $response->assertSee('Service Request Management');
        $response->assertSee('Submit Service Request');
        $response->assertSee('name="search"', false);
        $response->assertSee('name="status"', false);
        $response->assertSeeInOrder([
            'All Statuses',
            'Pending',
            'In Progress',
            'Completed',
            'Cancelled',
        ]);
        $response->assertSee('No Service Requests found.');
        $response->assertSee('Residents');
    }

    public function test_persisted_service_requests_are_displayed(): void
    {
        $first = $this->submitServiceRequest('Barangay Clearance', 'Employment requirement', '2026-09-15');
        $second = $this->submitServiceRequest('Community Assistance', 'Medical support', '2026-09-16', 'In Progress');

        $response = $this->get('/service-requests');

        $response->assertOk();

        foreach ([$first, $second] as $serviceRequest) {
            $response->assertSee($serviceRequest->service_type);
            $response->assertSee($serviceRequest->description);
            $response->assertSee($serviceRequest->date_requested);
            $response->assertSee($serviceRequest->status);
            $response->assertSee(
                route('service-requests.show', ['id' => $serviceRequest->id], false)
            );
        }

        $response->assertDontSee('No Service Requests found.');
    }

    public function test_required_ordering_is_preserved(): void
    {
        $this->submitServiceRequest('Middle Request', dateRequested: '2026-09-15');
        $this->submitServiceRequest('Newest Lower Id', dateRequested: '2026-09-18');
        $this->submitServiceRequest('Newest Higher Id', dateRequested: '2026-09-18');
        $this->submitServiceRequest('Oldest Request', dateRequested: '2026-09-10');

        $this->get('/service-requests')->assertSeeInOrder([
            'Newest Higher Id',
            'Newest Lower Id',
            'Middle Request',
            'Oldest Request',
        ]);
    }

    public function test_text_search_uses_t11_behavior(): void
    {
        $this->submitServiceRequest('Barangay Clearance', 'Employment requirement');
        $this->submitServiceRequest('Community Assistance', 'Medical support');

        $byType = $this->get('/service-requests?search='.urlencode('   cLeAr   '));

        $byType->assertOk();
        $byType->assertSee('Barangay Clearance');
        $byType->assertDontSee('Community Assistance');
        $byType->assertSee('value="cLeAr"', false);

        $byDescription = $this->get('/service-requests?search=MEDICAL');

        $byDescription->assertSee('Community Assistance');
        $byDescription->assertDontSee('Barangay Clearance');

        $blank = $this->get('/service-requests?search='.urlencode('      '));

        $blank->assertOk();
        $blank->assertSee('Barangay Clearance');
        $blank->assertSee('Community Assistance');

        $noMatch = $this->get('/service-requests?search=ZzzNothing');

        $noMatch->assertOk();
        $noMatch->assertSee('No Service Requests found.');
    }

    public function test_status_filter_uses_t11_behavior(): void
    {
        $this->submitServiceRequest('Pending Type', status: 'Pending');
        $this->submitServiceRequest('Progress Type', status: 'In Progress');
        $this->submitServiceRequest('Completed Type', status: 'Completed');
        $this->submitServiceRequest('Cancelled Type', status: 'Cancelled');

        $expected = [
            'Pending' => 'Pending Type',
            'In Progress' => 'Progress Type',
            'Completed' => 'Completed Type',
            'Cancelled' => 'Cancelled Type',
        ];

        foreach ($expected as $status => $visibleType) {
            $response = $this->get('/service-requests?status='.urlencode($status));

            $response->assertOk();
            $response->assertSee($visibleType);

            foreach (array_diff($expected, [$visibleType]) as $hiddenType) {
                $response->assertDontSee($hiddenType);
            }
        }

        $all = $this->get('/service-requests?status=');

        foreach ($expected as $visibleType) {
            $all->assertSee($visibleType);
        }
    }

    public function test_unsupported_status_filter_is_reported_without_failing(): void
    {
        $this->submitServiceRequest('Barangay Clearance');

        $response = $this->get('/service-requests?status=Approved');

        $response->assertOk();
        $response->assertSee('not a supported Service Request status');
        $response->assertSee('No Service Requests found.');
    }

    public function test_combined_search_and_status_filter_works(): void
    {
        $this->submitServiceRequest('Barangay Clearance', 'Matches both', status: 'Pending');
        $this->submitServiceRequest('Barangay Clearance', 'Matches text only', status: 'Completed');
        $this->submitServiceRequest('Community Assistance', 'Matches status only', status: 'Pending');
        $this->submitServiceRequest('Community Assistance', 'Matches neither', status: 'Cancelled');

        $response = $this->get('/service-requests?search=clearance&status=Pending');

        $response->assertOk();
        $response->assertSee('Matches both');
        $response->assertDontSee('Matches text only');
        $response->assertDontSee('Matches status only');
        $response->assertDontSee('Matches neither');
    }

    public function test_submission_form_is_accessible(): void
    {
        $response = $this->get('/service-requests/new');

        $response->assertOk();

        foreach (['Resident ID', 'Service Type', 'Description', 'Date Requested'] as $label) {
            $response->assertSee($label);
        }

        foreach (['resident_id', 'service_type', 'description', 'date_requested'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertDontSee('name="id"', false);
        $response->assertDontSee('name="status"', false);
    }

    public function test_valid_submission_persists_a_pending_request(): void
    {
        $response = $this->post(
            '/service-requests',
            $this->validInput(['id' => 700, 'status' => 'Completed'])
        );

        $response->assertRedirect('/service-requests');
        $response->assertSessionHas('success', 'Service Request submitted successfully.');

        $this->assertSame(1, ServiceRequest::count());

        $serviceRequest = ServiceRequest::first();

        $this->assertNotNull($serviceRequest->id);
        $this->assertNotSame(700, $serviceRequest->id);
        $this->assertSame($this->resident->id, $serviceRequest->resident_id);
        $this->assertSame('Barangay Clearance', $serviceRequest->service_type);
        $this->assertSame(
            'Requesting barangay clearance for employment requirements.',
            $serviceRequest->description
        );
        $this->assertSame('2026-09-18', $serviceRequest->date_requested);
        $this->assertSame('Pending', $serviceRequest->status);

        $this->get('/service-requests')->assertSee('Barangay Clearance');
    }

    public function test_invalid_submission_shows_validation_feedback(): void
    {
        $input = $this->validInput([
            'service_type' => '   ',
            'description' => '',
            'date_requested' => '18/09/2026',
        ]);

        $response = $this->post('/service-requests', $input);

        $response->assertRedirect('/service-requests/new');
        $response->assertSessionHasErrors(['service_type', 'description', 'date_requested']);

        $form = $this->followingRedirects()->post('/service-requests', $input);

        $form->assertOk();
        $form->assertSee('Enter a service type.');
        $form->assertSee('Enter a description.');
        $form->assertSee('Enter the date requested as a valid date.');
        $form->assertSee('value="'.$this->resident->id.'"', false);

        $this->post(
            '/service-requests',
            $this->validInput(['resident_id' => 'abc'])
        )->assertSessionHasErrors(['resident_id']);

        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_missing_resident_submission_is_rejected(): void
    {
        $input = $this->validInput(['resident_id' => '999999']);

        $this->post('/service-requests', $input)
            ->assertRedirect('/service-requests/new')
            ->assertSessionHasErrors(['resident_id']);

        $this->followingRedirects()
            ->post('/service-requests', $input)
            ->assertSee('Resident not found.');

        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_inactive_resident_submission_is_rejected(): void
    {
        app(ResidentDeactivationService::class)->deactivateResident($this->resident->id);

        $this->post('/service-requests', $this->validInput())
            ->assertRedirect('/service-requests/new')
            ->assertSessionHasErrors(['resident_id']);

        $this->followingRedirects()
            ->post('/service-requests', $this->validInput())
            ->assertSee('Inactive Residents cannot submit new Service Requests.');

        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame('Inactive', Resident::find($this->resident->id)->status);
    }

    public function test_existing_service_request_details_can_be_viewed(): void
    {
        $longDescription = 'Requesting barangay clearance for employment requirements. '
            .'The clearance is needed before the end of the month for onboarding.';

        $serviceRequest = $this->submitServiceRequest(
            'Barangay Clearance',
            $longDescription,
            '2026-09-18'
        );

        $response = $this->get('/service-requests/'.$serviceRequest->id);

        $response->assertOk();
        $response->assertSee('Service Request Details');
        $response->assertSeeInOrder([
            'ID',
            (string) $serviceRequest->id,
            'Resident ID',
            (string) $this->resident->id,
            'Service Type',
            'Barangay Clearance',
            'Description',
            $longDescription,
            'Date Requested',
            '2026-09-18',
            'Status',
            'Pending',
        ]);
        $response->assertSee('Start Processing');
        $response->assertSee('Cancel');
        $response->assertDontSee('Complete');
    }

    public function test_missing_service_request_is_handled_safely(): void
    {
        $this->get('/service-requests/999999')
            ->assertNotFound()
            ->assertSee('Service Request not found.');

        $this->post('/service-requests/999999/start')->assertNotFound();
        $this->post('/service-requests/999999/complete')->assertNotFound();
        $this->post('/service-requests/999999/cancel')->assertNotFound();
        $this->get('/service-requests/not-a-number')->assertNotFound();

        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_pending_request_can_start_processing(): void
    {
        $serviceRequest = $this->submitServiceRequest();

        $response = $this->post('/service-requests/'.$serviceRequest->id.'/start');

        $response->assertRedirect('/service-requests/'.$serviceRequest->id);
        $response->assertSessionHas('success', 'Service Request is now In Progress.');

        $updated = $this->findServiceRequest($serviceRequest->id);

        $this->assertSame('In Progress', $updated->status);
        $this->assertSame($serviceRequest->resident_id, $updated->resident_id);
        $this->assertSame($serviceRequest->service_type, $updated->service_type);
        $this->assertSame($serviceRequest->description, $updated->description);
        $this->assertSame($serviceRequest->date_requested, $updated->date_requested);

        $details = $this->get('/service-requests/'.$serviceRequest->id);

        $details->assertSee('Complete');
        $details->assertSee('Cancel');
        $details->assertDontSee('Start Processing');
    }

    public function test_pending_request_can_be_cancelled(): void
    {
        $serviceRequest = $this->submitServiceRequest();

        $this->post('/service-requests/'.$serviceRequest->id.'/cancel')
            ->assertRedirect('/service-requests/'.$serviceRequest->id)
            ->assertSessionHas('success', 'Service Request cancelled successfully.');

        $this->assertSame('Cancelled', $this->findServiceRequest($serviceRequest->id)->status);
        $this->assertSame(1, ServiceRequest::count());
    }

    public function test_in_progress_request_can_be_completed(): void
    {
        $serviceRequest = $this->submitServiceRequest(status: 'In Progress');

        $this->post('/service-requests/'.$serviceRequest->id.'/complete')
            ->assertRedirect('/service-requests/'.$serviceRequest->id)
            ->assertSessionHas('success', 'Service Request completed successfully.');

        $this->assertSame('Completed', $this->findServiceRequest($serviceRequest->id)->status);
    }

    public function test_in_progress_request_can_be_cancelled(): void
    {
        $serviceRequest = $this->submitServiceRequest(status: 'In Progress');

        $this->post('/service-requests/'.$serviceRequest->id.'/cancel')
            ->assertRedirect('/service-requests/'.$serviceRequest->id)
            ->assertSessionHas('success', 'Service Request cancelled successfully.');

        $this->assertSame('Cancelled', $this->findServiceRequest($serviceRequest->id)->status);
    }

    public function test_invalid_direct_transition_is_still_rejected(): void
    {
        $pending = $this->submitServiceRequest();

        $response = $this->post('/service-requests/'.$pending->id.'/complete');

        $response->assertRedirect('/service-requests/'.$pending->id);
        $response->assertSessionHas('error');
        $response->assertSessionMissing('success');

        $this->assertSame('Pending', $this->findServiceRequest($pending->id)->status);

        $this->followingRedirects()
            ->post('/service-requests/'.$pending->id.'/complete')
            ->assertSee('Invalid Service Request transition');

        foreach (['Completed', 'Cancelled'] as $terminalStatus) {
            $terminal = $this->submitServiceRequest(status: $terminalStatus);

            foreach (['start', 'complete', 'cancel'] as $action) {
                $this->post('/service-requests/'.$terminal->id.'/'.$action)
                    ->assertSessionHas('error');

                $this->assertSame(
                    $terminalStatus,
                    $this->findServiceRequest($terminal->id)->status
                );
            }
        }
    }

    public function test_completed_and_cancelled_requests_remain_visible(): void
    {
        $completed = $this->submitServiceRequest('Completed Clearance', status: 'Completed');
        $cancelled = $this->submitServiceRequest('Cancelled Clearance', status: 'Cancelled');

        $this->get('/service-requests')
            ->assertSee('Completed Clearance')
            ->assertSee('Cancelled Clearance');

        $this->get('/service-requests?search=clearance&status=Completed')
            ->assertSee('Completed Clearance')
            ->assertDontSee('Cancelled Clearance');

        $this->get('/service-requests?search=clearance&status=Cancelled')
            ->assertSee('Cancelled Clearance')
            ->assertDontSee('Completed Clearance');

        foreach ([$completed, $cancelled] as $terminal) {
            $details = $this->get('/service-requests/'.$terminal->id);

            $details->assertOk();
            $details->assertSee($terminal->status);
            $details->assertSee('No further workflow actions are available.');
            $details->assertDontSee('<button', false);
        }
    }

    public function test_get_requests_do_not_change_service_request_state(): void
    {
        $serviceRequest = $this->submitServiceRequest();

        $before = $this->snapshot();

        $this->get('/service-requests')->assertOk();
        $this->get('/service-requests?search=clearance')->assertOk();
        $this->get('/service-requests?status=Pending')->assertOk();
        $this->get('/service-requests?search=clearance&status=Pending')->assertOk();
        $this->get('/service-requests/new')->assertOk();
        $this->get('/service-requests/'.$serviceRequest->id)->assertOk();

        foreach (['start', 'complete', 'cancel'] as $action) {
            $this->get('/service-requests/'.$serviceRequest->id.'/'.$action)
                ->assertStatus(405);
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame('Pending', $this->findServiceRequest($serviceRequest->id)->status);
    }
}
