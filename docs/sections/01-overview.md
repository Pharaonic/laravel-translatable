:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Laravel Translatable

Laravel Translatable gives your Eloquent models translated fields with one API and three storage strategies. Store the translations in a translations table, in one JSON column per field, or in one column per field and locale, and keep the same reads, writes, scopes and events for all three. Saves are transactional, concurrent writers of other locales are kept, and Artisan commands copy your data from one strategy to another without touching the source.

:::features
### Three Strategies {icon="database"}
A translations table, a JSON column per field, or a column per field and locale, chosen per model.

### One API {icon="code"}
The same reads, writes, scopes and events on every strategy, with the same results on every database.

### Typed Fields {icon="badge-check"}
Declare a field as string, int, float, bool, array, object, date or datetime, and mark it required.

### Fallback Locale {icon="translate"}
Displayed values fall back to the Laravel fallback locale, while exact reads never do.

### Safe Saves {icon="shield-check"}
The model and its translations are saved in one transaction, and events fire after it commits.

### Strategy Migrations {icon="switch"}
Inspect, copy, verify and roll back translations between strategies with journaled, resumable commands.
:::

:::info Quick Tip
Start with the default table strategy. It keeps the storage of the 2.x releases, and you can move a model to JSON or columns later with the migration commands, without losing data.
:::
