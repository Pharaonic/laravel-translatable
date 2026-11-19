---
view: components.packages.features
variant: compact
badge: Key Features
title: Everything you need for multilingual models
subtitle: Add one trait, list your fields, and every strategy works the same way.
items:
  - icon: database
    title: Three Strategies
    text: A translations table, a JSON column per field, or `title_en` and `title_ar` columns, chosen per model.
  - icon: translate
    title: Laravel Locales
    text: "`$post->title` follows `App::getLocale()` and falls back to `app.fallback_locale`."
  - icon: search
    title: Translated Scopes
    text: "`whereTranslation()`, `orderByTranslation()` and more, with the same results on SQLite, MySQL, MariaDB and PostgreSQL."
  - icon: badge-check
    title: Typed and Required Fields
    text: Typed values, plus `missingTranslations()` and `translationProgress()` for completeness.
  - icon: shield-check
    title: Safe Saves
    text: One transaction per save, concurrent writers of other locales kept, events after commit.
  - icon: switch
    title: Strategy Migrations
    text: "`translatable:migrate` copies, verifies and rolls back translations between strategies."
---
