# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Translations for Eloquent models through one API (`Translatable` trait) over three storage strategies: `table` (a translation model and a real `HasMany` relation, the 2.x storage), `json` (one JSON column per field) and `columns` (one column per field and locale), plus tools to move a model between them.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major, all carrying the same code (first released as `N.0.0` on each line). Each line's PHP range, `illuminate/*` constraint, and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit, because Pest cannot install on Laravel 6; later lines run Pest. `develop` follows the newest line. 2.x (`main`, tags `1.0.0`–`2.0.0`) is the legacy release line and is not developed further.

The code therefore stays compatible with PHP 8.0 and Laravel 6: no enums, `readonly`, `array_is_list()`, first-class callables or later syntax. Framework APIs newer than Laravel 6 are detected at runtime (`getRawOriginal()`, `CastsAttributes`, `NullDispatcher`, the 8.19+ transaction manager), and generic relation and builder PHPDoc only exists from 11.x.

## Core library

None. The package is Eloquent glue; the slug integration uses `pharaonic/php-slugify` only when it is installed.

## Public API invariants

Configuration keys (`translatable.*`), publish tags (`pharaonic`, `laravel-translatable`, `translatable-config`, `translatable-stubs`), the stub names in `stubs/`, and the `make:translatable`, `translatable:inspect`, `translatable:make-schema`, `translatable:migrate`, `translatable:verify` and `translatable:rollback` commands are public API.

- The configuration lives at `config/translatable.php` under `translatable.*`, not `pharaonic.translatable`: the owner's v3 specification fixed `config('translatable.strategy')`. The legacy `Pharaonic.translatable.default` key is not read.
- The `Translatable` trait's public methods and scopes, the three marker interfaces, `FallbackPolicy`, the `ResolvedTranslation`, `LocaleTranslation` and `MigrationReport` data classes, the events in `src/Events`, the exceptions in `src/Exceptions`, `HasTranslatedSlugs` and `TranslatableSeo`. Classes marked `@internal` are not.
- The 2.x table conventions: `getTranslatableModel()`, `getTranslatableField()`, the `locale` column and the `$translationsKey` payload key (default `locale`).

## Package-specific constraints

- Exactly three strategies. A model's strategy is exactly one marker interface, else `config('translatable.strategy')`, else `table`. Never a model property, method, attribute or per-field option. Changing it never moves data.
- The configuration has one key, `strategy`. Locales come from `app.locale` and `app.fallback_locale`; storage names are conventions (JSON column = field, `{field}_{locale}` columns with the locales the table has). Add keys only with a concrete need, and with the owner.
- On a columns-strategy table, a column named `{field}_{2–3 letters}` reads as a locale of that field; other columns must not use that pattern.
- No translation workflow (states, review, publication): the owner left it out of the package.
- A `save()` never deletes translations; exact reads never fall back; a fallback never creates a slug, an hreflang link or a canonical URL.
- Every field and locale is validated before it reaches SQL; values are always bound. JSON and columns identifiers come only from validated field names and locale tags.
- The services are singletons that are safe under Octane: pending changes live on the model, config and the locale are read on every call, caches hold only class metadata and schema columns (keyed by connection, database and table), and a request-bound service (fallback policy, `db.transactions`, `pharaonic-seo`) is resolved from `Container::getInstance()` at call time. Never inject the container or a request-bound service into a singleton.
- The traits are analysed by PHPStan only through `tests/Fixtures`, which use them.
- The test suite runs on SQLite by default; it is not run in Docker. `TRANSLATABLE_DB=mysql|mariadb|pgsql` with `TRANSLATABLE_DB_HOST`, `_PORT`, `_DATABASE`, `_USERNAME` and `_PASSWORD` runs it on a server, which also enables the parallel-writer tests (they need `pcntl`).

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/translatable

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
