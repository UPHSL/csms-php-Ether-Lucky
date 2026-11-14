<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use App\Services\ResidentDeactivationService;
use App\Services\ResidentRegistrationService;
use Tests\Concerns\UsesTemporaryDatabase;
use Tests\TestCase;

class ResidentManagementInterfaceTest extends TestCase
{
    use UsesTemporaryDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTemporaryDatabase('csms_t12_');
    }

    protected function tearDown(): void
    {
        $this->removeTemporaryDatabase();

        parent::tearDown();
    }

    /**
     * Register a Resident through the T04 registration service.
     */
    private function registerResident(
        string $firstName = 'Maria',
        string $lastName = 'Santos',
        string $contactNumber = '09171234567',
        string $email = 'maria@example.com'
    ): Resident {
        $result = app(ResidentRegistrationService::class)->registerResident(
            new Resident([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'address' => 'Barangay Santo Tomas',
                'contact_number' => $contactNumber,
                'email' => $email,
            ])
        );

        $this->assertTrue($result->success);

        return $result->resident;
    }

    private function validInput(array $overrides = []): array
    {
        return array_merge(
            [
                'first_name' => 'Pedro',
                'last_name' => 'Reyes',
                'address' => 'Purok 2, Barangay San Antonio',
                'contact_number' => '09181234567',
                'email' => 'pedro@example.com',
            ],
            $overrides
        );
    }

    private function findResident(int $id): ?Resident
    {
        return (new ResidentRepository)->findById($id);
    }

    public function test_resident_management_page_loads_successfully(): void
    {
        $response = $this->get('/residents');

        $response->assertOk();
        $response->assertSee('Resident Management');
        $response->assertSee('Register Resident');
        $response->assertSee('name="search"', false);
        $response->assertSee('No Residents found.');
    }

    public function test_resident_list_displays_persisted_residents_in_query_order(): void
    {
        $santos = $this->registerResident('Maria', 'Santos', '09171234561', 'maria@example.com');
        $cruz = $this->registerResident('Juan', 'Cruz', '09171234562', 'juan@example.com');

        $response = $this->get('/residents');

        $response->assertOk();

        foreach ([$santos, $cruz] as $resident) {
            $response->assertSee($resident->first_name);
            $response->assertSee($resident->last_name);
            $response->assertSee($resident->address);
            $response->assertSee($resident->contact_number);
            $response->assertSee($resident->email);
        }

        $response->assertSee('Active');
        $response->assertSeeInOrder(['juan@example.com', 'maria@example.com']);
        $response->assertDontSee('No Residents found.');
    }

    public function test_resident_search_uses_existing_search_behavior(): void
    {
        $this->registerResident('Maria', 'Santos', '09171234561', 'maria@example.com');
        $this->registerResident('Juan', 'Cruz', '09171234562', 'juan@example.com');

        $response = $this->get('/residents?search='.urlencode('   sAnT   '));

        $response->assertOk();
        $response->assertSee('maria@example.com');
        $response->assertDontSee('juan@example.com');

        $noMatch = $this->get('/residents?search=ZzzNobody');

        $noMatch->assertOk();
        $noMatch->assertSee('No Residents found.');
        $noMatch->assertDontSee('maria@example.com');
    }

    public function test_blank_search_displays_all_residents(): void
    {
        $this->registerResident('Maria', 'Santos', '09171234561', 'maria@example.com');
        $this->registerResident('Juan', 'Cruz', '09171234562', 'juan@example.com');

        foreach (['', ' ', '       '] as $blank) {
            $response = $this->get('/residents?search='.urlencode($blank));

            $response->assertOk();
            $response->assertSeeInOrder(['juan@example.com', 'maria@example.com']);
        }
    }

    public function test_registration_form_is_accessible(): void
    {
        $response = $this->get('/residents/register');

        $response->assertOk();
        $response->assertSee('Register Resident');

        foreach (['First Name', 'Last Name', 'Address', 'Contact Number', 'Email'] as $label) {
            $response->assertSee($label);
        }

        foreach (['first_name', 'last_name', 'address', 'contact_number', 'email'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertDontSee('name="id"', false);
        $response->assertDontSee('name="status"', false);
    }

    public function test_valid_registration_persists_resident(): void
    {
        $response = $this->post('/residents', $this->validInput());

        $response->assertRedirect('/residents');
        $response->assertSessionHas('success', 'Resident registered successfully.');

        $this->assertSame(1, Resident::count());

        $resident = Resident::first();

        $this->assertNotNull($resident->id);
        $this->assertSame('Pedro', $resident->first_name);
        $this->assertSame('Reyes', $resident->last_name);
        $this->assertSame('Purok 2, Barangay San Antonio', $resident->address);
        $this->assertSame('09181234567', $resident->contact_number);
        $this->assertSame('pedro@example.com', $resident->email);
        $this->assertSame('Active', $resident->status);

        $list = $this->get('/residents');

        $list->assertSee('pedro@example.com');
        $list->assertSee('Active');
    }

    public function test_registration_ignores_submitted_id_and_status(): void
    {
        $this->post(
            '/residents',
            $this->validInput(['id' => 500, 'status' => 'Inactive'])
        )->assertRedirect('/residents');

        $resident = Resident::first();

        $this->assertNotSame(500, $resident->id);
        $this->assertSame('Active', $resident->status);
    }

    public function test_invalid_registration_shows_validation_feedback_and_does_not_persist(): void
    {
        $input = $this->validInput([
            'first_name' => '   ',
            'contact_number' => '12345',
            'email' => 'not-an-email',
        ]);

        $response = $this->post('/residents', $input);

        $response->assertRedirect('/residents/register');
        $response->assertSessionHasErrors(['first_name', 'contact_number', 'email']);
        $response->assertSessionDoesntHaveErrors(['last_name', 'address']);

        $this->assertSame(0, Resident::count());

        $form = $this->followingRedirects()->post('/residents', $input);

        $form->assertOk();
        $form->assertSee('Enter a first name.');
        $form->assertSee('Enter a contact number in the format 09XXXXXXXXX.');
        $form->assertSee('Enter a valid email address.');
        $form->assertSee('value="Reyes"', false);

        $this->assertSame(0, Resident::count());
    }

    public function test_edit_form_displays_existing_resident_information(): void
    {
        $resident = $this->registerResident();

        $response = $this->get('/residents/'.$resident->id.'/edit');

        $response->assertOk();
        $response->assertSee('Edit Resident');
        $response->assertSee('value="Maria"', false);
        $response->assertSee('value="Santos"', false);
        $response->assertSee('value="Barangay Santo Tomas"', false);
        $response->assertSee('value="09171234567"', false);
        $response->assertSee('value="maria@example.com"', false);
        $response->assertSee('Resident ID');
        $response->assertSee('Active');
        $response->assertDontSee('name="id"', false);
        $response->assertDontSee('name="status"', false);
    }

    public function test_valid_update_persists_changes(): void
    {
        $resident = $this->registerResident();

        $response = $this->put(
            '/residents/'.$resident->id,
            $this->validInput(['id' => 999, 'status' => 'Inactive'])
        );

        $response->assertRedirect('/residents');
        $response->assertSessionHas('success', 'Resident information updated successfully.');

        $this->assertSame(1, Resident::count());

        $updated = $this->findResident($resident->id);

        $this->assertSame($resident->id, $updated->id);
        $this->assertSame('Pedro', $updated->first_name);
        $this->assertSame('Reyes', $updated->last_name);
        $this->assertSame('Purok 2, Barangay San Antonio', $updated->address);
        $this->assertSame('09181234567', $updated->contact_number);
        $this->assertSame('pedro@example.com', $updated->email);
        $this->assertSame('Active', $updated->status);
    }

    public function test_invalid_update_does_not_modify_resident(): void
    {
        $resident = $this->registerResident();

        $before = $this->findResident($resident->id)->toArray();

        $response = $this->put(
            '/residents/'.$resident->id,
            $this->validInput(['contact_number' => 'invalid'])
        );

        $response->assertRedirect('/residents/'.$resident->id.'/edit');
        $response->assertSessionHasErrors(['contact_number']);

        $this->assertSame(
            $before,
            $this->findResident($resident->id)->toArray()
        );

        $form = $this->followingRedirects()->put(
            '/residents/'.$resident->id,
            $this->validInput(['contact_number' => 'invalid'])
        );

        $form->assertOk();
        $form->assertSee('Enter a contact number in the format 09XXXXXXXXX.');

        $this->assertSame(
            $before,
            $this->findResident($resident->id)->toArray()
        );
    }

    public function test_missing_resident_is_handled_safely(): void
    {
        $this->get('/residents/999999/edit')
            ->assertNotFound()
            ->assertSee('Resident not found.');

        $this->put('/residents/999999', $this->validInput())->assertNotFound();
        $this->post('/residents/999999/deactivate')->assertNotFound();
        $this->get('/residents/abc/edit')->assertNotFound();

        $this->assertSame(0, Resident::count());
    }

    public function test_active_resident_can_be_deactivated_through_the_interface(): void
    {
        $resident = $this->registerResident();

        $response = $this->post('/residents/'.$resident->id.'/deactivate');

        $response->assertRedirect('/residents');
        $response->assertSessionHas('success', 'Resident deactivated successfully.');

        $this->assertSame(1, Resident::count());

        $deactivated = $this->findResident($resident->id);

        $this->assertSame('Inactive', $deactivated->status);
        $this->assertSame('Maria', $deactivated->first_name);
        $this->assertSame('Santos', $deactivated->last_name);
        $this->assertSame('Barangay Santo Tomas', $deactivated->address);
        $this->assertSame('09171234567', $deactivated->contact_number);
        $this->assertSame('maria@example.com', $deactivated->email);

        $repeat = $this->post('/residents/'.$resident->id.'/deactivate');

        $repeat->assertRedirect('/residents');
        $repeat->assertSessionHas('info', 'Resident is already Inactive.');

        $this->assertSame('Inactive', $this->findResident($resident->id)->status);
    }

    public function test_inactive_resident_remains_visible_without_deactivate_action(): void
    {
        $resident = $this->registerResident();

        $this->get('/residents')->assertSee('Deactivate');

        app(ResidentDeactivationService::class)->deactivateResident($resident->id);

        foreach (['/residents', '/residents?search=santos'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('maria@example.com');
            $response->assertSee('Inactive');
            $response->assertDontSee('Deactivate');
        }
    }

    public function test_get_requests_do_not_modify_residents(): void
    {
        $resident = $this->registerResident();

        $before = Resident::query()->orderBy('id')->get()->toArray();

        $this->get('/residents')->assertOk();
        $this->get('/residents?search=maria')->assertOk();
        $this->get('/residents/register')->assertOk();
        $this->get('/residents/'.$resident->id.'/edit')->assertOk();
        $this->get('/residents/'.$resident->id.'/deactivate')->assertStatus(405);
        $this->get('/residents/'.$resident->id)->assertStatus(405);

        $this->assertSame(
            $before,
            Resident::query()->orderBy('id')->get()->toArray()
        );

        $this->assertSame('Active', $this->findResident($resident->id)->status);
    }
}
