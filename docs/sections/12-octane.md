## Octane & Queues

The package is safe under Laravel Octane and in queue workers:

- pending translation changes live on each model instance, never in a shared service;
- `translatable.strategy`, `app.locale` and `app.fallback_locale` are read on every call, so each request's `App::setLocale()` applies;
- request-bound services (your `FallbackPolicy`, database transactions) are resolved from the current request's container;
- the shared caches hold only class metadata and table column lists, keyed by connection, database and table, so tenants on other databases never share them.

:::warning Reload After Schema Changes
Each worker reads a table's columns once. After a migration that adds a locale column (columns strategy) or a translated column, run `php artisan octane:reload` or `php artisan queue:restart` so running workers see it. A normal deploy restarts them anyway.
:::

In a queued job, set the locale you want before reading displayed values, because a worker has no request locale:

```php title="app/Jobs/SendDigest.php"
public function handle()
{
    App::setLocale($this->user->locale);

    $title = $this->post->title;
}
```
