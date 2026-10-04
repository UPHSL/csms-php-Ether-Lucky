<?php

namespace Tests\Feature;

use App\Models\Resident;
use App\Repositories\ResidentRepository;
use App\Services\ResidentQueryService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResidentQueryServiceTest extends TestCase
{
    private string $databasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $path = tempnam(
            sys_get_temp_dir(),
            'csms_t05_'
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

    private function makeResident(
        string $firstName,
        string $lastName,
        string $contactNumber,
        string $email,
        string $status = 'Active'
    ): Resident {
        $resident = new Resident([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'address' => 'Barangay Santo Tomas',
            'contact_number' => $contactNumber,
            'email' => $email,
        ]);

        if ($status !== 'Active') {
            $resident->status = $status;
        }

        return $resident;
    }

    private function makeQueryService(): array
    {
        $repository =
            new ResidentRepository;

        $service =
            new ResidentQueryService(
                $repository
            );

        return [
            $service,
            $repository,
        ];
    }

    private function saveResident(
        ResidentRepository $repository,
        Resident $resident
    ): Resident {
        return $repository->save(
            $resident
        );
    }

    public function test_lists_all_persisted_residents(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Juan',
                'Cruz',
                '09171234561',
                'juan@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Maria',
                'Santos',
                '09171234562',
                'maria@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Ana',
                'Reyes',
                '09171234563',
                'ana@example.com'
            )
        );

        $residents =
            $service->listResidents();

        $this->assertCount(
            3,
            $residents
        );
    }

    public function test_empty_listing_returns_empty_collection(): void
    {
        [$service] =
            $this->makeQueryService();

        $residents =
            $service->listResidents();

        $this->assertNotNull(
            $residents
        );

        $this->assertCount(
            0,
            $residents
        );

        $this->assertTrue(
            $residents->isEmpty()
        );
    }

    public function test_listing_uses_required_ordering(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Ana',
                'Santos',
                '09171234561',
                'ana.santos@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Pedro',
                'Cruz',
                '09171234562',
                'pedro.cruz@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Maria',
                'Andres',
                '09171234563',
                'maria.andres@example.com'
            )
        );

        $firstJuan =
            $this->saveResident(
                $repository,
                $this->makeResident(
                    'Juan',
                    'Cruz',
                    '09171234564',
                    'juan.one@example.com'
                )
            );

        $secondJuan =
            $this->saveResident(
                $repository,
                $this->makeResident(
                    'Juan',
                    'Cruz',
                    '09171234565',
                    'juan.two@example.com'
                )
            );

        $residents =
            $service->listResidents();

        $this->assertCount(
            5,
            $residents
        );

        $this->assertSame(
            'Andres',
            $residents[0]->last_name
        );

        $this->assertSame(
            'Maria',
            $residents[0]->first_name
        );

        $this->assertSame(
            'Cruz',
            $residents[1]->last_name
        );

        $this->assertSame(
            'Juan',
            $residents[1]->first_name
        );

        $this->assertSame(
            $firstJuan->id,
            $residents[1]->id
        );

        $this->assertSame(
            $secondJuan->id,
            $residents[2]->id
        );

        $this->assertSame(
            'Pedro',
            $residents[3]->first_name
        );

        $this->assertSame(
            'Santos',
            $residents[4]->last_name
        );
    }

    public function test_searches_partial_first_name_case_insensitively(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Juan',
                'Dela Cruz',
                '09171234561',
                'juan@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Maria',
                'Santos',
                '09171234562',
                'maria@example.com'
            )
        );

        $results =
            $service->searchResidents(
                '   jUa   '
            );

        $this->assertCount(
            1,
            $results
        );

        $this->assertSame(
            'Juan',
            $results[0]->first_name
        );
    }

    public function test_searches_partial_last_name_case_insensitively(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Juan',
                'Dela Cruz',
                '09171234561',
                'juan@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Maria',
                'Santos',
                '09171234562',
                'maria@example.com'
            )
        );

        $results =
            $service->searchResidents(
                'cRuZ'
            );

        $this->assertCount(
            1,
            $results
        );

        $this->assertSame(
            'Dela Cruz',
            $results[0]->last_name
        );
    }

    public function test_blank_search_returns_all_residents(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Juan',
                'Cruz',
                '09171234561',
                'juan@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Maria',
                'Santos',
                '09171234562',
                'maria@example.com'
            )
        );

        $listed =
            $service->listResidents();

        $searched =
            $service->searchResidents(
                '       '
            );

        $this->assertSame(
            $listed->pluck('id')->all(),
            $searched->pluck('id')->all()
        );
    }

    public function test_search_with_no_match_returns_empty_collection(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Juan',
                'Cruz',
                '09171234561',
                'juan@example.com'
            )
        );

        $results =
            $service->searchResidents(
                'ZzzUnknownResident'
            );

        $this->assertNotNull(
            $results
        );

        $this->assertTrue(
            $results->isEmpty()
        );
    }

    public function test_search_result_preserves_resident_information(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $saved =
            $this->saveResident(
                $repository,
                $this->makeResident(
                    'Juan',
                    'Dela Cruz',
                    '09171234567',
                    'juan@example.com'
                )
            );

        $results =
            $service->searchResidents(
                'Juan'
            );

        $this->assertCount(
            1,
            $results
        );

        $resident =
            $results[0];

        $this->assertSame(
            $saved->id,
            $resident->id
        );

        $this->assertSame(
            'Juan',
            $resident->first_name
        );

        $this->assertSame(
            'Dela Cruz',
            $resident->last_name
        );

        $this->assertSame(
            'Barangay Santo Tomas',
            $resident->address
        );

        $this->assertSame(
            '09171234567',
            $resident->contact_number
        );

        $this->assertSame(
            'juan@example.com',
            $resident->email
        );

        $this->assertSame(
            'Active',
            $resident->status
        );
    }

    public function test_listing_includes_active_and_inactive_residents(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Juan',
                'Cruz',
                '09171234561',
                'juan@example.com'
            )
        );

        $this->saveResident(
            $repository,
            $this->makeResident(
                'Maria',
                'Santos',
                '09171234562',
                'maria@example.com',
                'Inactive'
            )
        );

        $residents =
            $service->listResidents();

        $statuses =
            $residents
                ->pluck('status')
                ->all();

        $this->assertContains(
            'Active',
            $statuses
        );

        $this->assertContains(
            'Inactive',
            $statuses
        );
    }

    public function test_matching_resident_appears_only_once(): void
    {
        [$service, $repository] =
            $this->makeQueryService();

        $saved =
            $this->saveResident(
                $repository,
                $this->makeResident(
                    'Ana',
                    'Anaya',
                    '09171234561',
                    'ana@example.com'
                )
            );

        $results =
            $service->searchResidents(
                'ana'
            );

        $this->assertCount(
            1,
            $results
        );

        $this->assertSame(
            $saved->id,
            $results[0]->id
        );
    }
}
