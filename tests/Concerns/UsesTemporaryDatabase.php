<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gives a test class its own freshly migrated SQLite database file.
 *
 * Call createTemporaryDatabase() from setUp() after parent::setUp(), and
 * removeTemporaryDatabase() from tearDown() before parent::tearDown().
 */
trait UsesTemporaryDatabase
{
    private string $databasePath;

    protected function createTemporaryDatabase(string $prefix): void
    {
        $path = tempnam(
            sys_get_temp_dir(),
            $prefix
        );

        if ($path === false) {
            throw new RuntimeException(
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

    protected function removeTemporaryDatabase(): void
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
    }
}
