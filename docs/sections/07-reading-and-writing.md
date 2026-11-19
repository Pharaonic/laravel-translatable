## Reading & Writing

Every method takes an optional locale; without one it uses the current locale (`App::getLocale()`). Locales are validated (letters, digits, `-` and `_`, like `en`, `pt-BR` or `zh-Hant-TW`), and a malformed one throws `InvalidLocaleException`.

### Exact Values and Displayed Values

An exact read returns what is stored for the locale, or `null`. A displayed read may fall back to `app.fallback_locale`.

```php
$post->getTranslation('title', 'fr');          // null: nothing stored in French
$post->getTranslation('title', 'fr', true);    // "Hello": displayed, from the fallback locale
$post->title;                                  // displayed, in the current locale
```

`resolveTranslation()` tells you where a displayed value came from:

```php
$resolved = $post->resolveTranslation('title', 'fr');

$resolved->value;            // "Hello"
$resolved->requestedLocale;  // "fr"
$resolved->resolvedLocale;   // "en"
$resolved->isFallback;       // true
$resolved->isMissing;        // false
```

`getTranslationOrFail()` returns the exact value or throws `MissingTranslationException`.

### Reading Everything

```php
$post->getTranslations('title');          // ['ar' => 'مرحبا', 'en' => 'Hello']
$post->getTranslations();                 // ['title' => ['ar' => …, 'en' => …], 'content' => […]]
$post->getTranslationsForLocale('ar');    // ['title' => 'مرحبا', 'content' => null]
$post->getTranslatedLocales();            // ['ar', 'en']
$post->hasTranslation('ar');              // true: any field in Arabic
$post->hasTranslation('ar', 'content');   // false
```

### Writing and Removing

Writes are staged on the model and stored by `save()`.

```php
$post->setTranslation('title', 'ar', 'مرحبا');
$post->setTranslations('title', ['en' => 'Hello', 'ar' => 'مرحبا']);
$post->fillTranslations(['en' => ['title' => 'Hello', 'content' => 'Body']]);

$post->forgetTranslation('title', 'ar');   // removes one value
$post->forgetTranslations('ar');           // removes every field of the locale

$post->save();
```

Setting a field that is not translated throws `UnknownTranslatableFieldException`. A `save()` never deletes translations you didn't forget.

### Dirty Tracking

Translated values are not model attributes, so `isDirty()` and `getDirty()` don't list them. Use the translation methods:

```php
$post->setTranslation('title', 'ar', 'أهلا');

$post->isTranslationDirty();                 // true
$post->isTranslationDirty('title', 'ar');    // true
$post->getDirtyTranslations();               // ['title' => ['ar' => 'أهلا']]

$post->save();

$post->wasTranslationChanged('title');       // true
$post->getTranslationChanges();              // ['title' => ['ar' => 'أهلا']]
```

Setting a value equal to the stored one is not a change. `refresh()` discards unsaved translation changes, and `replicate()` copies the translations to the replica as unsaved changes.

### translate()

`translate()` returns one locale's translation. Under the table strategy it is the translation model; under json and columns it is a `LocaleTranslation` object you can read and write like one.

```php
$translation = $post->translate('ar');
$translation->title;                  // "مرحبا"
$post->translate();                   // the current locale's
$post->translate('de');               // null: no German value

$post->translateOrNew('fr')->title = 'Bonjour';
$post->save();                        // stores the French title

$post->translateOrDefault('fr');      // the French translation, else the fallback locale's, else null
$post->translateOrFail('de');         // throws NotFoundHttpException (a 404) when missing
```

A locale counts as translated when at least one field has a value in it. Values set through a translation (`$translation->title = …`) are stored by the model's `save()`, in the same transaction, under every strategy.

### Serialization

When the translations are loaded (with `withTranslations()`, or after a read) or changed, `toArray()` and JSON include each translated field's displayed value in the current locale. The raw JSON or `{field}_{locale}` columns are never serialized. Under the table strategy, a loaded `translations` relation is serialized as a list, like any relation.
