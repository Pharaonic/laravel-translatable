## Generators

### make:translatable

Create a translatable model, its translation model under the table strategy, and with `-m` its migrations.

```bash title="Terminal" no-line-numbers
php artisan make:translatable Post -m
php artisan make:translatable Blog/Post --json -m
php artisan make:translatable Product --columns --locales=en,ar -m
php artisan make:translatable Page --table
```

| Option | Description |
| --- | --- |
| `--table`, `--json`, `--columns` | Write that strategy's marker on the model. Without one, the model has no marker and follows `translatable.strategy`. At most one. |
| `-m`, `--migrations` | Also create the migrations: the model's table, plus the translations table under the table strategy. |
| `--locales=en,ar` | The locale columns of the columns strategy. Defaults to `app.locale` and `app.fallback_locale`. |

The model goes to `app/Models` when that folder exists, else to `app`. The command never overwrites a file; it reports the existing file and exits with `1`.

The translations-table migration has the `locale` column, the unique index on the foreign key and `locale`, and a foreign key that cascades deletes.

### Customizing the Stubs

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=translatable-stubs
```

Edit the copies in `stubs/translatable`: `model.stub`, `model.translation.stub`, `migration.create.stub` and `migration.add.stub`. The generators use a published stub instead of the package's.

### translatable:inspect

Show a model's strategy, where it comes from, its fields, and which layouts have a schema and data.

```bash title="Terminal" no-line-numbers
php artisan translatable:inspect 'App\Models\Post'
php artisan translatable:inspect 'App\Models\Post' --json
```

### translatable:make-schema

Write a migration that adds another strategy's storage to an existing model, for you to review and run.

```bash title="Terminal" no-line-numbers
php artisan translatable:make-schema 'App\Models\Post' --to=json
php artisan translatable:make-schema 'App\Models\Post' --to=columns --locales=en,ar,fr
php artisan translatable:make-schema 'App\Models\Post' --to=table
```

`--to=table` needs the translation model first. Its foreign key column gets the exact type of the model's key (`increments`, `bigIncrements`, UUID, ULID or string), read from the database, so MySQL and PostgreSQL accept the foreign key. When the type can't be read, the command warns you to check the column.
