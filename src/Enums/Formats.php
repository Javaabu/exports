<?php

namespace Javaabu\Exports\Enums;

use ValueError;

/**
 * The file formats an export can be written in
 */
enum Formats: string
{
    case CSV = 'csv';
    case XLSX = 'xlsx';
    case ODS = 'ods';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::CSV => 'text/csv',
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::ODS => 'application/vnd.oasis.opendocument.spreadsheet',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CSV => __('CSV'),
            self::XLSX => __('Excel (XLSX)'),
            self::ODS => __('OpenDocument (ODS)'),
        };
    }

    /**
     * The format for a value, a file name with an extension, or the configured default
     */
    public static function resolve(self|string|null $format = null, ?string $filename = null): self
    {
        if ($format instanceof self) {
            return $format;
        }

        if ($format) {
            return self::tryFrom(strtolower($format))
                ?? throw new ValueError(__('Unsupported export format: :format', ['format' => $format]));
        }

        $extension = $filename ? strtolower(pathinfo($filename, PATHINFO_EXTENSION)) : '';

        if ($extension && ($from_extension = self::tryFrom($extension))) {
            return $from_extension;
        }

        return self::tryFrom((string) config('exports.format', self::XLSX->value)) ?? self::XLSX;
    }

    /**
     * The formats enabled in `config('exports.formats')`
     *
     * @return array<int, self>
     */
    public static function enabled(): array
    {
        $enabled = config('exports.formats', [self::XLSX->value, self::CSV->value, self::ODS->value]);

        return array_values(array_filter(
            self::cases(),
            fn (self $format) => in_array($format->value, $enabled, true),
        ));
    }
}
