## Laravel Translatable (pharaonic/laravel-translatable)

Translated fields for Eloquent models, stored in one of three strategies behind one API: a translations table (`table`, the default), a JSON column per field (`json`), or a column per field and locale (`columns`). Use the `translatable-development` skill for the full API, the scopes and the commands.

### Conventions

- Add the `Pharaonic\Laravel\Translatable\Translatable` trait and list the translated fields in `protected $translatables = [...]`, or return them with types from `translatable(): array`.
- A model picks its strategy with one marker interface (`TableTranslatable`, `JsonTranslatable`, `ColumnsTranslatable` in `Pharaonic\Laravel\Translatable\Contracts`), else `config('translatable.strategy')`, else `table`. Changing it never moves data; use `translatable:migrate`.
- Locales are Laravel's: `app.locale` is the current locale, `app.fallback_locale` the fallback. There is no locale list in the package config.
- Translated values are not model attributes: use `getDirtyTranslations()` / `isTranslationDirty()`, not `getDirty()`. Never add a set mutator for a translated field.
- `$fillable` is never changed for you: add the translated fields, or the `locale` payload key, yourself.
- A json model casts its translated JSON columns to `array` (`make:translatable --json` writes it).

### Main features

@verbatim
<code-snippet name="Declare, write and read translations" lang="php">
use Pharaonic\Laravel\Translatable\Translatable;

class Post extends Model
{
    use Translatable;

    protected $translatables = ['title', 'content'];
}

$post->setTranslation('title', 'en', 'Hello')->setTranslation('title', 'ar', 'مرحبا')->save();

$post->title;                                // current locale, falls back to app.fallback_locale
$post->getTranslation('title', 'ar');        // exact stored value, or null
$post->getTranslations('title');             // ['ar' => 'مرحبا', 'en' => 'Hello']
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Query by translated values" lang="php">
Post::whereTranslation('title', 'Hello', 'en')->get();
Post::translated('ar')->orderByTranslation('title', 'asc', 'ar')->get();
Post::withTranslations(['en', 'ar'])->paginate();
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="Generate a translatable model" lang="bash">
php artisan make:translatable Post -m            # default strategy, with migrations
php artisan make:translatable Post --json -m     # or --table / --columns --locales=en,ar
</code-snippet>
@endverbatim
