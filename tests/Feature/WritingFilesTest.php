<?php

namespace Javaabu\Exports\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Javaabu\Exports\Enums\Formats;
use Javaabu\Exports\IterableExport;
use Javaabu\Exports\Support\TemporaryFile;
use Javaabu\Exports\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ValueError;

class WritingFilesTest extends TestCase
{
    /**
     * @return array<string, array{0: Formats}>
     */
    public static function formats(): array
    {
        return [
            'csv' => [Formats::CSV],
            'xlsx' => [Formats::XLSX],
            'ods' => [Formats::ODS],
        ];
    }

    #[Test]
    #[DataProvider('formats')]
    public function it_writes_headings_and_rows_in_every_format(Formats $format): void
    {
        $path = TemporaryFile::path($format);

        $count = $this->islandsExport()->writeTo($path);

        $this->assertEquals(2, $count);
        $this->assertEquals([
            ['Name', 'Atoll', 'Population'],
            ['މާލެ', 'ކ', 252768],
            ['ހިތަދޫ', 'ސ', 13000],
        ], $this->normalise($this->readSpreadsheet($path)));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_prefixes_csv_values_that_would_run_as_formulas(): void
    {
        $path = TemporaryFile::path(Formats::CSV);

        IterableExport::make(['Value'], [['=1+2'], ['@SUM(A1)'], ['-5'], ['+960 7777777'], ['ok']])->writeTo($path);

        $this->assertEquals([
            ['Value'],
            ["'=1+2"],
            ["'@SUM(A1)"],
            ['-5'],
            ["'+960 7777777"],
            ['ok'],
        ], $this->readSpreadsheet($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_can_be_told_not_to_escape_csv_formulas(): void
    {
        config()->set('exports.csv.escape_formulas', false);

        $path = TemporaryFile::path(Formats::CSV);

        IterableExport::make(['Value'], [['=1+2']])->writeTo($path);

        $this->assertEquals([['Value'], ['=1+2']], $this->readSpreadsheet($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_never_writes_xlsx_strings_as_formulas(): void
    {
        $path = TemporaryFile::path(Formats::XLSX);

        IterableExport::make(['Value'], [['=1+2']])->writeTo($path);

        $sheet = $this->readFromArchive($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringNotContainsString('<f>', $sheet);
        $this->assertEquals([['Value'], ['=1+2']], $this->readSpreadsheet($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_adds_a_bom_to_csv_files_so_excel_reads_thaana(): void
    {
        $path = TemporaryFile::path(Formats::CSV);

        IterableExport::make(['ނަން'], [['އަހުމަދު']])->writeTo($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", file_get_contents($path));

        config()->set('exports.csv.bom', false);

        IterableExport::make(['ނަން'], [['އަހުމަދު']])->writeTo($path);

        $this->assertStringStartsWith('ނަން', file_get_contents($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_uses_the_csv_delimiter_from_the_config(): void
    {
        config()->set('exports.csv.delimiter', ';');

        $path = TemporaryFile::path(Formats::CSV);

        IterableExport::make(['A', 'B'], [[1, 2]])->writeTo($path);

        $this->assertStringContainsString("A;B\n1;2", file_get_contents($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_sizes_xlsx_columns_to_their_widest_value(): void
    {
        $path = TemporaryFile::path(Formats::XLSX);

        IterableExport::make(['Name', 'Note'], [['އަހުމަދު', str_repeat('x', 100)]])->writeTo($path);

        $sheet = $this->readFromArchive($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('<col min="1" max="1" width="10"', $sheet);
        $this->assertStringContainsString('<col min="2" max="2" width="60"', $sheet);

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_writes_right_to_left_sheets_in_dhivehi(): void
    {
        $path = TemporaryFile::path(Formats::XLSX);

        app()->setLocale('dv');

        $this->islandsExport()->writeTo($path);

        $sheet = $this->readFromArchive($path, 'xl/worksheets/sheet1.xml');

        $this->assertStringContainsString('rightToLeft="true"', $sheet);

        app()->setLocale('en');

        $this->islandsExport()->writeTo($path);

        $this->assertStringNotContainsString('rightToLeft="true"', $this->readFromArchive($path, 'xl/worksheets/sheet1.xml'));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_formats_dates_enums_and_objects_as_text(): void
    {
        $path = TemporaryFile::path(Formats::CSV);

        IterableExport::make(['Value'], [
            [now()->setDate(2026, 11, 4)->setTime(9, 30)],
            [Formats::CSV],
            [['island' => 'މާލެ']],
            [true],
        ])->writeTo($path);

        $this->assertEquals([
            ['Value'],
            ['2026-11-04 09:30:00'],
            ['csv'],
            ['{"island":"މާލެ"}'],
            ['1'],
        ], $this->readSpreadsheet($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_writes_null_as_an_empty_cell(): void
    {
        $path = TemporaryFile::path(Formats::XLSX);

        IterableExport::make(['A', 'B', 'C'], [['x', null, '']])->writeTo($path);

        $this->assertEquals([['A', 'B', 'C'], ['x', '', '']], $this->readSpreadsheet($path));

        TemporaryFile::delete($path);
    }

    #[Test]
    public function it_downloads_with_a_file_name_and_content_type(): void
    {
        $response = $this->islandsExport()->download('islands.csv');

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename=islands.csv', $response->headers->get('Content-Disposition'));
        $this->assertEquals([
            ['Name', 'Atoll', 'Population'],
            ['މާލެ', 'ކ', '252768'],
            ['ހިތަދޫ', 'ސ', '13000'],
        ], $this->readSpreadsheet($response->getFile()->getPathname(), 'csv'));
    }

    #[Test]
    public function it_adds_the_extension_and_a_timestamp_to_download_names(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(9, 30, 15));

        $export = $this->islandsExport();

        $this->assertEquals('islands-2026-11-04-093015.xlsx', $export->downloadName(null, Formats::XLSX));
        $this->assertEquals('atolls.ods', $export->downloadName('atolls', Formats::ODS));
        $this->assertEquals('atolls.csv', $export->downloadName('atolls.csv', Formats::CSV));
    }

    #[Test]
    public function it_stores_exports_on_a_disk(): void
    {
        Storage::fake('exports');

        config()->set('exports.disk', 'exports');

        $path = $this->islandsExport()->store('reports/islands.csv');

        $this->assertEquals('reports/islands.csv', $path);
        Storage::disk('exports')->assertExists('reports/islands.csv');
        $this->assertStringContainsString('ހިތަދޫ', Storage::disk('exports')->get('reports/islands.csv'));
    }

    #[Test]
    public function it_stores_exports_in_the_configured_directory_by_default(): void
    {
        Storage::fake('local');

        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(9, 30, 15));

        $path = $this->islandsExport()->store(disk: 'local', format: 'ods');

        $this->assertEquals('exports/islands-2026-11-04-093015.ods', $path);
        Storage::disk('local')->assertExists($path);
    }

    #[Test]
    public function it_resolves_formats(): void
    {
        $this->assertEquals(Formats::CSV, Formats::resolve('CSV'));
        $this->assertEquals(Formats::ODS, Formats::resolve(null, 'report.ods'));
        $this->assertEquals(Formats::XLSX, Formats::resolve(null, 'report'));
        $this->assertEquals(Formats::CSV, Formats::resolve(Formats::CSV, 'report.ods'));

        config()->set('exports.format', 'csv');

        $this->assertEquals(Formats::CSV, Formats::resolve());

        $this->expectException(ValueError::class);

        Formats::resolve('pdf');
    }

    #[Test]
    public function it_lists_the_enabled_formats(): void
    {
        config()->set('exports.formats', ['csv']);

        $this->assertEquals([Formats::CSV], Formats::enabled());
        $this->assertEquals('text/csv', Formats::CSV->mimeType());
        $this->assertEquals('Excel (XLSX)', Formats::XLSX->label());
    }

    #[Test]
    public function it_counts_iterable_rows_when_it_can(): void
    {
        $this->assertEquals(2, $this->islandsExport()->totalRows());
        $this->assertNull(IterableExport::make(['A'], fn () => yield [1])->totalRows());
        $this->assertEquals(5, IterableExport::make(['A'], fn () => yield [1], total_rows: 5)->totalRows());
    }

    protected function islandsExport(): IterableExport
    {
        return IterableExport::make(['Name', 'Atoll', 'Population'], [
            ['މާލެ', 'ކ', 252768],
            ['name' => 'ހިތަދޫ', 'atoll' => 'ސ', 'population' => 13000],
        ], 'islands');
    }

    /**
     * Numbers read back from a CSV are strings
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    protected function normalise(array $rows): array
    {
        return array_map(fn (array $row) => array_map(
            fn (mixed $value) => is_string($value) && ctype_digit($value) ? (int) $value : $value,
            $row,
        ), $rows);
    }
}
