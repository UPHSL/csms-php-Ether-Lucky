<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use App\Services\ResidentDeactivationService;
use App\Services\ResidentQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentDeactivationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ResidentRepository $repository;

    private ResidentDeactivationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ResidentRepository;
        $this->service = new ResidentDeactivationService($this->repository);
    }

    private function persistResident(array $overrides = []): Resident
    {
        return $this->repository->save(new Resident(array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ], $overrides)));
    }

    public function test_active_resident_can_be_deactivated(): void
    {
        $resident = $this->persistResident();

        $result = $this->service->deactivateResident($resident->id);

        $this->assertTrue($result->success);
        $this->assertFalse($result->alreadyInactive);
        $this->assertFalse($result->notFound);
        $this->assertSame('Inactive', $result->resident->status);
    }

    public function test_resident_status_becomes_inactive_in_persistence(): void
    {
        $resident = $this->persistResident();

        $this->service->deactivateResident($resident->id);

        $this->assertSame('Inactive', $this->repository->findById($resident->id)->status);
        $this->assertDatabaseHas('residents', [
            'id' => $resident->id,
            'status' => 'Inactive',
        ]);
    }

    public function test_resident_id_is_preserved(): void
    {
        $resident = $this->persistResident();
        $originalId = $resident->id;

        $result = $this->service->deactivateResident($originalId);

        $this->assertSame($originalId, $result->resident->id);
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_resident_information_is_preserved(): void
    {
        $resident = $this->persistResident();

        $this->service->deactivateResident($resident->id);

        $stored = $this->repository->findById($resident->id);

        $this->assertSame('Juan', $stored->first_name);
        $this->assertSame('Dela Cruz', $stored->last_name);
        $this->assertSame('Barangay Santo Tomas', $stored->address);
        $this->assertSame('09171234567', $stored->contact_number);
        $this->assertSame('juan@example.com', $stored->email);
    }

    public function test_deactivated_resident_remains_persisted_and_retrievable(): void
    {
        $resident = $this->persistResident();

        $this->service->deactivateResident($resident->id);

        $stored = (new ResidentRepository)->findById($resident->id);

        $this->assertNotNull($stored);
        $this->assertSame($resident->id, $stored->id);
        $this->assertSame('Inactive', $stored->status);
    }

    public function test_deactivated_resident_remains_available_through_t05(): void
    {
        $resident = $this->persistResident();
        $queryService = new ResidentQueryService($this->repository);

        $this->service->deactivateResident($resident->id);

        $searched = $queryService->searchResidents('juan');
        $listed = $queryService->listResidents();

        $this->assertCount(1, $searched);
        $this->assertSame($resident->id, $searched[0]->id);
        $this->assertSame('Inactive', $searched[0]->status);
        $this->assertCount(1, $listed);
        $this->assertSame('Inactive', $listed[0]->status);
    }

    public function test_already_inactive_resident_is_handled_safely(): void
    {
        $resident = $this->persistResident(['status' => 'Inactive']);

        $first = $this->service->deactivateResident($resident->id);
        $second = $this->service->deactivateResident($resident->id);

        $stored = $this->repository->findById($resident->id);

        $this->assertTrue($first->success);
        $this->assertTrue($first->alreadyInactive);
        $this->assertTrue($second->alreadyInactive);
        $this->assertSame($resident->id, $stored->id);
        $this->assertSame('Inactive', $stored->status);
        $this->assertSame('Juan', $stored->first_name);
        $this->assertSame('09171234567', $stored->contact_number);
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_nonexistent_resident_is_handled_safely(): void
    {
        $result = $this->service->deactivateResident(999999);

        $this->assertFalse($result->success);
        $this->assertTrue($result->notFound);
        $this->assertNull($result->resident);
    }

    public function test_nonexistent_deactivation_does_not_create_or_delete_records(): void
    {
        $existing = $this->persistResident();
        $countBefore = Resident::query()->count();

        $this->service->deactivateResident(999999);

        $this->assertSame($countBefore, Resident::query()->count());
        $this->assertNull($this->repository->findById(999999));
        $this->assertSame('Active', $this->repository->findById($existing->id)->status);
    }

    public function test_deactivating_one_resident_does_not_affect_another(): void
    {
        $target = $this->persistResident();
        $other = $this->persistResident([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'contact_number' => '09181234567',
            'email' => 'maria@example.com',
        ]);

        $this->service->deactivateResident($target->id);

        $storedOther = $this->repository->findById($other->id);

        $this->assertSame('Inactive', $this->repository->findById($target->id)->status);
        $this->assertSame('Active', $storedOther->status);
        $this->assertSame('Maria', $storedOther->first_name);
        $this->assertSame('Santos', $storedOther->last_name);
        $this->assertSame('09181234567', $storedOther->contact_number);
        $this->assertSame('maria@example.com', $storedOther->email);
    }
}
