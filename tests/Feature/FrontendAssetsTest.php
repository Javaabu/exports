<?php

namespace Javaabu\Exports\Tests\Feature;

use Javaabu\Exports\Tests\TestCase;
use Javaabu\Manifest\Manifest;
use PHPUnit\Framework\Attributes\Test;

class FrontendAssetsTest extends TestCase
{
    #[Test]
    public function it_registers_its_pages_and_scripts_with_manifest(): void
    {
        $manifest = app(Manifest::class);

        $this->assertTrue($manifest->has('exports::progress'));

        $package = $manifest->package('exports');

        $this->assertEquals('@javaabu/exports', $package->importAlias());
        $this->assertEquals('js/Pages', $package->pagesPath());
        $this->assertArrayHasKey('@javaabu/forms', $package->npmDependencies());
        $this->assertArrayHasKey('react', $package->npmPeerDependencies());

        $this->artisan('manifest:list', ['package' => 'exports'])->assertSuccessful();
    }
}
