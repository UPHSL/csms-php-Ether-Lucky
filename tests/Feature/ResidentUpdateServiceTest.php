<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use App\Services\ResidentQueryService;
use App\Services\ResidentUpdateService;
use App\Services\ResidentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentUpdateServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResidentRepository $repository;

    private ResidentUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ResidentRepository;
        $this->service = new ResidentUpdateService(
            new ResidentValidator,
            $this->repository
        );
    }

    private function persistResident(array $overrides = []): Resident
    {
        return $this->repository->save(new Resident(array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ], $overrides)));
    }

    private function validChanges(): array
    {
        return [
            'first_name' => 'Juan Miguel',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay San Isidro',
            'contact_number' => '09181234567',
            'email' => 'juan.miguel@example.com',
        ];
    }

    public function test_valid_resident_update_succeeds(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->updateResident($resident->id, $this->validChanges());

        $this->assertTrue($result->success);
        $this->assertFalse($result->notFound);
        $this->assertSame([], $result->errors);
        $this->assertNotNull($result->resident);
    }

    public function test_resident_id_is_preserved(): void
    {
        $resident = $this->persistResident();
        $originalId = $resident->id;

        $result = $this->service->updateResident($originalId, array_merge(
            $this->validChanges(),
            ['id' => 999]
        ));

        $this->assertTrue($result->success);
        $this->assertSame($originalId, $result->resident->id);
        $this->assertDatabaseCount('residents', 1);
        $this->assertNull($this->repository->findById(999));
    }

    public function test_permitted_resident_information_is_persisted(): void
    {
        $resident = $this->persistResident();

        $this->service->updateResident($resident->id, $this->validChanges());

        $stored = $this->repository->findById($resident->id);

        $this->assertSame('Juan Miguel', $stored->first_name);
        $this->assertSame('Dela Cruz', $stored->last_name);
        $this->assertSame('Barangay San Isidro', $stored->address);
        $this->assertSame('09181234567', $stored->contact_number);
        $this->assertSame('juan.miguel@example.com', $stored->email);
    }

    public function test_resident_status_is_preserved(): void
    {
        $active = $this->persistResident();
        $inactive = $this->persistResident([
            'email' => 'inactive@example.com',
            'status' => 'Inactive',
        ]);

        $activeResult = $this->service->updateResident($active->id, array_merge(
            $this->validChanges(),
            ['status' => 'Inactive']
        ));

        $inactiveResult = $this->service->updateResident($inactive->id, array_merge(
            $this->validChanges(),
            ['status' => 'Active']
        ));

        $this->assertTrue($activeResult->success);
        $this->assertTrue($inactiveResult->success);
        $this->assertSame('Active', $this->repository->findById($active->id)->status);
        $this->assertSame('Inactive', $this->repository->findById($inactive->id)->status);
    }

    public function test_invalid_update_fails(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->updateResident($resident->id, array_merge(
            $this->validChanges(),
            ['first_name' => '']
        ));

        $this->assertFalse($result->success);
        $this->assertFalse($result->notFound);
        $this->assertNull($result->resident);
        $this->assertContains('first_name', $result->errors);
    }

    public function test_invalid_update_does_not_modify_persisted_information(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->updateResident($resident->id, [
            'first_name' => '',
            'last_name' => 'Santos',
            'contact_number' => 'ABC',
        ]);

        $stored = $this->repository->findById($resident->id);

        $this->assertFalse($result->success);
        $this->assertContains('first_name', $result->errors);
        $this->assertContains('contact_number', $result->errors);
        $this->assertSame('Juan', $stored->first_name);
        $this->assertSame('Cruz', $stored->last_name);
        $this->assertSame('Barangay Santo Tomas', $stored->address);
        $this->assertSame('09171234567', $stored->contact_number);
        $this->assertSame('juan@example.com', $stored->email);
        $this->assertSame('Active', $stored->status);
    }

    public function test_updating_a_nonexistent_resident_is_handled_safely(): void
    {
        $result = $this->service->updateResident(999999, $this->validChanges());

        $this->assertFalse($result->success);
        $this->assertTrue($result->notFound);
        $this->assertNull($result->resident);
        $this->assertSame([], $result->errors);
    }

    public function test_nonexistent_update_does_not_create_a_resident(): void
    {
        $this->persistResident();
        $countBefore = Resident::query()->count();

        $this->service->updateResident(999999, $this->validChanges());

        $this->assertSame($countBefore, Resident::query()->count());
        $this->assertNull($this->repository->findById(999999));
    }

    public function test_updated_resident_is_visible_through_t05_querying(): void
    {
        $resident = $this->persistResident();
        $queryService = new ResidentQueryService($this->repository);

        $this->service->updateResident($resident->id, array_merge(
            $this->validChanges(),
            ['first_name' => 'Miguel', 'last_name' => 'Santos']
        ));

        $results = $queryService->searchResidents('miguel');

        $this->assertCount(1, $results);
        $this->assertSame($resident->id, $results[0]->id);
        $this->assertSame('Santos', $results[0]->last_name);
        $this->assertTrue($queryService->searchResidents('Cruz')->isEmpty());
        $this->assertSame('Miguel', $queryService->listResidents()[0]->first_name);
    }

    public function test_updated_information_and_contact_number_are_preserved(): void
    {
        $resident = $this->persistResident();
        $other = $this->persistResident([
            'first_name' => 'Maria',
            'last_name' => 'Reyes',
            'contact_number' => '09991234567',
            'email' => 'maria@example.com',
        ]);

        $this->service->updateResident($resident->id, $this->validChanges());

        $stored = $this->repository->findById($resident->id);

        $this->assertSame($resident->id, $stored->id);
        $this->assertSame('09181234567', $stored->contact_number);
        $this->assertSame('Juan Miguel', $stored->first_name);
        $this->assertSame('Dela Cruz', $stored->last_name);
        $this->assertSame('Barangay San Isidro', $stored->address);
        $this->assertSame('juan.miguel@example.com', $stored->email);
        $this->assertSame('Active', $stored->status);

        $untouched = $this->repository->findById($other->id);

        $this->assertSame('Maria', $untouched->first_name);
        $this->assertSame('Reyes', $untouched->last_name);
        $this->assertSame('09991234567', $untouched->contact_number);
        $this->assertSame('maria@example.com', $untouched->email);
    }
}
