## Slugs & SEO

### Translated Slugs

`HasTranslatedSlugs` gives each locale its own slug, made from that locale's value, under every strategy. Add the slug as a translated field and map it to its source field.

```php title="app/Post.php"
use Pharaonic\Laravel\Translatable\Integrations\Sluggable\HasTranslatedSlugs;
use Pharaonic\Laravel\Translatable\Translatable;

class Post extends Model
{
    use HasTranslatedSlugs;
    use Translatable;

    protected $translatables = ['title', 'slug'];

    public function translatedSlugs(): array
    {
        return ['slug' => 'title'];   // target field => source field
    }
}
```

```php
$post->setTranslation('title', 'en', 'Hello World')->save();

$post->getLocalizedSlug('en');                       // "hello-world"
$post->getLocalizedSlug('ar');                       // null: no Arabic title
Post::findByTranslatedSlug('hello-world', 'en');     // the post
```

- A slug is generated when the source value of its locale changes, unless you set that locale's slug yourself.
- A locale without its own source value gets no slug; a fallback value never makes one.
- Slugs are unique per locale: a second "Hello World" in English becomes `hello-world-2`.
- The slugifier is `pharaonic/php-slugify` when it is installed (it comes with `pharaonic/laravel-sluggable`), otherwise Laravel's `Str::slug()`. Override `makeTranslatedSlug(string $value, string $locale)` to use your own.

### SEO

`TranslatableSeo` fills [pharaonic/laravel-seo](https://github.com/Pharaonic/laravel-seo) from a translated model. Install that package first.

```php title="app/Http/Controllers/PostController.php"
use Pharaonic\Laravel\Translatable\Integrations\Seo\TranslatableSeo;

public function show(Post $post, TranslatableSeo $seo)
{
    $seo->apply($post, function (Post $post, string $locale) {
        return url("/{$locale}/posts/".$post->getLocalizedSlug($locale));
    });

    return view('posts.show', compact('post'));
}
```

- The title and description come from the locale's exact `title` and `description` values, never from a fallback. Pass a fourth argument to map other fields: `['title' => 'headline', 'description' => 'summary']`.
- An hreflang alternate is added for each complete locale (every required field has a value) for which your closure returns a URL. Return `null` for a locale with no page.
- The canonical URL is set only when the current locale is one of them.
