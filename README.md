<p align="center"><a href="https://pharaonic.dev/packages/laravel/translatable" target="_blank"><img src="https://raw.githubusercontent.com/Pharaonic/laravel-translatable/develop/docs/cover.webp"></a></p>

<p align="center">
  <a href="https://php.net" target="_blank"><img src="https://img.shields.io/static/v1?label=PHP&message=8.2%20|%208.3%20|%208.4%20|%208.5&color=blue&style=flat-square" alt="PHP Version : 8.2 | 8.3 | 8.4 | 8.5"></a>
  <a href="https://laravel.com" target="_blank"><img src="https://img.shields.io/static/v1?label=Laravel&message=12.x&color=F05340&style=flat-square" alt="Laravel Version : 12.x"></a>
  <img src="https://img.shields.io/static/v1?label=License&message=MIT&color=brightgreen&style=flat-square" alt="License">
  <img src="https://github.com/Pharaonic/laravel-translatable/actions/workflows/build.yml/badge.svg?branch=12.x" alt="Build">
  <br>
  <a href="https://packagist.org/packages/Pharaonic/laravel-translatable" target="_blank"><img src="https://img.shields.io/static/v1?label=Packagist&message=pharaonic/laravel-translatable&color=blue&logo=packagist&logoColor=white" alt="Source"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-translatable" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-translatable/v" alt="Packagist Version"></a>
  <a href="https://packagist.org/packages/pharaonic/laravel-translatable" target="_blank"><img src="https://poser.pugx.org/pharaonic/laravel-translatable/downloads" alt="Packagist Downloads"></a>
  <a href="https://pharaonic.dev/packages/laravel/translatable#ai-ready" target="_blank"><img src="https://img.shields.io/static/v1?label=AI%20Ready&message=Laravel%20Boost&color=8B5CF6&style=flat-square" alt="AI Ready: Laravel Boost"></a>
</p>

<h3 align="center">Translated Eloquent fields in three storage strategies, a translations table, a JSON column per field or a column per locale, behind one API.</h3>
<br>

## Installation

```bash
composer require pharaonic/laravel-translatable
```

The service provider is registered automatically. Publish the configuration when you need to change it:

```bash
php artisan vendor:publish --tag=translatable-config
```

## Usage

```php
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Translatable;

class Post extends Model implements JsonTranslatable   // or TableTranslatable, ColumnsTranslatable, or none for the default
{
    use Translatable;

    protected $translatables = ['title', 'body'];
}

$post = new Post;
$post->setTranslation('title', 'en', 'Hello')->setTranslation('title', 'ar', 'مرحبا')->save();

$post->title;                          // the current locale, with the fallback locale when missing
$post->getTranslation('title', 'ar');  // exactly what is stored for Arabic, or null
Post::whereTranslation('title', 'Hello', 'en')->get();
```

## Documentation

Full documentation: [Laravel Translatable Documentation](https://pharaonic.dev/packages/laravel/translatable).

## Support & Security

See [SUPPORT.md](SUPPORT.md) and [SECURITY.md](SECURITY.md). Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
