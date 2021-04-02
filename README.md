# Laravel Qmonitor Collector

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qmonitor/laravel-qmonitor-collector.svg?style=flat-square)](https://packagist.org/packages/qmonitor/laravel-qmonitor-collector)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/qmonitor/laravel-qmonitor-collector/run-tests?label=tests)](https://github.com/qmonitor/laravel-qmonitor-collector/actions?query=workflow%3ATests+branch%3Amaster)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/qmonitor/laravel-qmonitor-collector/Check%20&%20fix%20styling?label=code%20style)](https://github.com/qmonitor/laravel-qmonitor-collector/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amaster)
[![Total Downloads](https://img.shields.io/packagist/dt/qmonitor/laravel-qmonitor-collector.svg?style=flat-square)](https://packagist.org/packages/qmonitor/laravel-qmonitor-collector)


Collect and send data to qmonitor.io

## Installation

You can install the package via composer:

```bash
composer require qmonitor/laravel-qmonitor-collector
```

You can publish the config file with:
```bash
php artisan vendor:publish --provider="\Qmonitor\QmonitorServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$laravel-qmonitor-collector = new \Qmonitor();
echo $laravel-qmonitor-collector->echoPhrase('Hello, !');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Lucian Brodoceanu](https://github.com/brodos)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
