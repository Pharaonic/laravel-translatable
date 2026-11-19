## Installation

Install the package with Composer. Laravel discovers the service provider automatically.

### Requirements

- PHP 8.0
- Laravel 7.30 or newer within 7.x
- A database supported by Laravel: SQLite, MySQL, MariaDB or PostgreSQL

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require pharaonic/laravel-translatable
```

### Publish Configuration

Publishing the config is optional. Without it, every model uses the table strategy unless it says otherwise.

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=translatable-config
```

This creates `config/translatable.php`.

### Publish Stubs

Publish the stubs only if you want to change the files `make:translatable` generates.

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=translatable-stubs
```

The stubs are copied to `stubs/translatable`, and the published copies are used instead of the package's.

:::info Publish Tags
`--tag=laravel-translatable` (or `--tag=pharaonic`) publishes the config. The stubs are only published with `--tag=translatable-stubs`.
:::

:::success Installation Complete
You're all set! Run `php artisan make:translatable Post -m` to create a translatable model with its migrations.
:::
