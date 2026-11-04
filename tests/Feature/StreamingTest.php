<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Support\TemporaryFile;
use Javaabu\Exports\Tests\Concerns\SeedsUsers;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use Javaabu\Exports\Tests\TestSupport\Models\Role;
use Javaabu\Exports\Tests\TestSupport\Models\User;
use PHPUnit\Framework\Attributes\Test;

class StreamingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsUsers;

    #[Test]
    public function it_reads_the_query_a_chunk_at_a_time(): void
    {
        config()->set('exports.chunk_size', 10);

        $this->seedUsers(35);

        DB::enableQueryLog();

        $count = UsersExport::make()->writeTo($path = TemporaryFile::path(Formats::CSV));

        $user_queries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_starts_with($query['query'], 'select * from "users"'));

        $this->assertEquals(35, $count);
        $this->assertCount(4, $user_queries);
        $this->assertStringContainsString('"users"."id" > ?', $user_queries->last()['query']);
        $this->assertCount(36, $this->readSpreadsheet($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_eager_loads_relations_for_every_chunk(): void
    {
        config()->set('exports.chunk_size', 10);

        $role = Role::factory()->create(['name' => 'ވެރިޔާ']);

        $this->seedUsers(25, role_id: $role->id);

        DB::enableQueryLog();

        UsersExport::make()->writeTo($path = TemporaryFile::path(Formats::CSV));

        $role_queries = collect(DB::getQueryLog())
            ->filter(fn (array $query) => str_starts_with($query['query'], 'select * from "test_roles"'));

        $this->assertCount(3, $role_queries);
        $this->assertEquals('ވެރިޔާ', $this->readSpreadsheet($path)[25][10]);

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_keeps_the_order_of_an_ordered_query(): void
    {
        config()->set('exports.chunk_size', 2);

        User::factory()->create(['name' => 'ބ']);
        User::factory()->create(['name' => 'އ']);
        User::factory()->create(['name' => 'ދ']);

        UsersExport::make(User::query()->orderBy('name', 'desc'))->writeTo($path = TemporaryFile::path(Formats::CSV));

        $names = array_column(array_slice($this->readSpreadsheet($path), 1), 2);

        // SQLite compares by code point: ދ (U+078B), އ (U+0787), ބ (U+0784)
        $this->assertEquals(['ދ', 'އ', 'ބ'], $names);

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_exports_a_relation_query(): void
    {
        $role = Role::factory()->create();

        User::factory()->count(2)->create(['role_id' => $role->id]);
        User::factory()->create();

        $export = UsersExport::make($role->hasMany(User::class, 'role_id'));

        $this->assertEquals(2, $export->totalRows());
        $this->assertCount(2, iterator_to_array($export->rows(), false));
    }

    #[Test]
    public function it_counts_the_rows_of_the_query(): void
    {
        $this->seedUsers(12);

        $this->assertEquals(12, UsersExport::make()->totalRows());
        $this->assertEquals(3, UsersExport::make(User::query()->where('id', '<=', 3))->totalRows());
    }

    #[Test]
    public function it_uses_flat_memory_however_many_rows_there_are(): void
    {
        config()->set('exports.chunk_size', 500);

        $this->seedUsers(2000);
        $small = $this->peakMemoryOfExport(Formats::XLSX);

        $this->seedUsers(18000);
        $large = $this->peakMemoryOfExport(Formats::XLSX);

        $this->assertLessThan(
            4 * 1024 * 1024,
            $large - $small,
            "Exporting 20,000 rows used {$this->megabytes($large)} MB at peak, against {$this->megabytes($small)} MB for 2,000.",
        );
    }

    protected function peakMemoryOfExport(Formats $format): int
    {
        $path = TemporaryFile::path($format);

        gc_collect_cycles();
        $before = memory_get_usage();
        memory_reset_peak_usage();

        UsersExport::make()->writeTo($path);

        $peak = memory_get_peak_usage() - $before;

        TemporaryFile::delete($path);

        return $peak;
    }

    protected function megabytes(int $bytes): string
    {
        return number_format($bytes / 1024 / 1024, 1);
    }
}
