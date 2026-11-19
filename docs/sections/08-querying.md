## Querying

The scopes give the same results under every strategy, on SQLite, MySQL, MariaDB and PostgreSQL. Field names, locales and operators are validated, and values are always bound.

### Filtering by Value

```php
Post::whereTranslation('title', 'Hello', 'en')->get();
Post::whereTranslation('title', 'Hello')->get();                       // any locale
Post::whereTranslation('rank', 3, 'en', 'whereHas', '>=')->get();
Post::whereTranslation('title', 'Hello', 'en')->orWhereTranslation('title', 'Hi', 'en')->get();

Post::whereTranslationLike('title', '%laravel%', 'en')->get();
Post::whereTranslationIn('status', ['draft', 'review'], 'en')->get();
Post::whereTranslationNotIn('status', ['archived'], 'en')->get();
```

`whereTranslation()` keeps its 2.x signature: the fourth argument is `whereHas` or `orWhereHas`, and the operator must be `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `like` or `not like`. Without a locale, `whereTranslation()` and the `Like` scopes match any locale; the `In` scopes use the current locale.

### Filtering by Presence

```php
Post::translated('ar')->get();                         // at least one field has an Arabic value
Post::translated()->get();                             // the same, in the current locale
Post::notTranslated('ar')->get();
Post::whereHasTranslation('ar', 'content')->get();     // content has an Arabic value
Post::whereMissingTranslation('ar', 'content')->get();
Post::whereTranslationProgress('ar', '<', 100)->get(); // required fields still missing in Arabic
```

### Sorting

```php
Post::orderByTranslation('title', 'asc', 'ar')->get();
Post::orderByTranslation('title', 'asc', 'ar', true)->get();   // sort by the fallback value when Arabic is missing
Post::translatedSorting('ar', 'title', 'desc')->get();         // 2.x: Arabic only, then by key
```

Records without a value always sort last.

### Eager Loading

Under the table strategy, `withTranslations()` eager loads the `translations` relation, optionally only some locales. Under json and columns the values are already on the row.

```php
Post::withTranslations()->get();
Post::withTranslations(['en', 'ar'])->get();
```

Reading a loaded locale needs no query. A locale outside the list is never read as missing: the first read of one loads the model's other translations once. Include the fallback locale in the list when your pages show fallback values.

:::tip Indexes
Under the columns strategy, index the `{field}_{locale}` columns you filter or sort by. Under the table strategy, the unique index on the foreign key and `locale` already serves the translation queries.
:::
