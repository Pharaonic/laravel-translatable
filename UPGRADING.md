# Upgrading

## From 2.x to 6.0 and later

From 6.0 the package has one release line per Laravel major (6.x for Laravel 6, … 13.x for Laravel 13), all with the same code. The table strategy is the default and keeps the 2.x storage, so an existing database needs no migration. Take these steps, then run your tests.

### Requirements

- PHP and Laravel follow the line you install: 6.x needs PHP 8.0 and Laravel 6.20+, 13.x needs PHP 8.3+ and Laravel 13.
- The package requires the `illuminate/*` components it uses instead of `laravel/framework`.

### Configuration

- The configuration is now `config/translatable.php` with one key, `strategy` (`php artisan vendor:publish --tag=translatable-config`). The old `config/Pharaonic/translatable.php` is not published, read or deleted.
- Locales come from Laravel: `app.locale` is the current locale and `app.fallback_locale` the fallback. 2.x fell back to `config('Pharaonic.translatable.default')` or `en`; set `app.fallback_locale` to that locale.
- Without `TRANSLATABLE_STRATEGY` or `translatable.strategy`, every model keeps the table strategy.

### Translated fields

Rename `$translatableAttributes` to `$translatables`:

```php
protected $translatables = ['title', 'content'];
```

`$translatableAttributes` is deprecated. It is still read when the model has no `$translatables`, and will be removed in a later major.

### Mass assignment

2.x added `locale` (or `$translationsKey`) to `$fillable` behind your back. 6.0 does not change `$fillable`:

```php
protected $fillable = ['published', 'locale'];   // the translations payload key
protected $fillable = ['published', 'title'];    // or the translated fields, for the current locale
```

The payload only accepts the translated fields. 2.x copied any key into the translation record, including its foreign key; 6.0 throws `UnknownTranslatableFieldException`.

### Reading and writing translated fields

- `$post->title` is the value of the current locale, with the fallback locale when it has none. It reads Laravel's locale on every access; 2.x kept the locale of the moment the model was created.
- `$post->title = 'Hello'` now stores the current locale's value on `save()`. 2.x silently dropped it.
- Translated values are no longer copied into the model's raw attributes, so `getAttributes()`, `getDirty()` and `isDirty()` no longer list them. Use `getDirtyTranslations()`, `isTranslationDirty()` and `wasTranslationChanged()`.
- A model cannot have a set mutator (`setTitleAttribute`) for a translated field. Transform the value before setting it. Get accessors still work and receive the displayed value.
- `translations` and `locales` cannot be translated field names.

### Saving

- `save()` writes the model and its translations in one transaction on the model's connection. If anything fails, nothing is stored and the changes stay pending on the model.
- A `save()` never deletes translations. Use `forgetTranslation()` or `forgetTranslations()`.
- Deleting a model (not a soft delete) deletes its translation rows, in addition to the foreign key cascade.

### translate() and the relation

- `translate()`, `translateOrNew()` and `translateOrFail()` still return the translation model. `translateOrFail()` still throws `NotFoundHttpException`; use `getTranslationOrFail()` for a domain `MissingTranslationException` instead.
- `translateOrDefault()` falls back to `app.fallback_locale`. 2.x used the legacy key or `en`.
- `$post->translations` is still the translations keyed by locale, but the loaded relation is no longer re-keyed, so `toArray()['translations']` stays a list.
- The protected `getTranslation(?string $locale)` is gone. `getTranslation()` is now public: `getTranslation('title', 'ar')`.
- The `Traits\Actions` and `Traits\Scopes` traits are merged into `Translatable`. Use the `Translatable` trait only.

### Scopes

- `translated('ar')` means at least one translated field has a value in Arabic. 2.x matched any row for the locale, even with every field null.
- `translated()` without a locale filters by the current locale, like `notTranslated()`. 2.x did not filter at all and only eager loaded the translations; use `withTranslations()` for that.
- `whereTranslation($field, $value, $locale, $action, $op)` no longer applies `$op` to the locale. `$op` must be `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like` or `not like`, and `$action` must be `whereHas` or `orWhereHas`.
- `translatedSorting()` orders by the translated value with missing values last, then by key.

### Locales

Locales are validated: letters, digits, `-` and `_`, like `en`, `pt-BR`, `zh-Hant-TW`. A malformed locale throws `InvalidLocaleException`. `pt-BR` and `pt_BR` are different locales under the table and json strategies; the columns strategy stores both in the `{field}_pt_br` column and returns the locale as `pt_br`.

### make:translatable

The generator renders the files from stubs (publish them with `--tag=translatable-stubs` to change them) instead of patching `make:model` output, exits `0` on success and never overwrites a file. `--table`, `--json` or `--columns` writes that strategy's marker on the model; without one, the model follows `translatable.strategy`. `-m` is short for `--migrations`.

### Moving to another strategy

Changing `translatable.strategy` or a marker never moves data. Inspect, create the new storage, copy, verify, then switch:

```bash
php artisan translatable:inspect 'App\Models\Post'
php artisan translatable:make-schema 'App\Models\Post' --to=json   # review, then php artisan migrate
php artisan translatable:migrate 'App\Models\Post' --from=table --to=json           # dry run
php artisan translatable:migrate 'App\Models\Post' --from=table --to=json --apply
php artisan translatable:verify 'App\Models\Post' --from=table --to=json
```

Then deploy the marker (`implements JsonTranslatable`) or the configuration. The table rows stay until you drop them yourself.
