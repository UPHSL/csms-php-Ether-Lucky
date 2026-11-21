<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Services\ResidentRegistrationService;
use App\Services\ServiceRequestStatusService;
use App\Services\ServiceRequestSubmissionService;
use Tests\Concerns\UsesTemporaryDatabase;
use Tests\TestCase;

/**
 * T15 hardening tests. Each one exercises the integrated system through its
 * browser routes and then re-reads persistence to confirm what happened.
 */
class SystemHardeningTest extends TestCase
{
    use UsesTemporaryDatabase;

    private int $residentSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryDatabase('csms_t15_');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDatabase();

        parent::tearDown();
    }

    private function registerResident(
        string $firstName = 'Maria',
        string $lastName = 'Santos'
    ): Resident {
        $this->residentSequence++;

        $result = app(ResidentRegistrationService::class)->registerResident(
            new Resident([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'address' => 'Barangay Santo Tomas',
                'contact_number' => sprintf('0917%07d', $this->residentSequence),
                'email' => 'resident'.$this->residentSequence.'@example.com',
            ])
        );

        $this->assertTrue($result->success);

        return $result->resident;
    }

    private function submitServiceRequest(
        Resident $resident,
        string $serviceType = 'Barangay Clearance',
        string $description = 'Employment requirement',
        string $dateRequested = '2026-09-15'
    ): ServiceRequest {
        $result = app(ServiceRequestSubmissionService::class)->submitServiceRequest(
            new ServiceRequest([
                'resident_id' => $resident->id,
                'service_type' => $serviceType,
                'description' => $description,
                'date_requested' => $dateRequested,
            ])
        );

        $this->assertTrue($result->success);

        return $result->serviceRequest;
    }

    /**
     * Every persisted row of both domains, including timestamps.
     */
    private function persistedState(): array
    {
        return [
            'residents' => Resident::query()->orderBy('id')->get()->toArray(),
            'service_requests' => ServiceRequest::query()->orderBy('id')->get()->toArray(),
        ];
    }

    private function metric(string $name): int
    {
        $response = $this->get('/dashboard');

        $response->assertOk();

        $this->assertSame(
            1,
            preg_match(
                '/data-metric="'.preg_quote($name, '/').'">(\d+)</',
                $response->getContent(),
                $matches
            ),
            'Dashboard metric not rendered: '.$name
        );

        return (int) $matches[1];
    }

    /**
     * Category 1 - Invalid or malformed input.
     *
     * Identifiers that are not usable numbers, identifiers far beyond any
     * real ID, and form/query values sent as arrays instead of text must all
     * end in controlled responses and leave persistence untouched.
     */
    public function test_malformed_identifiers_and_non_text_input_are_handled_safely(): void
    {
        $resident = $this->registerResident();
        $this->submitServiceRequest($resident);

        $before = $this->persistedState();

        $overflowingId = '99999999999999999999999999';

        foreach (['abc', '1.5', '-1', '1 OR 1=1', $overflowingId] as $badId) {
            $encoded = rawurlencode($badId);

            $this->get('/residents/'.$encoded.'/edit')->assertNotFound();
            $this->put('/residents/'.$encoded, ['first_name' => 'Changed'])->assertNotFound();
            $this->post('/residents/'.$encoded.'/deactivate')->assertNotFound();
            $this->get('/service-requests/'.$encoded)->assertNotFound();
            $this->post('/service-requests/'.$encoded.'/start')->assertNotFound();
            $this->post('/service-requests/'.$encoded.'/complete')->assertNotFound();
            $this->post('/service-requests/'.$encoded.'/cancel')->assertNotFound();
        }

        $this->get('/residents?search[]=maria')->assertOk()->assertSee('Santos');

        $this->get('/service-requests?search[]=clearance&status[]=Pending')
            ->assertOk()
            ->assertSee('Barangay Clearance');

        $this->post('/residents', [
            'first_name' => ['Maria'],
            'last_name' => ['nested' => 'Santos'],
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09181234567',
            'email' => 'array@example.com',
        ])
            ->assertRedirect('/residents/register')
            ->assertSessionHasErrors(['first_name', 'last_name']);

        $this->followingRedirects()
            ->post('/residents', ['first_name' => ['Maria'], 'email' => ['a' => 'b']])
            ->assertOk()
            ->assertSee('Enter a first name.');

        $this->followingRedirects()
            ->post('/service-requests', ['resident_id' => ['1'], 'description' => ['x']])
            ->assertOk()
            ->assertSee('Enter a valid Resident ID.');

        $this->post('/service-requests', [
            'resident_id' => [$resident->id],
            'service_type' => ['Barangay Clearance'],
            'description' => 'Employment requirement',
            'date_requested' => '2026-09-18',
        ])
            ->assertRedirect('/service-requests/new')
            ->assertSessionHasErrors(['resident_id', 'service_type']);

        $this->post('/service-requests', [
            'resident_id' => $overflowingId,
            'service_type' => 'Barangay Clearance',
            'description' => 'Employment requirement',
            'date_requested' => '2026-09-18',
        ])
            ->assertRedirect('/service-requests/new')
            ->assertSessionHasErrors(['resident_id']);

        $this->assertSame($before, $this->persistedState());
    }

    /**
     * Category 2 - Rejected mutation protects persistence.
     *
     * An invalid update aimed at an Inactive Resident, carrying extra id and
     * status fields, must be rejected as a whole: no editable field is
     * partially saved and the Resident is not reactivated or re-identified.
     */
    public function test_rejected_update_leaves_an_inactive_resident_completely_unchanged(): void
    {
        $resident = $this->registerResident();

        $this->post('/residents/'.$resident->id.'/deactivate')->assertRedirect('/residents');

        $before = Resident::find($resident->id)->toArray();

        $this->assertSame('Inactive', $before['status']);

        $this->travel(5)->minutes();

        $response = $this->put('/residents/'.$resident->id, [
            'id' => 4242,
            'status' => 'Active',
            'first_name' => 'Changed',
            'last_name' => 'Name',
            'address' => 'A different address',
            'contact_number' => 'not-a-number',
            'email' => 'changed@example.com',
        ]);

        $response->assertRedirect('/residents/'.$resident->id.'/edit');
        $response->assertSessionHasErrors(['contact_number']);

        $this->assertSame($before, Resident::find($resident->id)->toArray());
        $this->assertNull(Resident::find(4242));
        $this->assertSame(1, Resident::count());
    }

    /**
     * Category 3 - Read-only request safety.
     *
     * Every GET the interface offers, including searches, filters, missing
     * records and an unsupported filter, is requested repeatedly. Afterwards
     * every row of both tables must be identical, timestamps included.
     */
    public function test_repeated_read_only_requests_never_change_persisted_state(): void
    {
        $active = $this->registerResident('Maria', 'Santos');
        $inactive = $this->registerResident('Juan', 'Cruz');

        $pending = $this->submitServiceRequest($active);
        $inProgress = $this->submitServiceRequest($active, 'Community Assistance');

        app(ServiceRequestStatusService::class)->changeStatus($inProgress->id, 'In Progress');

        $this->post('/residents/'.$inactive->id.'/deactivate');

        $before = $this->persistedState();

        $this->travel(10)->minutes();

        $readOnlyRequests = [
            '/' => 200,
            '/health' => 200,
            '/dashboard' => 200,
            '/residents' => 200,
            '/residents?search=maria' => 200,
            '/residents?search=%25' => 200,
            '/residents/register' => 200,
            '/residents/'.$active->id.'/edit' => 200,
            '/residents/'.$inactive->id.'/edit' => 200,
            '/residents/999999/edit' => 404,
            '/service-requests' => 200,
            '/service-requests?search=clearance' => 200,
            '/service-requests?status=Pending' => 200,
            '/service-requests?search=assist&status=In+Progress' => 200,
            '/service-requests?status=Approved' => 200,
            '/service-requests/new' => 200,
            '/service-requests/'.$pending->id => 200,
            '/service-requests/'.$inProgress->id => 200,
            '/service-requests/999999' => 404,
        ];

        for ($round = 1; $round <= 3; $round++) {
            foreach ($readOnlyRequests as $url => $expectedStatus) {
                $this->get($url)->assertStatus($expectedStatus);
            }
        }

        $this->assertSame($before, $this->persistedState());
    }

    /**
     * Category 4 - Cross-feature regression.
     *
     * Resident deactivation (T07/T12) must block new submissions (T09/T13)
     * without disturbing that Resident's existing Service Request, which
     * stays queryable (T11) and processable (T10), and every step must be
     * reflected in the dashboard aggregates (T14).
     */
    public function test_resident_deactivation_blocks_new_requests_but_keeps_existing_ones_working(): void
    {
        $resident = $this->registerResident();

        $this->post('/service-requests', [
            'resident_id' => (string) $resident->id,
            'service_type' => 'Barangay Clearance',
            'description' => 'Employment requirement',
            'date_requested' => '2026-09-18',
        ])->assertRedirect('/service-requests');

        $existing = ServiceRequest::first();

        $this->assertSame(1, $this->metric('active-residents'));
        $this->assertSame(0, $this->metric('inactive-residents'));
        $this->assertSame(1, $this->metric('open-service-requests'));

        $this->post('/residents/'.$resident->id.'/deactivate')->assertRedirect('/residents');

        $this->assertSame(1, $this->metric('total-residents'));
        $this->assertSame(0, $this->metric('active-residents'));
        $this->assertSame(1, $this->metric('inactive-residents'));

        $this->post('/service-requests', [
            'resident_id' => (string) $resident->id,
            'service_type' => 'Community Assistance',
            'description' => 'Second request after deactivation',
            'date_requested' => '2026-09-19',
        ])
            ->assertRedirect('/service-requests/new')
            ->assertSessionHasErrors(['resident_id']);

        $this->assertSame(1, ServiceRequest::count());
        $this->assertSame(1, $this->metric('total-service-requests'));

        $this->get('/service-requests?search=clearance&status=Pending')
            ->assertOk()
            ->assertSee('Barangay Clearance');

        $this->post('/service-requests/'.$existing->id.'/start')
            ->assertSessionHas('success');

        $this->post('/service-requests/'.$existing->id.'/complete')
            ->assertSessionHas('success');

        $completed = ServiceRequest::find($existing->id);

        $this->assertSame('Completed', $completed->status);
        $this->assertSame($resident->id, $completed->resident_id);
        $this->assertSame('Inactive', Resident::find($resident->id)->status);

        $this->assertSame(0, $this->metric('open-service-requests'));
        $this->assertSame(1, $this->metric('completed-service-requests'));
        $this->assertSame(1, $this->metric('total-service-requests'));
    }

    /**
     * Category 5 - Boundary behavior.
     *
     * A search term is text to find, not a pattern. Terms made of the SQL
     * wildcard characters % and _ must match only records that literally
     * contain them, for both Resident and Service Request searches.
     */
    public function test_search_terms_containing_sql_wildcards_are_matched_literally(): void
    {
        $plain = $this->registerResident('Maria', 'Santos');
        $this->registerResident('Ana_Marie', 'Dela Cruz');

        $this->submitServiceRequest($plain, 'Barangay Clearance', 'Standard request');
        $this->submitServiceRequest($plain, 'Fee Discount', 'Requesting a 50% discount');
        $this->submitServiceRequest($plain, 'Records Update', 'Correct the entry for lot_12');

        $this->get('/residents?search=%25')
            ->assertOk()
            ->assertSee('No Residents found.');

        $this->get('/residents?search=_')
            ->assertOk()
            ->assertSee('Ana_Marie')
            ->assertDontSee('Santos');

        $this->get('/residents?search=a_i')
            ->assertOk()
            ->assertSee('No Residents found.');

        $this->get('/residents?search=ar')
            ->assertOk()
            ->assertSee('Santos')
            ->assertSee('Ana_Marie');

        $this->get('/service-requests?search=%25')
            ->assertOk()
            ->assertSee('Fee Discount')
            ->assertDontSee('Barangay Clearance')
            ->assertDontSee('Records Update');

        $this->get('/service-requests?search=lot_1')
            ->assertOk()
            ->assertSee('Records Update')
            ->assertDontSee('Fee Discount');

        $this->get('/service-requests?search=c_e')
            ->assertOk()
            ->assertSee('No Service Requests found.');

        $this->get('/service-requests?search='.urlencode('!'))
            ->assertOk()
            ->assertSee('No Service Requests found.');
    }

    /**
     * Navigation: the application entry page must lead to every module so
     * no URL has to be typed by hand.
     */
    public function test_every_module_is_reachable_from_the_home_page_and_from_each_other(): void
    {
        $links = [
            'href="'.route('dashboard').'"',
            'href="'.route('residents.index').'"',
            'href="'.route('service-requests.index').'"',
        ];

        foreach (['/', '/dashboard', '/residents', '/residents/register', '/service-requests', '/service-requests/new', '/residents/999999/edit'] as $url) {
            $response = $this->get($url);

            foreach ($links as $link) {
                $response->assertSee($link, false);
            }
        }
    }
}
