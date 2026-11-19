## Field Definitions

`$translatables` lists untyped fields. To give fields a type or make them required, return them from a `translatable()` method instead.

```php title="app/Product.php"
class Product extends Model implements JsonTranslatable
{
    use Translatable;

    public function translatable(): array
    {
        return [
            'name' => ['type' => 'string', 'required' => true],
            'description' => ['required' => true],
            'price' => ['type' => 'float'],
            'features' => ['type' => 'array'],
            'released_on' => ['type' => 'date'],
            'notes' => ['fallback' => false],
        ];
    }
}
```

A plain list works in the method too: `return ['name', 'description'];`. When both the method and `$translatables` exist, the method wins, and they must list the same fields.

### Options

| Option | Values | Description |
| --- | --- | --- |
| `type` | `string`, `int`, `float`, `bool`, `array`, `object`, `date`, `datetime`, or a custom cast class (Laravel 7+) | Values are cast when read and checked when set. |
| `required` | `true` / `false` | Required fields decide completeness: `hasCompleteTranslation()`, `missingTranslations()`, `translationProgress()`. |
| `fallback` | `true` / `false` | `false` never shows another locale's value for this field. |

A typed field only accepts values of its type, so nothing is silently converted: setting `'5'` or `1.5` on an `int` field throws `InvalidTranslationValueException`. `null` is always accepted.

### Completeness

```php
$product->hasCompleteTranslation('ar');   // false
$product->missingTranslations('ar');      // ['description']
$product->translationProgress('ar');      // 50

Product::whereTranslationProgress('ar', '>=', 100)->get();
```

`translationProgress()` is the percentage of required fields that have a value, and `100` for a model without required fields.

### Rules

- `translations` and `locales` cannot be field names, and neither can the model's key.
- A translated field cannot have a set mutator (`setTitleAttribute`). Get accessors still work and receive the displayed value.
- Storage options (`column`, `strategy`, …) are not field options. Storage comes only from the strategy.

Breaking a rule throws `InvalidTranslatableDefinitionException` the first time the model is used.
