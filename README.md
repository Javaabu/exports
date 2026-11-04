# Exports

[![Tests](https://github.com/n4ff4h/exports/actions/workflows/tests.yml/badge.svg?branch=next-gen)](https://github.com/n4ff4h/exports/actions/workflows/tests.yml?query=branch%3Anext-gen)
[![codecov](https://codecov.io/gh/n4ff4h/exports/branch/next-gen/graph/badge.svg)](https://codecov.io/gh/n4ff4h/exports/tree/next-gen)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/javaabu/exports.svg)](https://packagist.org/packages/javaabu/exports)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE.md)

Streaming model exports for Laravel. Write CSV, XLSX and ODS files with flat memory, however many rows there are.

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
```

## Documentation

You'll find the documentation on [https://docs.javaabu.com/exports](https://docs.javaabu.com/exports), and in the [`docs`](docs) folder.

Find yourself stuck using the package? Found a bug? Do you have general questions or suggestions for improving this package? Feel free to create an [issue](../../issues) on GitHub, we'll try to address it as soon as possible.

If you've found a bug regarding security please mail [info@javaabu.com](mailto:info@javaabu.com) instead of using the issue tracker.

## Testing

```bash
composer test
composer lint
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
