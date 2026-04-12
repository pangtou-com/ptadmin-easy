<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Feature\Package;

use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use PTAdmin\Easy\Providers\EasyServiceProviders;

class EasyServiceProviderPublishTest extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EasyServiceProviders::class];
    }

    public function test_it_registers_ptadmin_publish_groups(): void
    {
        $allPublishes = ServiceProvider::pathsToPublish(EasyServiceProviders::class, 'ptadmin');
        $configPublishes = ServiceProvider::pathsToPublish(EasyServiceProviders::class, 'ptadmin-config');
        $migrationPublishes = ServiceProvider::pathsToPublish(EasyServiceProviders::class, 'ptadmin-migrations');
        $langPublishes = ServiceProvider::pathsToPublish(EasyServiceProviders::class, 'ptadmin-lang');

        $this->assertCount(3, $allPublishes);
        $this->assertCount(1, $configPublishes);
        $this->assertCount(1, $migrationPublishes);
        $this->assertCount(1, $langPublishes);
        $this->assertSame($configPublishes + $migrationPublishes + $langPublishes, $allPublishes);
    }
}
