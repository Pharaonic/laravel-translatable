## Upgrading from 2.x

The table strategy is the default and keeps the 2.x storage, so an existing database needs no migration. Make these changes, then run your tests.

### Configuration

- The configuration is `config/translatable.php` with one key, `strategy`. `config/Pharaonic/translatable.php` is no longer published or read.
- 2.x fell back to `config('Pharaonic.translatable.default')` or `en`. Set `app.fallback_locale` to that locale instead.

### Models

- Rename `$translatableAttributes` to `$translatables`. The old name is deprecated but still read when `$translatables` is missing.
- `$fillable` is no longer changed for you. Add `locale` (or your `$translationsKey`) or the translated fields yourself.
- The translations payload only accepts translated fields; any other key throws `UnknownTranslatableFieldException`.
- Use the `Translatable` trait only: `Traits\Actions` and `Traits\Scopes` are merged into it.
- A translated field cannot have a set mutator. Transform the value before setting it.
- `translations` and `locales` cannot be translated field names.

### Behavior

- `$post->title` reads the current locale on every access, and setting it now stores the value on `save()` (2.x dropped it).
- Translated values are no longer raw attributes: use `getDirtyTranslations()`, `isTranslationDirty()` and `wasTranslationChanged()` instead of `getDirty()`.
- `save()` is transactional and never deletes translations. Use `forgetTranslation()` or `forgetTranslations()`.
- The protected `getTranslation(?string $locale)` is gone. `getTranslation('title', 'ar')` is public and returns a value.
- `translated('ar')` needs at least one field with an Arabic value.
- `whereTranslation()` no longer applies its operator to the locale, and only accepts the listed operators and `whereHas` / `orWhereHas`.
- `translateOrDefault()` falls back to `app.fallback_locale`.
- `make:translatable` renders stubs, takes `--table`, `--json`, `--columns` and `-m`, and never overwrites files.

The full list is in `UPGRADING.md` in the repository.
