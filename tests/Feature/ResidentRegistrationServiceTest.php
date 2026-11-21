<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use App\Services\ResidentRegistrationService;
use App\Services\ResidentValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidentRegistrationServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(
            sys_get_temp_dir(),
            'csms_t04_'
        );

        if ($path === false) {
            throw new \RuntimeException(
                'Unable to create temporary SQLite database.'
            );
        }

        $this->databasePath = $path;

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
        ]);

        DB::purge('sqlite');

        Artisan::call(
            'migrate:fresh',
            [
                '--database' => 'sqlite',
                '--force' => true,
            ]
        );
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::purge('sqlite');

        if (
            isset($this->databasePath)
            && is_file($this->databasePath)
        ) {
            unlink(
                $this->databasePath
            );
        }

        parent::tearDown();
    }

    private function makeValidResident(): Resident
    {
        return new Resident([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ]);
    }

    private function makeResidentWithMissingFirstName(): Resident
    {
        return new Resident([
            'first_name' => '',
            'last_name' => 'Dela Cruz',
            'address' => 'Barangay Santo Tomas',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ]);
    }

    private function makeRegistrationService(): array
    {
        $validator =
            new ResidentValidator;

        $repository =
            new ResidentRepository;

        $service =
            new ResidentRegistrationService(
                $validator,
                $repository
            );

        return [
            $service,
            $repository,
        ];
    }

    public function test_registers_a_valid_resident(): void
    {
        [$service] =
            $this->makeRegistrationService();

        $resident =
            $this->makeValidResident();

        $result =
            $service->registerResident(
                $resident
            );

        $this->assertTrue(
            $result->success
        );

        $this->assertNotNull(
            $result->resident
        );

        $this->assertSame(
            [],
            $result->errors
        );
    }

    public function test_registered_resident_receives_an_identifier(): void
    {
        [$service] =
            $this->makeRegistrationService();

        $resident =
            $this->makeValidResident();

        $this->assertNull(
            $resident->id
        );

        $result =
            $service->registerResident(
                $resident
            );

        $this->assertTrue(
            $result->success
        );

        $this->assertNotNull(
            $result->resident
        );

        $this->assertNotNull(
            $result->resident->id
        );
    }

    public function test_registered_resident_is_persisted(): void
    {
        [$service, $repository] =
            $this->makeRegistrationService();

        $resident =
            $this->makeValidResident();

        $result =
            $service->registerResident(
                $resident
            );

        $this->assertTrue(
            $result->success
        );

        $this->assertNotNull(
            $result->resident
        );

        $storedResident =
            $repository->findById(
                $result->resident->id
            );

        $this->assertNotNull(
            $storedResident
        );

        $this->assertSame(
            $result->resident->id,
            $storedResident->id
        );
    }

    public function test_registered_resident_information_is_preserved(): void
    {
        [$service, $repository] =
            $this->makeRegistrationService();

        $resident =
            $this->makeValidResident();

        $result =
            $service->registerResident(
                $resident
            );

        $storedResident =
            $repository->findById(
                $result->resident->id
            );

        $this->assertNotNull(
            $storedResident
        );

        $this->assertSame(
            'Juan',
            $storedResident->first_name
        );

        $this->assertSame(
            'Dela Cruz',
            $storedResident->last_name
        );

        $this->assertSame(
            'Barangay Santo Tomas',
            $storedResident->address
        );

        $this->assertSame(
            '09171234567',
            $storedResident->contact_number
        );

        $this->assertSame(
            'juan@example.com',
            $storedResident->email
        );

        $this->assertSame(
            'Active',
            $storedResident->status
        );
    }

    public function test_registration_preserves_default_active_status(): void
    {
        [$service] =
            $this->makeRegistrationService();

        $resident =
            $this->makeValidResident();

        $this->assertSame(
            'Active',
            $resident->status
        );

        $result =
            $service->registerResident(
                $resident
            );

        $this->assertTrue(
            $result->success
        );

        $this->assertSame(
            'Active',
            $result->resident->status
        );
    }

    public function test_invalid_resident_registration_fails(): void
    {
        [$service] =
            $this->makeRegistrationService();

        $resident =
            $this->makeResidentWithMissingFirstName();

        $result =
            $service->registerResident(
                $resident
            );

        $this->assertFalse(
            $result->success
        );

        $this->assertNull(
            $result->resident
        );

        $this->assertNotEmpty(
            $result->errors
        );
    }

    public function test_invalid_resident_is_not_persisted(): void
    {
        [$service] =
            $this->makeRegistrationService();

        $resident =
            $this->makeResidentWithMissingFirstName();

        $countBefore =
            DB::table('residents')
                ->count();

        $result =
            $service->registerResident(
                $resident
            );

        $countAfter =
            DB::table('residents')
                ->count();

        $this->assertFalse(
            $result->success
        );

        $this->assertSame(
            $countBefore,
            $countAfter
        );
    }

    public function test_registration_identifies_validation_failure(): void
    {
        [$service] =
            $this->makeRegistrationService();

        $resident =
            $this->makeResidentWithMissingFirstName();

        $result =
            $service->registerResident(
                $resident
            );

        $this->assertFalse(
            $result->success
        );

        $this->assertContains(
            'first_name',
            $result->errors
        );
    }
}
