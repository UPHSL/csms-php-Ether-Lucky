<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Models\ServiceRequest;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\UsesTemporaryDatabase;
use Tests\TestCase;

/**
 * T16 Final Examination integration test.
 *
 * Drives the released application only through its browser routes, mixing
 * valid operations with operations the business rules must reject, and then
 * checks that persistence, the query pages and the dashboard all describe
 * the same final state.
 */
class FinalExaminationTest extends TestCase
{
    use UsesTemporaryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryDatabase('csms_t16_');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDatabase();

        parent::tearDown();
    }

    private function metric(TestResponse $dashboard, string $name): int
    {
        $this->assertSame(
            1,
            preg_match(
                '/data-metric="'.preg_quote($name, '/').'">(\d+)</',
                $dashboard->getContent(),
                $matches
            ),
            'Dashboard metric not rendered: '.$name
        );

        return (int) $matches[1];
    }

    private function submit(Resident $resident, string $serviceType, string $date): TestResponse
    {
        return $this->post('/service-requests', [
            'resident_id' => (string) $resident->id,
            'service_type' => $serviceType,
            'description' => $serviceType.' requested on '.$date,
            'date_requested' => $date,
        ]);
    }

    private function serviceRequestOfType(string $serviceType): ServiceRequest
    {
        return ServiceRequest::query()
            ->where('service_type', $serviceType)
            ->sole();
    }

    public function test_rejected_operations_never_reach_persistence_and_dashboard_matches_stored_records(): void
    {
        // Starting state: an empty, freshly migrated database.
        $empty = $this->get('/dashboard');

        $this->assertSame(0, $this->metric($empty, 'total-residents'));
        $this->assertSame(0, $this->metric($empty, 'total-service-requests'));

        // Resident registration (T02/T04/T12): two valid, one rejected.
        $this->post('/residents', [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
        ])->assertRedirect('/residents');

        $this->post('/residents', [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'address' => 'Purok 1',
            'contact_number' => '09181234567',
            'email' => 'juan@example.com',
        ])->assertRedirect('/residents');

        $this->post('/residents', [
            'first_name' => 'Pedro',
            'last_name' => 'Reyes',
            'address' => 'Purok 2',
            'contact_number' => '12345',
            'email' => 'pedro@example.com',
        ])->assertSessionHasErrors(['contact_number']);

        $maria = Resident::query()->where('email', 'maria@example.com')->sole();
        $juan = Resident::query()->where('email', 'juan@example.com')->sole();

        // Resident update (T06/T12): Juan moves; an invalid change is rejected.
        $this->put('/residents/'.$juan->id, [
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'address' => 'Purok 7, Barangay San Antonio',
            'contact_number' => '09181234567',
            'email' => 'juan@example.com',
        ])->assertRedirect('/residents');

        $this->put('/residents/'.$juan->id, [
            'first_name' => '',
            'last_name' => 'Cruz',
            'address' => 'Should not be saved',
            'contact_number' => '09181234567',
            'email' => 'juan@example.com',
        ])->assertSessionHasErrors(['first_name']);

        // Service Request submission (T09/T13): four valid requests.
        $this->submit($maria, 'Barangay Clearance', '2026-11-02')->assertRedirect('/service-requests');
        $this->submit($maria, 'Community Assistance', '2026-11-05')->assertRedirect('/service-requests');
        $this->submit($maria, 'Certificate of Residency', '2026-11-05')->assertRedirect('/service-requests');
        $this->submit($juan, 'Senior Citizen Assistance', '2026-11-08')->assertRedirect('/service-requests');

        // Resident deactivation (T07) blocks Juan's next submission (T09).
        $this->post('/residents/'.$juan->id.'/deactivate')->assertRedirect('/residents');
        $this->submit($juan, 'Rejected After Deactivation', '2026-11-09')
            ->assertSessionHasErrors(['resident_id']);

        // Workflow (T10/T13): valid paths plus rejected transitions.
        $clearance = $this->serviceRequestOfType('Barangay Clearance');
        $assistance = $this->serviceRequestOfType('Community Assistance');
        $residency = $this->serviceRequestOfType('Certificate of Residency');
        $senior = $this->serviceRequestOfType('Senior Citizen Assistance');

        $this->post('/service-requests/'.$clearance->id.'/start')->assertSessionHas('success');
        $this->post('/service-requests/'.$clearance->id.'/complete')->assertSessionHas('success');
        $this->post('/service-requests/'.$clearance->id.'/cancel')->assertSessionHas('error');

        $this->post('/service-requests/'.$assistance->id.'/cancel')->assertSessionHas('success');
        $this->post('/service-requests/'.$assistance->id.'/start')->assertSessionHas('error');

        $this->post('/service-requests/'.$residency->id.'/complete')->assertSessionHas('error');

        $this->post('/service-requests/'.$senior->id.'/start')->assertSessionHas('success');

        // Final persisted state: exactly what the valid operations produced.
        $this->assertSame(2, Resident::count());
        $this->assertSame(0, Resident::query()->where('first_name', 'Pedro')->count());

        $maria = Resident::find($maria->id);
        $juan = Resident::find($juan->id);

        $this->assertSame('Active', $maria->status);
        $this->assertSame('Inactive', $juan->status);
        $this->assertSame('Juan', $juan->first_name);
        $this->assertSame('Purok 7, Barangay San Antonio', $juan->address);

        $this->assertSame(4, ServiceRequest::count());
        $this->assertSame(0, ServiceRequest::query()->where('service_type', 'Rejected After Deactivation')->count());

        $this->assertSame('Completed', ServiceRequest::find($clearance->id)->status);
        $this->assertSame('Cancelled', ServiceRequest::find($assistance->id)->status);
        $this->assertSame('Pending', ServiceRequest::find($residency->id)->status);
        $this->assertSame('In Progress', ServiceRequest::find($senior->id)->status);
        $this->assertSame($juan->id, ServiceRequest::find($senior->id)->resident_id);

        // Dashboard (T14) agrees with the stored rows, counted independently.
        $dashboard = $this->get('/dashboard');

        $dashboard->assertOk();

        $this->assertSame(Resident::count(), $this->metric($dashboard, 'total-residents'));
        $this->assertSame(Resident::where('status', 'Active')->count(), $this->metric($dashboard, 'active-residents'));
        $this->assertSame(Resident::where('status', 'Inactive')->count(), $this->metric($dashboard, 'inactive-residents'));
        $this->assertSame(ServiceRequest::count(), $this->metric($dashboard, 'total-service-requests'));

        foreach ([
            'Pending' => 'pending-service-requests',
            'In Progress' => 'in-progress-service-requests',
            'Completed' => 'completed-service-requests',
            'Cancelled' => 'cancelled-service-requests',
        ] as $status => $metric) {
            $this->assertSame(
                ServiceRequest::where('status', $status)->count(),
                $this->metric($dashboard, $metric)
            );
        }

        $this->assertSame(2, $this->metric($dashboard, 'open-service-requests'));

        $dashboard->assertSeeInOrder([
            'data-recent-service-request="'.$senior->id.'"',
            'data-recent-service-request="'.$residency->id.'"',
            'data-recent-service-request="'.$assistance->id.'"',
            'data-recent-service-request="'.$clearance->id.'"',
        ], false);

        // Queries (T05/T11) return the same final state.
        $this->get('/service-requests?search=assistance&status=In+Progress')
            ->assertOk()
            ->assertSee('Senior Citizen Assistance')
            ->assertDontSee('Community Assistance');

        $this->get('/residents?search=cruz')
            ->assertOk()
            ->assertSee('Purok 7, Barangay San Antonio')
            ->assertSee('Inactive');
    }
}
