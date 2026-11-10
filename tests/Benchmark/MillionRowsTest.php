<?php

namespace Javaabu\Exports\Tests\Benchmark;

use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\Support\TemporaryFile;
use Javaabu\Exports\Tests\Concerns\SeedsUsers;
use Javaabu\Exports\Tests\TestCase;
use Javaabu\Exports\Tests\TestSupport\Exports\UsersExport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exports a million rows and checks that memory stays flat
 *
 * Not part of the default suite. Run it with `composer benchmark`, and change the
 * number of rows with EXPORTS_BENCHMARK_ROWS.
 */
#[Group('benchmark')]
class MillionRowsTest extends TestCase
{
    use SeedsUsers;

    protected string $database_path;

    protected function setUp(): void
    {
        $this->database_path = sys_get_temp_dir() . '/exports-benchmark-' . getmypid() . '.sqlite';

        touch($this->database_path);

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        @unlink($this->database_path);
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.connections.sqlite.database', $this->database_path);
    }

    #[Test]
    public function it_exports_a_million_rows_with_flat_memory(): void
    {
        $rows = (int) (getenv('EXPORTS_BENCHMARK_ROWS') ?: 1_000_000);

        $this->artisan('migrate')->run();
        $this->seedUsers($rows, 2000);

        foreach ([Formats::CSV, Formats::XLSX] as $format) {
            $path = TemporaryFile::path($format);

            gc_collect_cycles();
            $before = memory_get_usage();
            memory_reset_peak_usage();
            $started = microtime(true);

            $count = UsersExport::make()->writeTo($path);

            $peak = memory_get_peak_usage() - $before;
            $seconds = microtime(true) - $started;
            $size = filesize($path);

            TemporaryFile::delete($path);

            fwrite(STDERR, sprintf(
                "\n%s: %s rows in %.1fs, peak memory +%.1f MB, file %.1f MB",
                $format->value,
                number_format($count),
                $seconds,
                $peak / 1024 / 1024,
                $size / 1024 / 1024,
            ));

            $this->assertEquals($rows, $count);
            $this->assertLessThan(32 * 1024 * 1024, $peak);
        }
    }
}
