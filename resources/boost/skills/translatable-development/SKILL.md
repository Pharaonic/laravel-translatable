---
name: translatable-development
description: Build multilingual Eloquent models with pharaonic/laravel-translatable, covering the table, json and columns strategies, reading and writing translations, fallbacks, translation scopes, saving, events, generators and moving data between strategies. Use when adding translated fields, querying or sorting by them, or changing how a model stores its translations.
---

# Laravel Translatable Development

## When to use this skill

Use this skill when a model has fields that differ per language: declaring them, reading and writing them, querying or sorting by them, generating translatable models and migrations, or moving a model to another storage strategy.

## Setup

- `composer require pharaonic/laravel-translatable`. The provider is auto-discovered.
- Optional config: `php artisan vendor:publish --tag=translatable-config` creates `config/translatable.php` with one key, `strategy` (`table`, `json` or `columns`, from `TRANSLATABLE_STRATEGY`, default `table`).
- Locales come from Laravel: the current locale is `App::getLocale()`, the fallback is `config('app.fallback_locale')`.

## Choosing a strategy

| Strategy | Marker | Storage | Use when |
| --- | --- | --- | --- |
| `table` | `TableTranslatable` | `PostTranslation` model, `post_translations` table, `post_id` + `locale` columns | many locales, long text, apps upgrading from 2.x |
| `json` | `JsonTranslatable` | one JSON column per field: `{"en": "…", "ar": "…"}` | few fields, open-ended locales, no extra table |
| `columns` | `ColumnsTranslatable` | `title_en`, `title_ar` columns (locale lowercased, `-` as `_`) | small fixed locale set, plain indexes |

A json model casts each translated JSON column to `array` (`protected $casts = ['title' => 'array'];`); the package reads and writes those columns raw, so the casts never change what it stores, and value types still come from `translatable()`.

A model implements at most one marker (`Pharaonic\Laravel\Translatable\Contracts\…`); without one it follows `config('translatable.strategy')`. Different models may use different strategies.

## Declaring fields

```php
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;
use Pharaonic\Laravel\Translatable\Translatable;

class Product extends Model implements JsonTranslatable
{
    use Translatable;

    // Untyped: protected $translatables = ['name', 'description'];

    public function translatable(): array
    {
        return [
            'name' => ['type' => 'string', 'required' => true],
            'description' => ['required' => true],
            'price' => ['type' => 'float'],
            'features' => ['type' => 'array'],
            'notes' => ['fallback' => false],
        ];
    }
}
```

- Types: `string`, `int`, `float`, `bool`, `array`, `object`, `date`, `datetime`, or a custom cast class. A value of the wrong type throws `InvalidTranslationValueException` (nothing is converted silently; `null` is allowed).
- `required` fields drive completeness. `'fallback' => false` never shows another locale's value.
- Not allowed: field names `translations`, `locales` or the key; a set mutator (`setTitleAttribute`) on a translated field; storage options such as `column`.

## Table strategy schema

```php
Schema::create('post_translations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->string('locale', 35);
    $table->string('title')->nullable();
    $table->text('content')->nullable();
    $table->unique(['post_id', 'locale']);
});
```

The `PostTranslation` model lives next to `Post`. Override names on the model with `getTranslatableModel()`, `getTranslatableTable()` and `getTranslatableField()`.

## Writing

```php
$post->setTranslation('title', 'ar', 'مرحبا');
$post->setTranslations('title', ['en' => 'Hello', 'ar' => 'مرحبا']);
$post->fillTranslations(['en' => ['title' => 'Hello', 'content' => 'Body']]);
$post->title = 'Hello';                       // the current locale
$post->forgetTranslation('title', 'ar');
$post->forgetTranslations('ar');              // every field of the locale
$post->save();                                // one transaction for the model and its translations

Post::create(['published' => true, 'locale' => ['en' => ['title' => 'Hello']]]); // when 'locale' is fillable
```

`save()` never deletes translations you did not forget. A failed save stores nothing and keeps the changes pending.

## Reading

```php
$post->title;                                  // displayed value: current locale, then fallback
$post->getTranslation('title', 'fr');          // exact value or null, never a fallback
$post->getTranslation('title', 'fr', true);    // displayed value
$post->resolveTranslation('title', 'fr');      // ResolvedTranslation: value, resolvedLocale, isFallback, isMissing
$post->getTranslationOrFail('title', 'fr');    // MissingTranslationException when missing
$post->getTranslations();                      // field => [locale => value]
$post->getTranslationsForLocale('ar');         // field => value
$post->getTranslatedLocales();                 // locales with at least one value
$post->hasTranslation('ar', 'title');
$post->hasCompleteTranslation('ar');           // every required field has a value
$post->missingTranslations('ar');              // required fields without a value
$post->translationProgress('ar');              // 0-100
```

`translate($locale)`, `translateOrNew($locale)`, `translateOrDefault($locale)` and `translateOrFail($locale)` return the locale's translation: the translation model under `table`, a `LocaleTranslation` under `json`/`columns`. Set fields on it and call the model's `save()`.

Dirty tracking: `getDirtyTranslations()`, `isTranslationDirty($field, $locale)`, `getTranslationChanges()`, `wasTranslationChanged($field, $locale)`.

## Querying

```php
Post::whereTranslation('title', 'Hello', 'en')->get();         // without a locale: any locale
Post::whereTranslation('rank', 3, 'en', 'whereHas', '>=')->get();
Post::orWhereTranslation('title', 'Hi', 'en');
Post::whereTranslationLike('title', '%news%', 'en')->get();
Post::whereTranslationIn('status', ['draft', 'review'], 'en')->get();
Post::whereTranslationNotIn('status', ['archived'], 'en')->get();
Post::translated('ar')->get();                                   // at least one field in Arabic
Post::translated()->get();                                       // the current locale
Post::notTranslated('ar')->get();
Post::whereHasTranslation('ar', 'content')->get();
Post::whereMissingTranslation('ar', 'content')->get();
Post::whereTranslationProgress('ar', '<', 100)->get();
Post::orderByTranslation('title', 'asc', 'ar', true)->get();     // missing values last; true sorts by the fallback value
Post::withTranslations(['en', 'ar'])->get();                     // eager load (table strategy)
```

Operators: `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like`, `not like`. Results are the same under every strategy on SQLite, MySQL, MariaDB and PostgreSQL. Index the `{field}_{locale}` columns you filter by under `columns`.

## Events

`TranslationSet` (model, field, locale, value, previous) and `TranslationRemoved` (model, field, locale, previous) fire once per stored change after the save's transaction commits; `TranslationsMigrated` after `translatable:migrate --apply`. All in `Pharaonic\Laravel\Translatable\Events`. Bind `Contracts\FallbackPolicy` for a custom fallback chain.

## Commands

```bash
php artisan make:translatable Post -m                          # default strategy; -m adds migrations
php artisan make:translatable Post --columns --locales=en,ar -m
php artisan translatable:inspect 'App\Models\Post'
php artisan translatable:make-schema 'App\Models\Post' --to=json   # review, then migrate
php artisan translatable:migrate 'App\Models\Post' --from=table --to=json          # dry run
php artisan translatable:migrate 'App\Models\Post' --from=table --to=json --apply
php artisan translatable:verify 'App\Models\Post' --from=table --to=json
php artisan translatable:rollback 'App\Models\Post' --migration-id=<id> --apply
```

Move a model to another strategy in this order: make-schema, migrate, verify, then deploy the new marker, adding the `array` casts when moving to json or removing them when moving away (both commands print the exact change). The source is never changed.

## Pitfalls

- Changing a marker or `translatable.strategy` without `translatable:migrate` leaves the model reading empty storage.
- Under `columns`, setting a locale that has no `{field}_{locale}` column throws `TranslationSchemaException`; add the column first. Another column named like `title_xx` is read as a locale.
- `getDirty()` and `isDirty()` never list translated fields.
- After adding a locale column, reload Octane and queue workers (`octane:reload`, `queue:restart`): column lists are cached per worker.
- In queued jobs set `App::setLocale()` before reading displayed values.

## Testing

Use your normal database tests. Set the locale with `App::setLocale()` and the fallback with `config(['app.fallback_locale' => 'en'])`, assert exact values with `getTranslation($field, $locale)`, and use `Event::fake([TranslationSet::class])` to assert what a save stored.
