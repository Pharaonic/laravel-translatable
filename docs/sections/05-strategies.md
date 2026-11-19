## Strategies

Laravel Translatable supports three ways to store translations. Every model picks one, and the rest of the package (reads, writes, scopes, events, fallbacks, generators) works the same for all three.

### Choosing a Strategy

| If your model… | Use |
| --- | --- |
| has many locales, long text, or comes from 2.x | `table` |
| has a few translated fields and an open-ended list of locales, and you want no extra table | `json` |
| has a small fixed set of locales, and you filter or sort by translated values with plain indexes | `columns` |

Mix them freely: a `Post` can use a translations table while a `Tag` uses JSON and a `Country` uses columns, in the same application.

### How a Model Picks One

Each model uses exactly one strategy:

1. its marker interface, when it implements one;
2. otherwise `config('translatable.strategy')`;
3. otherwise `table`.

Implementing two markers throws `ConflictingTranslationStrategiesException`.

| Strategy | Marker | Storage | Best for |
| --- | --- | --- | --- |
| `table` | `TableTranslatable` | A translation model, one row per model and locale | Many locales, long text, the 2.x storage |
| `json` | `JsonTranslatable` | One JSON column per field: `{"en": "Hello", "ar": "مرحبا"}` | Few fields, any number of locales, no extra table |
| `columns` | `ColumnsTranslatable` | One column per field and locale: `title_en`, `title_ar` | A small fixed set of locales, plain indexes |

### Table

The 2.x storage. A `Post` model uses a `PostTranslation` model in the same namespace, a `post_translations` table, a `post_id` foreign key and a `locale` column, with a real `HasMany` relation.

```php title="app/Models/Post.php"
use Pharaonic\Laravel\Translatable\Contracts\TableTranslatable;

class Post extends Model implements TableTranslatable
{
    use Translatable;

    protected $translatables = ['title', 'content'];
}
```

```php title="app/Models/PostTranslation.php"
class PostTranslation extends Model
{
    protected $table = 'post_translations';

    protected $fillable = ['locale', 'post_id', 'title', 'content'];
}
```

```php title="database/migrations/2020_01_01_000001_create_post_translations_table.php"
Schema::create('post_translations', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('post_id');
    $table->string('locale', 35);
    $table->string('title')->nullable();
    $table->text('content')->nullable();
    $table->unique(['post_id', 'locale']);
    $table->foreign('post_id')->references('id')->on('posts')->onDelete('cascade');
});
```

The unique index on the foreign key and the locale keeps one row per model and locale, and it is the index every translation query uses. Override the names on the model with `getTranslatableModel()`, `getTranslatableTable()` and `getTranslatableField()`.

### JSON

Each translated field is a JSON column with the field's own name.

```php title="app/Models/Post.php"
use Pharaonic\Laravel\Translatable\Contracts\JsonTranslatable;

class Post extends Model implements JsonTranslatable
{
    use Translatable;

    protected $translatables = ['title', 'content'];
}
```

```php title="database/migrations/2020_01_01_000000_create_posts_table.php"
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->json('title')->nullable();
    $table->json('content')->nullable();
    $table->timestamps();
});
```

A save locks the row, reads the stored JSON again, and writes back only the locales you changed, so two requests that write different locales both keep their values.

### Columns

Each field has one column per locale, named `{field}_{locale}`. The locale is lowercased, with `-` written as `_`: `title_en`, `title_ar`, `title_pt_br`.

```php title="app/Models/Post.php"
use Pharaonic\Laravel\Translatable\Contracts\ColumnsTranslatable;

class Post extends Model implements ColumnsTranslatable
{
    use Translatable;

    protected $translatables = ['title'];
}
```

```php title="database/migrations/2020_01_01_000000_create_posts_table.php"
Schema::create('posts', function (Blueprint $table) {
    $table->id();
    $table->string('title_en')->nullable();
    $table->string('title_ar')->nullable();
    $table->timestamps();
});
```

The locales are the columns the table has. Setting a locale without a column throws `TranslationSchemaException`; add the column with a migration first. Under this strategy, `pt-BR` and `pt_BR` are the same locale and read back as `pt_br`.

:::warning Column Names
On a columns-strategy table, any column named like `title_xx` or `title_xxx` (a translated field, then two or three letters) is read as a locale of that field. Don't give other columns that shape.
:::
