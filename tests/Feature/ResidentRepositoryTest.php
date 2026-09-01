<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ResidentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new ResidentRepository;
    }

    private function makeResident(array $overrides = []): Resident
    {
        return new Resident(array_merge([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'status' => 'Active',
        ], $overrides));
    }

    public function test_a_valid_resident_can_be_persisted(): void
    {
        $resident = $this->repository->save($this->makeResident());

        $this->assertTrue($resident->exists);
        $this->assertDatabaseHas('residents', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'contact_number' => '09171234567',
        ]);
    }

    public function test_a_persisted_resident_receives_a_database_generated_identifier(): void
    {
        $resident = $this->makeResident();

        $this->assertNull($resident->id);

        $saved = $this->repository->save($resident);

        $this->assertNotNull($saved->id);
        $this->assertIsInt($saved->id);
        $this->assertGreaterThan(0, $saved->id);
    }

    public function test_a_resident_can_be_retrieved_by_identifier(): void
    {
        $saved = $this->repository->save($this->makeResident());

        $found = $this->repository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertSame($saved->id, $found->id);
        $this->assertSame('Juan', $found->first_name);
    }

    public function test_resident_information_is_preserved_after_retrieval(): void
    {
        $saved = $this->repository->save($this->makeResident());

        $found = $this->repository->findById($saved->id);

        $this->assertSame('Juan', $found->first_name);
        $this->assertSame('Dela Cruz', $found->last_name);
        $this->assertSame('Barangay Santo Tomas', $found->address);
        $this->assertSame('09171234567', $found->contact_number);
        $this->assertSame('juan@example.com', $found->email);
        $this->assertSame('Active', $found->status);
    }

    public function test_active_status_is_preserved_after_persistence(): void
    {
        $resident = $this->makeResident();

        $this->assertSame('Active', $resident->status);

        $saved = $this->repository->save($resident);

        $this->assertSame('Active', $this->repository->findById($saved->id)->status);
    }

    public function test_missing_resident_returns_null(): void
    {
        $this->assertNull($this->repository->findById(999999));
    }

    public function test_persisted_resident_is_available_to_a_new_repository_instance(): void
    {
        $saved = (new ResidentRepository)->save($this->makeResident());

        $anotherRepository = new ResidentRepository;
        $found = $anotherRepository->findById($saved->id);

        $this->assertNotNull($found);
        $this->assertSame($saved->id, $found->id);
        $this->assertSame('09171234567', $found->contact_number);
    }

    public function test_saving_multiple_residents_assigns_distinct_identifiers(): void
    {
        $first = $this->repository->save($this->makeResident([
            'email' => 'first@example.com',
        ]));

        $second = $this->repository->save($this->makeResident([
            'first_name' => 'Maria',
            'email' => 'second@example.com',
        ]));

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('Juan', $this->repository->findById($first->id)->first_name);
        $this->assertSame('Maria', $this->repository->findById($second->id)->first_name);
        $this->assertDatabaseCount('residents', 2);
    }
}
