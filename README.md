<div align="center">
    <h1>Bot Shield</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/marshmallow/bot-shield"><img src="https://img.shields.io/packagist/v/marshmallow/bot-shield.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/marshmallow/bot-shield"><img src="https://img.shields.io/packagist/php-v/marshmallow/bot-shield.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://packagist.org/packages/marshmallow/bot-shield"><img src="https://badge.laravel.cloud/badge/marshmallow/bot-shield?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/marshmallow/bot-shield/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/marshmallow/bot-shield/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/marshmallow/bot-shield"><img src="https://img.shields.io/packagist/dt/marshmallow/bot-shield.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Bot traffic hardening and spam protection for Laravel + Livewire: exception noise suppression, honeypot, reCAPTCHA, crawler rules and monitoring.

## Installation

You can install the package via Composer:

```bash
composer require marshmallow/bot-shield
```

You may publish all of the package's resources at once:

```bash
php artisan vendor:publish --tag="bot-shield"
```

Or, you may publish each resource individually:

### Publishing the Configuration File

```bash
php artisan vendor:publish --tag="bot-shield-config"
```

### Publishing and Running the Migrations

```bash
php artisan vendor:publish --tag="bot-shield-migrations"
php artisan migrate
```

### Publishing the Views

```bash
php artisan vendor:publish --tag="bot-shield-views"
```

### Publishing the Translations

```bash
php artisan vendor:publish --tag="bot-shield-lang"
```

## Usage

<!-- Add a basic usage example here. -->

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Bot Shield! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Credits

- [LTKort](https://github.com/marshmallow)
- [All Contributors](../../contributors)

## License

Bot Shield is open-sourced software licensed under the [MIT license](LICENSE.md).
