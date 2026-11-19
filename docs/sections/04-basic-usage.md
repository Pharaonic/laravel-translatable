## Basic Usage

Add the `Translatable` trait to a model and list its translated fields in `$translatables`.

```php title="app/Post.php"
namespace App;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Translatable;

class Post extends Model
{
    use Translatable;

    protected $fillable = ['published'];

    protected $translatables = ['title', 'content'];
}
```

With the default table strategy, the translations live in a `PostTranslation` model and a `post_translations` table. `php artisan make:translatable Post -m` creates the model, the translation model and both migrations for you (see [Generators](#generators)).

### Writing Translations

Set one value, several locales of a field, or several fields of several locales, then save the model.

```php
$post = new Post(['published' => true]);

$post->setTranslation('title', 'en', 'Hello')
    ->setTranslation('title', 'ar', 'مرحبا')
    ->save();

$post->setTranslations('content', ['en' => 'Body', 'ar' => 'المحتوى'])->save();

$post->fillTranslations([
    'en' => ['title' => 'Hello', 'content' => 'Body'],
    'ar' => ['title' => 'مرحبا', 'content' => 'المحتوى'],
])->save();
```

Setting a translated attribute writes the current locale:

```php
App::setLocale('ar');

$post->title = 'مرحبا';
$post->save();
```

### Reading Translations

`$post->title` is the value of the current locale, or of the fallback locale when it has none. `getTranslation()` returns exactly what is stored.

```php
App::setLocale('fr');

$post->title;                                  // "Hello" (from the fallback locale, en)
$post->getTranslation('title', 'ar');          // "مرحبا"
$post->getTranslation('title', 'fr');          // null
$post->getTranslation('title', 'fr', true);    // "Hello"
$post->getTranslations('title');               // ['ar' => 'مرحبا', 'en' => 'Hello']
```

### Querying

Scopes filter and sort by translated values, under every strategy.

```php
Post::whereTranslation('title', 'Hello', 'en')->get();
Post::whereTranslationLike('title', '%ello%')->get();
Post::translated('ar')->orderByTranslation('title', 'asc', 'ar')->get();
```

:::tip Mass Assignment
`$fillable` is never changed for you. Add `title` to make the current locale's value fillable, or `locale` to accept a `['locale' => ['en' => ['title' => '…']]]` payload.
:::
