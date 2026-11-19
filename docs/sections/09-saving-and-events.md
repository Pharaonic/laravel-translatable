## Saving & Events

### Transactions

`save()` writes the model and its translations in one transaction on the model's connection. If anything fails, nothing is stored and the changes stay pending on the model, so you can save again.

- **Table**: changed rows are updated, new locales inserted, forgotten locales deleted. When another request inserts the same locale at the same moment, the row it created is updated instead of failing on the unique index.
- **JSON**: the row is locked and read again, and only the changed locales are written, so concurrent writers of other locales or fields are kept.
- **Columns**: only the changed columns are written.

Deadlocks and serialization failures are retried up to three times. Deleting a model (not a soft delete) deletes its translation rows. A `save()` with no changes writes nothing and doesn't touch `updated_at`.

### Events

| Event | Properties | Dispatched |
| --- | --- | --- |
| `Events\TranslationSet` | `model`, `field`, `locale`, `value`, `previous` | Once per stored field and locale |
| `Events\TranslationRemoved` | `model`, `field`, `locale`, `previous` | Once per removed field and locale |
| `Events\TranslationsMigrated` | `model`, `from`, `to`, `migrationId`, `records` | When `translatable:migrate --apply` finishes |

The translation events fire after the save's transaction commits; inside your own `DB::transaction()`, after it commits on Laravel 8.19+. Saving inside `Model::withoutEvents()` dispatches none.

```php title="app/Providers/EventServiceProvider.php"
use Pharaonic\Laravel\Translatable\Events\TranslationSet;

protected $listen = [
    TranslationSet::class => [
        \App\Listeners\ClearPageCache::class,
    ],
];
```

### Custom Fallbacks

Bind your own `FallbackPolicy` to decide which locales a displayed value may fall back to. `chain()` returns the requested locale first, then its fallbacks in order.

```php title="app/Providers/AppServiceProvider.php"
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\Translatable\Contracts\FallbackPolicy;

public function register()
{
    $this->app->singleton(FallbackPolicy::class, function () {
        return new class implements FallbackPolicy {
            public function chain(Model $model, string $field, string $locale): array
            {
                return $locale === 'ar-EG' ? ['ar-EG', 'ar', 'en'] : [$locale, 'en'];
            }
        };
    });
}
```
