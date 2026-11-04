<?php

namespace Javaabu\Exports\Tests;

use Javaabu\Exports\ExportsServiceProvider;
use Javaabu\Exports\Tests\TestSupport\Providers\TestServiceProvider;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Orchestra\Testbench\TestCase as BaseTestCase;
use ZipArchive;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ExportsServiceProvider::class,
            TestServiceProvider::class,
        ];
    }

    /**
     * Read every row of a CSV, XLSX or ODS file
     *
     * @return array<int, array<int, mixed>>
     */
    protected function readSpreadsheet(string $path, ?string $extension = null): array
    {
        $reader = $this->makeReader($extension ?? pathinfo($path, PATHINFO_EXTENSION));
        $reader->open($path);

        $rows = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }

            break;
        }

        $reader->close();

        return $rows;
    }

    protected function makeReader(string $extension): ReaderInterface
    {
        return match ($extension) {
            'csv' => new CsvReader,
            'ods' => new OdsReader,
            default => new XlsxReader,
        };
    }

    /**
     * Read a file inside an XLSX or ODS archive, e.g. `xl/worksheets/sheet1.xml`
     */
    protected function readFromArchive(string $path, string $file): string
    {
        $zip = new ZipArchive;
        $zip->open($path);

        $contents = (string) $zip->getFromName($file);

        $zip->close();

        return $contents;
    }
}
