## Changing Strategy

Changing a marker or `translatable.strategy` never moves data. To move a model to another strategy, copy its translations first, then switch.

### 1. Inspect

```bash title="Terminal" no-line-numbers
php artisan translatable:inspect 'App\Models\Post'
```

### 2. Create the New Storage

```bash title="Terminal" no-line-numbers
php artisan translatable:make-schema 'App\Models\Post' --to=json
php artisan migrate
```

### 3. Copy

`translatable:migrate` is a dry run by default. It reports what it would copy and any conflicts. Add `--apply` to write.

```bash title="Terminal" no-line-numbers
php artisan translatable:migrate 'App\Models\Post' --from=table --to=json
php artisan translatable:migrate 'App\Models\Post' --from=table --to=json --apply
```

| Option | Description |
| --- | --- |
| `--from`, `--to` | The source and target layouts: `table`, `json` or `columns`. |
| `--apply` | Write the target. Without it (or with `--dry-run`) the command only reports. |
| `--chunk=500` | Records per transaction. |
| `--resume` / `--migration-id=` | Continue the latest unfinished migration, or a given one. |
| `--fields=title,content` | Copy only these fields (partial backfill). |
| `--ignore-columns=` | Extra translation-table columns whose data may stay behind. |
| `--without-scopes` | Include records hidden by global scopes, such as a tenant scope. Soft-deleted records are always included. |
| `--json` | Print the report as JSON. |

The source is never changed. Each record whose target differs from the source is written with the source's values, and the target's previous values are journaled in `storage/translatable/migrations`, so an interrupted copy can resume and an applied one can be undone. When the target cannot hold the source (a locale without a column, or translation-table columns that would stay behind), the copy is blocked, nothing is written, and the command exits with `1`.

### 4. Verify

```bash title="Terminal" no-line-numbers
php artisan translatable:verify 'App\Models\Post' --from=table --to=json
```

It compares every record and exits with `1` when any differs.

### 5. Switch

Deploy the marker (`implements JsonTranslatable`) or the configuration. The old storage stays until you drop it yourself.

### Undo

```bash title="Terminal" no-line-numbers
php artisan translatable:rollback 'App\Models\Post' --migration-id=<id>          # report
php artisan translatable:rollback 'App\Models\Post' --migration-id=<id> --apply
```

Rollback writes the journaled previous values back to the target.
