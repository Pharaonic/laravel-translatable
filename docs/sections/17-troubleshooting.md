## Troubleshooting

### My translations disappeared after changing the strategy

Changing a marker or `translatable.strategy` doesn't move data, so the model now reads the new, empty storage. Switch back, then copy the data with `translatable:migrate` before switching again (see [Changing Strategy](#changing-strategy)).

### Setting a locale throws TranslationSchemaException

The model uses the columns strategy and the table has no `{field}_{locale}` column for that locale. Add the column with `translatable:make-schema --to=columns --locales=…` and migrate. Under Octane or queue workers, reload them afterwards.

### $post->title shows another language

The current locale has no value, so the displayed value comes from `app.fallback_locale`. Use `getTranslation('title')` for the exact value, `resolveTranslation('title')->isFallback` to detect it, or `'fallback' => false` on the field to turn it off.

### isDirty() is false after setting a translation

Translated values are not model attributes. Use `isTranslationDirty()` and `getDirtyTranslations()`.

### Mass assignment ignores the translations

`$fillable` is not changed for you. Add the translated fields, or the `locale` payload key, to `$fillable`.

### InvalidTranslatableDefinitionException on a field

The field has a set mutator, uses a reserved name (`translations`, `locales` or the key), has an unknown option, or `translatable()` and `$translatables` list different fields. The message names the cause.

### The foreign key of a generated translations table fails on MySQL

The foreign key column must have the exact type of the model's key. `translatable:make-schema --to=table` reads it from the database; if it warned that it couldn't, change the column by hand (`unsignedInteger` for `increments`, `uuid` or `char` for UUID keys).

### A column on a columns-strategy table is treated as a locale

Any column named like a translated field followed by two or three letters (`title_xx`) is read as a locale of that field. Rename the column.
