# Changelog

All notable changes to this project are documented here.

## 6.0.0 - Unreleased

A redesign with three storage strategies behind one API. The table strategy is the default and keeps the 2.x storage. 2.x applications follow `UPGRADING.md`.

### Added

- Three strategies, one per model: `table` (a translation model and a real `HasMany` relation), `json` (one JSON column per field) and `columns` (one column per field and locale). A model chooses one with the `TableTranslatable`, `JsonTranslatable` or `ColumnsTranslatable` marker interface, or inherits `config('translatable.strategy')`, `table` by default. Invalid or conflicting choices throw `InvalidTranslationStrategyException` or `ConflictingTranslationStrategiesException`.
- `config/translatable.php` with one key, `strategy`. Locales come from `app.locale` and `app.fallback_locale`. The json strategy stores a field in the JSON column of the same name; the columns strategy stores it in `{field}_{locale}` columns and takes its locales from the table.
- Field definitions as a `$translatables` list, or with types and required fields through `translatable(): array`: `string`, `int`, `float`, `bool`, `array`, `object`, `date`, `datetime`, and custom casts on Laravel 7+. Typed fields reject lossy values.
- Reading: `getTranslation()`, `getTranslations()`, `getTranslationsForLocale()`, `getTranslatedLocales()`, `resolveTranslation()` (a `ResolvedTranslation` with the resolved locale and whether it fell back) and `getTranslationOrFail()`.
- Writing: `setTranslation()`, `setTranslations()`, `fillTranslations()`, `forgetTranslation()`, `forgetTranslations()`, and translated fields as attributes for the current locale.
- Completeness: `hasCompleteTranslation()`, `missingTranslations()` and `translationProgress()`.
- Dirty tracking: `getDirtyTranslations()`, `isTranslationDirty()`, `getTranslationChanges()` and `wasTranslationChanged()`.
- Scopes: `whereHasTranslation()`, `whereMissingTranslation()`, `whereTranslationIn()`, `whereTranslationNotIn()`, `whereTranslationProgress()`, `orderByTranslation()` (missing values last, optional fallback order) and `withTranslations()`, with the same results under every strategy on SQLite, MySQL, MariaDB and PostgreSQL.
- A `LocaleTranslation` proxy returned by `translate()` for the json and columns strategies.
- `TranslationSet`, `TranslationRemoved` and `TranslationsMigrated` events, dispatched after the save's transaction commits.
- A `FallbackPolicy` contract for custom fallback chains.
- `translatable:inspect`, `translatable:make-schema`, `translatable:migrate`, `translatable:verify` and `translatable:rollback`: dry-run by default, resumable, journaled copies between any two strategies that never change the source.
- `HasTranslatedSlugs`: per-locale slugs from each locale's own value, unique per locale, for every strategy.
- `TranslatableSeo`: title and description for pharaonic/laravel-seo from exact values, and hreflang and canonical links only for complete locales with a URL.

### Changed

- `save()` stores the model and its translations in one transaction, retries deadlocks, and keeps the changes pending when it fails.
- JSON translations are patched after a row lock and a fresh read, and the columns strategy writes only changed columns, so concurrent writers of other locales or fields are kept. The table strategy updates the row a concurrent writer created instead of failing on the unique index.
- `$model->title` reads the current locale on every access and is no longer copied into the raw attributes. Setting it stores the translation.
- The translations payload only accepts translated fields and follows mass-assignment rules; `$fillable` is no longer changed.
- `translated($locale)` needs at least one present field; `whereTranslation()` no longer applies its operator to the locale and only accepts known operators.
- `translateOrDefault()` falls back to `app.fallback_locale`; the legacy `Pharaonic.translatable.default` is no longer read.
- `make:translatable` renders publishable stubs (`translatable-stubs`), takes `--table`, `--json` or `--columns` (the configured default without one) and `-m` for `--migrations`, never overwrites files and exits `0` on success.
- Commands are registered only in the console.

### Fixed

- A `save()` after loading the translations no longer deletes the stored translations missing from the payload (introduced on `main` after 2.0.0, never released).
- Setting a translated attribute is no longer discarded on save.
- A failed translation write no longer leaves the model row saved.

### Deprecated

- `$translatableAttributes`, renamed to `$translatables`. It is still read when a model has no `$translatables`.

### Removed

- The `Traits\Actions` and `Traits\Scopes` traits, merged into `Translatable`.
- The protected `getTranslation(?string $locale)` method.
- Publishing `config/Pharaonic/translatable.php`.

### Compatibility

- Laravel 6.20+ on PHP 8.0. The package requires the `illuminate/*` components it uses instead of `laravel/framework`.
