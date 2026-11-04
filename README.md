# Exports

[![Tests](https://github.com/Javaabu/exports/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/Javaabu/exports/actions/workflows/tests.yml?query=branch%3Amain)
[![codecov](https://codecov.io/gh/Javaabu/exports/branch/main/graph/badge.svg)](https://codecov.io/gh/Javaabu/exports/tree/main)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/exports.svg)](https://packagist.org/packages/javaabu/exports)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Streaming model exports for Laravel. Write CSV, XLSX and ODS files with flat memory, however many rows there are: a million rows take about 4 MB.

- Smart defaults: no hidden attributes or secrets, foreign keys replaced by related names, readable and translated headings, enum labels, dates in the app timezone.
- Download, store on any disk, or queue with progress, cancellation, notifications and signed, expiring links.
- An exports page and JSON API, in Blade or Inertia React, with live progress over Laravel Reverb (or polling).
- A policy and permissions through javaabu/permissions, for any guard and user model.
- Dhivehi translations and right-to-left sheets and pages.

```php
use Javaabu\Exports\ModelExport;

class UsersExport extends ModelExport
{
    public function modelClass(): string
    {
        return User::class;
    }
}

return UsersExport::make()->download('users.xlsx');

// big exports go on the queue; the user is notified when the file is ready
UsersExport::make(User::query()->where('is_active', true))->queue($request->user());
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/exports](https://docs.javaabu.com/exports), and in the [`docs`](docs) folder. Upgrading from v1? Read [`docs/upgrading.md`](docs/upgrading.md).

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving this package? Feel free to create an [issue](../../issues) on GitHub, we'll try to address it as soon as possible.

If you've found a bug regarding security please mail [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Testing

```bash
composer test
composer lint
composer benchmark   # 1M rows
pnpm install && pnpm check   # the React pages
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Credits

- [Javaabu Pvt. Ltd.](https://github.com/javaabu)
- [Arushad Ahmed (@dash8x)](http://arushad.com)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
