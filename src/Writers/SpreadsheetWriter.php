<?php

namespace Javaabu\Exports\Writers;

use BackedEnum;
use DateTimeInterface;
use Javaabu\Exports\Enums\Formats;
use JsonSerializable;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\BooleanCell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\AbstractWriterMultiSheets;
use OpenSpout\Writer\CSV\Options as CsvOptions;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\ODS\Options as OdsOptions;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Stringable;
use UnitEnum;

/**
 * Streams a heading row and data rows to a CSV, XLSX or ODS file with OpenSpout
 *
 * Rows are written one at a time and never kept, so memory stays flat. Strings are always
 * written as text, never as formulas; in CSV files, values that a spreadsheet would run as a
 * formula are prefixed with an apostrophe (see `exports.csv.escape_formulas`).
 */
class SpreadsheetWriter
{
    /** Characters a spreadsheet treats as the start of a formula */
    protected const array FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /** @var array<int, int> column index => widest value, for auto-sizing */
    protected array $widths = [];

    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, mixed>>  $rows
     * @return int the number of data rows written
     */
    public function write(string $path, Formats $format, array $headings, iterable $rows): int
    {
        $this->widths = [];

        $writer = $this->makeWriter($format);
        $writer->openToFile($path);

        $count = 0;

        try {
            if ($format === Formats::XLSX && $this->isRtl() && $writer instanceof XlsxWriter) {
                $writer->getCurrentSheet()->setSheetView($this->sheetView($headings !== []));
            }

            if ($headings !== []) {
                $writer->addRow(new Row($this->cells($headings, $format, $this->headingStyle())));
            }

            foreach ($rows as $row) {
                $writer->addRow(new Row($this->cells(array_values($row), $format)));
                $count++;
            }

            $this->applyColumnWidths($writer, $format);
        } finally {
            $writer->close();
        }

        return $count;
    }

    protected function makeWriter(Formats $format): WriterInterface
    {
        $temporary_directory = $this->temporaryDirectory();

        return match ($format) {
            Formats::CSV => new CsvWriter(new CsvOptions(
                FIELD_DELIMITER: (string) config('exports.csv.delimiter', ','),
                FIELD_ENCLOSURE: (string) config('exports.csv.enclosure', '"'),
                SHOULD_ADD_BOM: (bool) config('exports.csv.bom', true),
            )),
            Formats::XLSX => new XlsxWriter(new XlsxOptions(tempFolder: $temporary_directory)),
            Formats::ODS => new OdsWriter(new OdsOptions(tempFolder: $temporary_directory)),
        };
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, Cell>
     */
    protected function cells(array $values, Formats $format, ?Style $style = null): array
    {
        $cells = [];

        foreach ($values as $index => $value) {
            $cell = $this->cell($value, $format, $style);

            $this->trackWidth($index, $cell);

            $cells[] = $cell;
        }

        return $cells;
    }

    protected function cell(mixed $value, Formats $format, ?Style $style = null): Cell
    {
        if ($value === null || $value === '') {
            return new EmptyCell(null, $style);
        }

        if (is_bool($value)) {
            return new BooleanCell($value, $style);
        }

        if (is_int($value) || is_float($value)) {
            return new NumericCell($value, $style);
        }

        return new StringCell($this->escape($this->stringify($value), $format), $style);
    }

    protected function stringify(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            $value instanceof DateTimeInterface => $value->format((string) config('exports.datetime_format', 'Y-m-d H:i:s')),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            $value instanceof Stringable => (string) $value,
            $value instanceof JsonSerializable, is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            is_object($value) => $value::class,
            default => (string) $value,
        };
    }

    /**
     * Stop a CSV value from being run as a formula when the file is opened in a spreadsheet
     */
    protected function escape(string $value, Formats $format): string
    {
        if ($format !== Formats::CSV) {
            return $value;
        }

        if (! config('exports.csv.escape_formulas', true)) {
            return $value;
        }

        if (! in_array($value[0], self::FORMULA_TRIGGERS, true)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value;
        }

        return "'{$value}";
    }

    protected function headingStyle(): Style
    {
        return (new Style)->withFontBold(true);
    }

    protected function sheetView(bool $has_headings): SheetView
    {
        $view = (new SheetView)->withRightToLeft(true);

        return $has_headings ? $view->withFreezeRow(2) : $view;
    }

    protected function trackWidth(int $index, Cell $cell): void
    {
        $value = $cell->getValue();

        $length = is_string($value) ? mb_strlen($value) : strlen((string) $value);

        if ($length > ($this->widths[$index] ?? 0)) {
            $this->widths[$index] = $length;
        }
    }

    /**
     * Size XLSX columns to fit their widest value, up to `exports.xlsx.max_column_width`
     */
    protected function applyColumnWidths(WriterInterface $writer, Formats $format): void
    {
        if ($format !== Formats::XLSX) {
            return;
        }

        if (! config('exports.xlsx.auto_size', true)) {
            return;
        }

        if (! $writer instanceof AbstractWriterMultiSheets) {
            return;
        }

        $max = (float) config('exports.xlsx.max_column_width', 60);
        $sheet = $writer->getCurrentSheet();

        foreach ($this->widths as $index => $length) {
            $sheet->setColumnWidth(min(max($length + 2, 8), $max), $index + 1);
        }
    }

    protected function isRtl(): bool
    {
        $rtl = config('exports.rtl', 'auto');

        if ($rtl !== 'auto') {
            return (bool) $rtl;
        }

        return in_array(app()->getLocale(), config('exports.rtl_locales', ['dv', 'ar', 'fa', 'he', 'ur']), true);
    }

    protected function temporaryDirectory(): string
    {
        $directory = config('exports.temporary_directory') ?: sys_get_temp_dir();

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return $directory;
    }
}
