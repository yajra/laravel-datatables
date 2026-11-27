# UPGRADE GUIDE

## Searching nested Eloquent relationships

For automatic search on a column such as `user.roles.role`, declare the full relationship path on the root Eloquent query:

```php
use App\Models\Post;
use Yajra\DataTables\Facades\DataTables;

return DataTables::eloquent(Post::with('user.roles')->select('posts.*'))->toJson();
```

Selecting `posts.*` preserves the post's ID when per-column filtering joins a table that also has an `id` column.

Loading `roles` only inside the `user` eager-load callback still works for display. Automatic search only traverses relationships declared on the root query. In this example, a role search returns no matching posts until you declare `user.roles` on the root or register a custom filter.

To keep the callback-based eager load, register a server-side filter for the exact searchable column name:

```php
$query = Post::with(['user' => fn ($user) => $user->with('roles')]);

return DataTables::eloquent($query)
    ->filterColumn('user.roles.role', function ($posts, string $keyword) {
        $posts->whereHas('user.roles', fn ($roles) => $roles->where('role', 'like', "%{$keyword}%"));
    })
    ->toJson();
```

## Migrating custom Eloquent engines

If your engine extends `EloquentDataTable` and overrides `compileQuerySearch()`, delegate with `parent::compileQuerySearch($query, $column, $keyword, $boolean)`. Pass the full client column name and remove the former `nested:` argument. The parent validates the relationship path against eager loads on the root query before traversing it.

Replace calls to the removed `isMorphRelation()` helper with the parent search implementation. For type-specific filtering, use Eloquent’s `whereHasMorph()` with a server-defined relationship name and model classes inside a `filterColumn()` callback.

## Upgrading from v11.x to v12.x

- See PR https://github.com/yajra/laravel-datatables/pull/3216

## Upgrading from v9.x to v10.x

- `ApiResourceDataTable` support dropped, use `CollectionDataTable` instead.
- `queryBuilder()` deprecated method removed, use `query()` instead.
- Methods signature were updated to PHP8 syntax, adjust as needed if you extended the package.

## Upgrading from v8.x to v9.x

No breaking changes with only a bump on php version requirements.

## Upgrading from v7.x to v8.x

There are breaking changes since DataTables v8.x. If you are upgrading from v7.x to v8.x, please see [upgrade guide](https://yajrabox.com/docs/laravel-datatables/master/upgrade).

## Upgrading from v6.x to v7.x
  - composer require yajra/laravel-datatables-oracle 
  - composer require yajra/laravel-datatables-buttons
  - php artisan vendor:publish --tag=datatables --force
  - php artisan vendor:publish --tag=datatables-buttons --force

## Upgrading from v5.x to v6.x
  - Change all occurrences of `yajra\Datatables` to `Yajra\Datatables`. (Use Sublime's find and replace all for faster update). 
  - Remove `Datatables` facade registration.
  - Temporarily comment out `Yajra\Datatables\DatatablesServiceProvider`.
  - Update package version on your composer.json and use `yajra/laravel-datatables-oracle: ~6.0`
  - Uncomment the provider `Yajra\Datatables\DatatablesServiceProvider`. 
