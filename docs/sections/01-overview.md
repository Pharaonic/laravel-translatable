:::badges
- Laravel Package {color=blue}
- {release.label} {color=green}
- {package.license} License {color=purple}
:::

# Laravel Translatable

Laravel Translatable gives your Eloquent models translated fields, stored the way that fits each model. It supports three storage strategies behind one API: a translations table, a JSON column per field, or a column per field and locale. Reads, writes, scopes, events and fallbacks behave the same under all three, so you can start with one and move a model to another with a command.

:::features
### Translations Table {icon="database"}
One row per model and locale in a translations table, with a real HasMany relation. The default, and the 2.x storage.

### JSON Column per Field {icon="code-brackets"}
Each translated field is one JSON column holding every locale on the model's own row.

### Column per Locale {icon="grid"}
Each field has a column per locale, such as title_en and title_ar, discovered from your table.

### One API {icon="code"}
The same reads, writes, scopes and events under every strategy, with the same results on every database.

### Move Between Strategies {icon="switch"}
Copy, verify and roll back a model's translations between strategies with journaled, resumable commands.

### Safe Saves {icon="shield-check"}
The model and its translations are saved in one transaction, and events fire after it commits.
:::

### The Same Post, Three Ways

The same two translations, as each strategy stores them:

- ===Translations Table

  ```text title="post_translations"
  id | post_id | locale | title
  1  | 1       | en     | Hello
  2  | 1       | ar     | مرحبا
  ```

- ===JSON Column

  ```text title="posts"
  id | title
  1  | {"en": "Hello", "ar": "مرحبا"}
  ```

- ===Column per Locale

  ```text title="posts"
  id | title_en | title_ar
  1  | Hello    | مرحبا
  ```

And the code is the same for all three:

```php
$post->setTranslation('title', 'en', 'Hello')->setTranslation('title', 'ar', 'مرحبا')->save();

$post->getTranslation('title', 'ar');            // "مرحبا"
Post::whereTranslation('title', 'Hello', 'en')->get();
```

:::info Quick Tip
Start with the default translations table. It keeps the storage of the 2.x releases, and you can move a model to JSON or columns later with the [strategy migration commands](#changing-strategy), without losing data.
:::
