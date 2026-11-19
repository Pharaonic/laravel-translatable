## Configuration

The configuration has one key: the default strategy.

```php title="config/translatable.php"
return [
    // table, json or columns
    'strategy' => env('TRANSLATABLE_STRATEGY', 'table'),
];
```

| Key | Default | Description |
| --- | --- | --- |
| `strategy` | `table` | The strategy of every model that has no strategy marker. Read from `TRANSLATABLE_STRATEGY`. |

A model with a marker interface (`TableTranslatable`, `JsonTranslatable` or `ColumnsTranslatable`) always uses that strategy, whatever the configuration says. An invalid value throws `InvalidTranslationStrategyException`.

### Locales

The package has no locale list of its own. It uses Laravel's:

| Laravel setting | Used for |
| --- | --- |
| `app.locale` (`App::getLocale()`) | The current locale: `$post->title`, and every method called without a locale |
| `app.fallback_locale` | The locale a displayed value falls back to |

Change the current locale with `App::setLocale('ar')`, for example in a middleware. It is read on every access, so the same model shows each request's locale.

:::warning Changing the Strategy
Changing `strategy`, or a model's marker, never moves data. A model that switches strategy reads empty translations until you copy them with the [strategy migration commands](#changing-strategy).
:::
