## API Reference

### Reading

| Method | Description | Returns |
| --- | --- | --- |
| `getTranslation(string $field, ?string $locale = null, bool $fallback = false)` | The exact value, or the displayed value with `$fallback` | `mixed` |
| `getTranslations(?string $field = null)` | `locale => value` of a field, or `field => [locale => value]` | `array` |
| `getTranslationsForLocale(string $locale)` | `field => value` of a locale | `array` |
| `getTranslatedLocales()` | Locales with at least one value | `array` |
| `resolveTranslation(string $field, ?string $locale = null)` | The displayed value and where it came from | `ResolvedTranslation` |
| `getTranslationOrFail(string $field, ?string $locale = null)` | The exact value, or throws `MissingTranslationException` | `mixed` |
| `hasTranslation(?string $locale = null, ?string $field = null)` | Whether a value exists | `bool` |
| `hasCompleteTranslation(string $locale)` | Whether every required field has a value | `bool` |
| `missingTranslations(string $locale)` | Required fields without a value | `array` |
| `translationProgress(string $locale)` | Percentage of required fields with a value | `int` |

### Writing

| Method | Description | Returns |
| --- | --- | --- |
| `setTranslation(string $field, string $locale, mixed $value)` | Stage one value | `$this` |
| `setTranslations(string $field, array $values)` | Stage `locale => value` | `$this` |
| `fillTranslations(array $translations)` | Stage `locale => [field => value]` | `$this` |
| `forgetTranslation(string $field, string $locale)` | Stage the removal of one value | `$this` |
| `forgetTranslations(string $locale)` | Stage the removal of a locale | `$this` |

### Dirty Tracking

| Method | Description | Returns |
| --- | --- | --- |
| `getDirtyTranslations()` | Unsaved changes, `null` for removals | `array` |
| `isTranslationDirty(?string $field = null, ?string $locale = null)` | Whether there are unsaved changes | `bool` |
| `getTranslationChanges()` | Changes stored by the last save | `array` |
| `wasTranslationChanged(?string $field = null, ?string $locale = null)` | Whether the last save changed it | `bool` |

### translate() and the Table Strategy

| Method | Description | Returns |
| --- | --- | --- |
| `translate(?string $locale = null)` | The locale's translation, or `null` | `Model\|LocaleTranslation\|null` |
| `translateOrNew(string $locale)` | The locale's translation, created when missing | `Model\|LocaleTranslation` |
| `translateOrDefault(?string $locale = null)` | The locale's, else the fallback locale's | `Model\|LocaleTranslation\|null` |
| `translateOrFail(string $locale)` | The locale's, or throws `NotFoundHttpException` | `Model\|LocaleTranslation` |
| `translations()` | The `HasMany` relation (table strategy only) | `HasMany` |
| `$model->translations` | Table: translation models keyed by locale. Others: `getTranslations()` | `Collection\|array` |
| `$model->locales` | `getTranslatedLocales()` | `array` |

### Scopes

| Scope | Description |
| --- | --- |
| `translated(?string $locale = null)` | At least one field has a value in the locale |
| `notTranslated(?string $locale = null)` | No field has a value in the locale |
| `whereHasTranslation(string $locale, ?string $field = null)` | The field (or any field) has a value |
| `whereMissingTranslation(string $locale, ?string $field = null)` | The field (or every field) has no value |
| `whereTranslation(string $field, mixed $value, ?string $locale = null, string $action = 'whereHas', string $op = '=')` | Compare a value |
| `orWhereTranslation(string $field, mixed $value, ?string $locale = null)` | `or` equality |
| `whereTranslationLike(string $field, string $pattern, ?string $locale = null)` | `like` |
| `orWhereTranslationLike(string $field, string $pattern, ?string $locale = null)` | `or like` |
| `whereTranslationIn(string $field, iterable $values, ?string $locale = null)` | `in` |
| `whereTranslationNotIn(string $field, iterable $values, ?string $locale = null)` | `not in` |
| `whereTranslationProgress(string $locale, string $operator, int\|float $percentage)` | Compare the completeness percentage |
| `orderByTranslation(string $field, string $direction = 'asc', ?string $locale = null, bool $fallback = false)` | Sort, missing values last |
| `translatedSorting(string $locale, string $field, string $method = 'asc')` | 2.x: translated in the locale, sorted, then by key |
| `withTranslations(string\|array\|null $locales = null)` | Eager load (table strategy) |

### Classes

| Class | Description |
| --- | --- |
| `Translatable` | The model trait |
| `Contracts\TableTranslatable`, `JsonTranslatable`, `ColumnsTranslatable` | Strategy markers |
| `Contracts\FallbackPolicy` | `chain(Model $model, string $field, string $locale): array` |
| `Data\ResolvedTranslation` | `field`, `value`, `requestedLocale`, `resolvedLocale`, `isFallback`, `isMissing` |
| `Data\LocaleTranslation` | One locale of a json or columns model: `getLocale()`, `getModel()`, property and array access, `toArray()` |
| `Integrations\Sluggable\HasTranslatedSlugs` | `getLocalizedSlug()`, `findByTranslatedSlug()` |
| `Integrations\Seo\TranslatableSeo` | `apply()`, `publishedLocales()` |

### Exceptions

Every exception implements `Exceptions\TranslatableException`.

| Exception | Thrown when |
| --- | --- |
| `InvalidTranslationStrategyException` | The configured strategy is not `table`, `json` or `columns` |
| `ConflictingTranslationStrategiesException` | A model implements more than one marker |
| `InvalidTranslatableDefinitionException` | A field definition breaks a rule |
| `InvalidLocaleException` | A locale is malformed |
| `UnknownTranslatableFieldException` | A field is not translated |
| `InvalidTranslationValueException` | A value doesn't match the field's type |
| `MissingTranslationException` | `getTranslationOrFail()` finds no value |
| `TranslationConflictException` | A save keeps conflicting after its retries |
| `TranslationSchemaException` | The storage a strategy needs is missing |
| `UnsupportedTranslationQueryException` | A query or method doesn't fit the strategy |
