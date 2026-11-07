<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stop tests from accessing the real HopSurigao database.
     * This runs before database test setup or migrations.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = (string) $app['config']->get(
            'database.default',
            ''
        );

        $settings = (array) $app['config']->get(
            "database.connections.{$connection}",
            []
        );

        $database = (string) ($settings['database'] ?? '');
        $url = $settings['url'] ?? null;

        // DB_URL can override DB_DATABASE.
        if (is_string($url) && $url !== '') {
            $path = parse_url($url, PHP_URL_PATH);

            $database = is_string($path)
                ? ltrim($path, '/')
                : '';
        }

        $isTestMysql = $connection === 'mysql'
            && $database === 'island_hopping_test';

        $isMemorySqlite = $connection === 'sqlite'
            && $database === ':memory:';

        if (
            ! $app->environment('testing')
            || (! $isTestMysql && ! $isMemorySqlite)
        ) {
            throw new RuntimeException(
                'SAFETY STOP: Tests must use APP_ENV=testing and '
                .'DB_DATABASE=island_hopping_test (or SQLite :memory:). '
                .'Current connection: '.$connection
                .'; database: '.$database.'. '
                .'Do not run tests against the main island-hopping database.'
            );
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must mock external API calls using Http::fake().
        // Prevent accidental real PayMongo or weather API requests.
        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(
        string $feature,
        ?string $message = null
    ): void {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped(
                $message
                    ?? "Fortify feature [{$feature}] is not enabled."
            );
        }
    }
}
