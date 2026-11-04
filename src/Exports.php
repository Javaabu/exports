<?php

namespace Javaabu\Exports;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Javaabu\Exports\Contracts\Exportable;
use Javaabu\Exports\Models\Export;

/**
 * Package-wide settings and the registry of exports that the exports API can start by name
 */
class Exports
{
    /** Whether the package loads its migrations. Turn off with ignoreMigrations(). */
    public static bool $runs_migrations = true;

    /** @var array<string, class-string<Exportable>> */
    protected static array $registered = [];

    /**
     * Let the exports API and UI start an export by name
     *
     * ```php
     * Exports::register('users', UsersExport::class);
     * ```
     *
     * @param  string|array<string, class-string<Exportable>>  $type
     * @param  class-string<Exportable>|null  $export_class
     */
    public static function register(string|array $type, ?string $export_class = null): void
    {
        if (is_array($type)) {
            foreach ($type as $name => $class) {
                static::register($name, $class);
            }

            return;
        }

        if (! $export_class || ! is_a($export_class, Exportable::class, true)) {
            throw new InvalidArgumentException("The export for [{$type}] must implement " . Exportable::class . '.');
        }

        static::$registered[$type] = $export_class;
    }

    /**
     * @return array<string, class-string<Exportable>>
     */
    public static function registered(): array
    {
        return static::$registered;
    }

    /**
     * @return class-string<Exportable>|null
     */
    public static function exportClass(string $type): ?string
    {
        return static::$registered[$type] ?? null;
    }

    /**
     * The registered name of an export class, if it has one
     */
    public static function typeOf(Exportable|string $export): ?string
    {
        $class = is_string($export) ? $export : $export::class;

        $type = array_search($class, static::$registered, true);

        return $type === false ? null : $type;
    }

    public static function forget(?string $type = null): void
    {
        if ($type === null) {
            static::$registered = [];

            return;
        }

        unset(static::$registered[$type]);
    }

    /**
     * The Export model class, from `config('exports.models.export')`
     *
     * @return class-string<Export>
     */
    public static function exportModel(): string
    {
        return config('exports.models.export', Export::class);
    }

    public static function newExportModel(): Export
    {
        $class = static::exportModel();

        return new $class;
    }

    /**
     * Don't load the package's migrations; publish them with `--tag=exports-migrations` instead
     */
    public static function ignoreMigrations(): void
    {
        static::$runs_migrations = false;
    }

    /**
     * The morph type and key of a user, for querying exports by owner
     *
     * @return array{0: string, 1: mixed}
     */
    public static function ownerKey(Model $user): array
    {
        return [$user->getMorphClass(), $user->getKey()];
    }
}
