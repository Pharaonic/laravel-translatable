#### Beta Version

##### Command
```bash
php artisan make:translatable Post --migrations
```

##### Relationship
- translations

##### Scopes
- translated(string $locale = null)
- notTranslated(string $locale = null)
- translatedSorting(string $locale, string $field, string $method = 'asc')
- whereTranslation(string $field, $value, string $locale = null, string $action = 'whereHas', string $op = '=')
- orWhereTranslation(string $field, $value, string $locale = null)
- whereTranslationLike(string $field, $value, string $locale = null)
- orWhereTranslationLike(string $field, $value, string $locale = null)

##### Actions
- translate(string $locale = null)
- translateOrFail(string $locale)
- translateOrNew(string $locale)
- translateOrDefault(string $locale = null)
- hasTranslation(string $locale = null)

##### Attributes
- locales