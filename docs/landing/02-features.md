---
view: components.packages.features
variant: compact
badge: Three Strategies, One API
title: Pick the storage that fits each model
subtitle: Every strategy has the same reads, writes, scopes and events, so changing your mind costs a command, not a rewrite.
items:
  - icon: database
    title: Translations Table
    text: "One row per model and locale in `post_translations`, with a real `HasMany` relation. The default, and the 2.x storage."
  - icon: code-brackets
    title: JSON Column per Field
    text: "`title` holds `{\"en\": \"Hello\", \"ar\": \"مرحبا\"}` on the model's own row. No extra table, any number of locales."
  - icon: grid
    title: Column per Locale
    text: "`title_en` and `title_ar` columns, read from your table. Plain columns, plain indexes, a fixed set of locales."
  - icon: code
    title: The Same API Everywhere
    text: "`$post->title`, `whereTranslation()` and `orderByTranslation()` return the same results under all three, on SQLite, MySQL, MariaDB and PostgreSQL."
  - icon: switch
    title: Move Between Them
    text: "`translatable:migrate` copies a model's translations to another strategy, `translatable:verify` checks them, `translatable:rollback` undoes it."
  - icon: shield-check
    title: Safe Saves
    text: One transaction per save, concurrent writers of other locales kept, events after commit.
---
