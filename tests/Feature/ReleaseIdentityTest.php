<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * T16 release-version verification. The running application must identify
 * itself as CSMS 1.0.0, everywhere, from one source.
 */
class ReleaseIdentityTest extends TestCase
{
    public function test_release_identity_is_csms_version_1_0_0(): void
    {
        $this->assertSame('Community Services Management System', config('csms.name'));
        $this->assertSame('CSMS', config('csms.short_name'));
        $this->assertSame('1.0.0', config('csms.version'));
    }

    public function test_health_reports_csms_release_1_0_0_and_ok_status(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertExactJson([
            'status' => 'ok',
            'application' => 'Community Services Management System',
            'short_name' => 'CSMS',
            'version' => '1.0.0',
        ]);
    }

    public function test_health_exposes_no_environment_or_configuration_details(): void
    {
        $body = $this->get('/health')->getContent();

        foreach ([
            (string) config('app.key'),
            (string) config('app.env'),
            (string) config('database.default'),
            (string) config('database.connections.sqlite.database'),
            base_path(),
        ] as $internalValue) {
            if ($internalValue !== '') {
                $this->assertStringNotContainsString($internalValue, $body);
            }
        }
    }

    public function test_every_page_shows_the_same_release_version_as_health(): void
    {
        $healthVersion = $this->get('/health')->json('version');

        $this->assertSame('1.0.0', $healthVersion);

        $this->get('/')->assertOk()->assertSee($healthVersion);

        foreach (['/dashboard', '/residents', '/residents/register', '/service-requests', '/service-requests/new'] as $url) {
            $response = $this->get($url);

            $response->assertOk();
            $response->assertSee('<span data-release-version>v'.$healthVersion.'</span>', false);
        }
    }

    /**
     * Changing the one configured version must change every place that
     * reports it, which proves no page or endpoint keeps its own copy.
     */
    public function test_changing_the_configured_version_changes_every_reported_version(): void
    {
        config(['csms.version' => '9.9.9-test']);

        $this->get('/health')->assertJsonPath('version', '9.9.9-test');
        $this->get('/')->assertSee('9.9.9-test')->assertDontSee('1.0.0');
        $this->get('/residents')
            ->assertSee('v9.9.9-test')
            ->assertDontSee('v1.0.0');
    }
}
