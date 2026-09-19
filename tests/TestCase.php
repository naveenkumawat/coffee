<?php

namespace Tests;

use App\Events\Realtime\PublicCacheInvalidated;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([PublicCacheInvalidated::class]);
    }

    public function createApplication(): Application
    {
        $this->forgetBootstrapCaches();

        return parent::createApplication();
    }

    /**
     * Developer `config:cache` / `route:cache` from a local APP_URL path must not
     * leak into PHPUnit (phpunit.xml forces APP_URL=http://localhost).
     */
    protected function forgetBootstrapCaches(): void
    {
        $cachePath = dirname(__DIR__).'/bootstrap/cache';

        foreach (glob($cachePath.'/*.php') ?: [] as $file) {
            $basename = basename($file);

            if (in_array($basename, ['packages.php', 'services.php'], true)) {
                continue;
            }

            @unlink($file);
        }
    }
}
