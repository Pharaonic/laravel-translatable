## Examples

### 1. A Multilingual Blog

A table-strategy post with typed, required fields and a locale from the URL.

- ===Model

  ```php title="app/Models/Post.php"
  namespace App\Models;

  use Illuminate\Database\Eloquent\Model;
  use Pharaonic\Laravel\Translatable\Translatable;

  class Post extends Model
  {
      use Translatable;

      protected $fillable = ['published', 'locale'];

      public function translatable(): array
      {
          return [
              'title' => ['type' => 'string', 'required' => true],
              'content' => ['required' => true],
          ];
      }
  }
  ```

- ===Middleware

  ```php title="app/Http/Middleware/SetLocale.php"
  namespace App\Http\Middleware;

  use Closure;
  use Illuminate\Support\Facades\App;

  class SetLocale
  {
      public function handle($request, Closure $next)
      {
          App::setLocale($request->route('locale'));

          return $next($request);
      }
  }
  ```

- ===Controller

  ```php title="app/Http/Controllers/PostController.php"
  namespace App\Http\Controllers;

  use App\Models\Post;

  class PostController extends Controller
  {
      public function index(string $locale)
      {
          $posts = Post::whereTranslationProgress($locale, '>=', 100)
              ->orderByTranslation('title', 'asc', $locale)
              ->paginate();

          return view('posts.index', compact('posts'));
      }

      public function show(string $locale, Post $post)
      {
          abort_unless($post->hasCompleteTranslation($locale), 404);

          return view('posts.show', compact('post'));
      }
  }
  ```

- ===Routes

  ```php title="routes/web.php"
  use App\Http\Controllers\PostController;

  Route::middleware('locale')->prefix('{locale}')->group(function () {
      Route::get('posts', [PostController::class, 'index']);
      Route::get('posts/{post}', [PostController::class, 'show']);
  });
  ```

### 2. Saving a Form with Every Locale

The `locale` payload key fills several locales at once when it is fillable.

```php title="app/Http/Controllers/Admin/PostController.php"
public function store(Request $request)
{
    $data = $request->validate([
        'published' => 'boolean',
        'locale' => 'required|array',
        'locale.en.title' => 'required|string',
        'locale.ar.title' => 'nullable|string',
        'locale.*.content' => 'nullable|string',
    ]);

    $post = Post::create($data);   // one transaction for the post and its translations

    return redirect()->route('admin.posts.edit', $post);
}
```

### 3. A Translation Dashboard

List what still needs translating, per locale.

```php
$locale = 'ar';

$pending = Post::whereTranslationProgress($locale, '<', 100)->get()
    ->map(function (Post $post) use ($locale) {
        return [
            'id' => $post->id,
            'title' => $post->getTranslation('title', 'en'),
            'missing' => $post->missingTranslations($locale),
            'progress' => $post->translationProgress($locale),
        ];
    });
```

### 4. A JSON Catalog

Products with typed translated fields in JSON columns.

```php title="app/Models/Product.php"
class Product extends Model implements JsonTranslatable
{
    use Translatable;

    public function translatable(): array
    {
        return [
            'name' => ['type' => 'string', 'required' => true],
            'features' => ['type' => 'array'],
        ];
    }
}
```

```php
$product = new Product;
$product->setTranslation('features', 'en', ['Waterproof', 'Bluetooth'])->save();

$product->getTranslation('features', 'en');   // ['Waterproof', 'Bluetooth']
Product::whereTranslationLike('name', 'Phone%', 'en')->get();
```

### 5. Moving a Model from Table to Columns

```bash title="Terminal" no-line-numbers
php artisan translatable:make-schema 'App\Models\Post' --to=columns --locales=en,ar
php artisan migrate
php artisan translatable:migrate 'App\Models\Post' --from=table --to=columns --apply
php artisan translatable:verify 'App\Models\Post' --from=table --to=columns
```

Then add `implements ColumnsTranslatable` to the model and deploy.
