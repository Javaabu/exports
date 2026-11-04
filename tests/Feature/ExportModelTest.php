<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Javaabu\Exports\Enums\ExportStatuses;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Exports;
use Javaabu\Exports\Models\Export;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class ExportModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('exports');

        config()->set('exports.disk', 'exports');
    }

    #[Test]
    public function it_reports_progress(): void
    {
        $export = $this->makeExport(['status' => ExportStatuses::PROCESSING, 'total_rows' => 200, 'processed_rows' => 50]);

        $this->assertEquals(25, $export->progress());

        $export->total_rows = null;
        $this->assertNull($export->progress());

        $export->total_rows = 0;
        $this->assertEquals(100, $export->progress());

        $export->status = ExportStatuses::COMPLETED;
        $this->assertEquals(100, $export->progress());
    }

    #[Test]
    public function it_finds_exports_by_owner(): void
    {
        $ahmed = User::factory()->create();
        $aisha = User::factory()->create();

        $ahmeds = $this->makeExport(user: $ahmed);
        $this->makeExport(user: $aisha);

        $this->assertEquals([$ahmeds->id], Export::query()->ownedBy($ahmed)->pluck('id')->all());
        $this->assertTrue($ahmeds->isOwnedBy($ahmed));
        $this->assertFalse($ahmeds->isOwnedBy($aisha));
        $this->assertFalse($ahmeds->isOwnedBy(null));
        $this->assertTrue($ahmeds->user->is($ahmed));
    }

    #[Test]
    public function it_knows_when_it_can_be_downloaded(): void
    {
        $export = $this->completedExport();

        $this->assertTrue($export->isDownloadable());
        $this->assertTrue($export->fileExists());

        $this->travel(8)->days();

        $this->assertTrue($export->hasExpired());
        $this->assertFalse($export->isDownloadable());
        $this->assertNull($export->temporaryDownloadUrl());
    }

    #[Test]
    public function it_has_no_signed_link_without_the_signed_route(): void
    {
        $this->assertNull($this->completedExport()->temporaryDownloadUrl());
    }

    #[Test]
    public function it_streams_its_file(): void
    {
        $response = $this->completedExport()->download();

        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(rawurlencode('ބޭނުންތެރިން') . '.csv', $response->headers->get('Content-Disposition'));
    }

    #[Test]
    public function it_deletes_its_file_with_it(): void
    {
        $export = $this->completedExport();
        $path = $export->path;

        $export->delete();

        Storage::disk('exports')->assertMissing($path);
    }

    #[Test]
    public function it_uses_the_configured_model_and_table(): void
    {
        $this->assertEquals(Export::class, Exports::exportModel());
        $this->assertEquals('exports', (new Export)->getTable());
        $this->assertEquals('uuid', (new Export)->getRouteKeyName());
        $this->assertEquals('Pending', ExportStatuses::PENDING->label());
    }

    #[Test]
    public function it_prunes_expired_exports(): void
    {
        $expired = $this->completedExport();
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $stuck = $this->makeExport(['status' => ExportStatuses::PROCESSING]);
        $stuck->forceFill(['created_at' => now()->subDays(8)])->save();

        $recent = $this->completedExport();
        $old_but_valid = $this->completedExport();
        $old_but_valid->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->artisan('exports:prune --pretend')
            ->expectsOutputToContain('2 exports would be deleted.')
            ->assertSuccessful();

        $this->assertEquals(4, Export::count());

        $this->artisan('exports:prune')
            ->expectsOutputToContain('Deleted 2 exports.')
            ->assertSuccessful();

        $this->assertEquals([$recent->id, $old_but_valid->id], Export::pluck('id')->all());
        Storage::disk('exports')->assertMissing($expired->path);

        $this->artisan('exports:prune --days=2')
            ->expectsOutputToContain('Deleted 1 exports.')
            ->assertSuccessful();

        $this->assertEquals([$recent->id], Export::pluck('id')->all());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeExport(array $attributes = [], ?User $user = null): Export
    {
        $export = new Export;

        $export->forceFill(array_merge([
            'export_class' => UsersExport::class,
            'name' => 'Users',
            'format' => Formats::CSV,
            'disk' => 'exports',
            'filename' => 'ބޭނުންތެރިން.csv',
        ], $attributes));

        $export->user()->associate($user ?? User::factory()->create());
        $export->save();

        return $export;
    }

    protected function completedExport(): Export
    {
        $export = $this->makeExport();

        $path = "exports/{$export->uuid}/ބޭނުންތެރިން.csv";
        Storage::disk('exports')->put($path, 'a,b');

        $export->markCompleted('exports', $path, 'ބޭނުންތެރިން.csv', 1, 3);

        return $export;
    }
}
