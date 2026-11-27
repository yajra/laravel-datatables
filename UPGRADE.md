# UPGRADE GUIDE

## Searching nested Eloquent relationships

For automatic search on a column such as `user.roles.role`, declare the full relationship path on the root Eloquent query:

```php
use App\Models\Post;
use Yajra\DataTables\Facades\DataTables;

return DataTables::eloquent(Post::with('user.roles'))->toJson();
```

Loading `roles` only inside the `user` eager-load callback still works for display. Automatic search checks the paths declared on the root query and can produce an unknown-column error for the callback-only path.

To keep the callback-based eager load, register a server-side filter for the exact searchable column name:

```php
$query = Post::with(['user' => fn ($user) => $user->with('roles')]);

return DataTables::eloquent($query)
    ->filterColumn('user.roles.role', function ($posts, string $keyword) {
        $posts->whereHas('user.roles', fn ($roles) => $roles->where('role', 'like', "%{$keyword}%"));
    })
    ->toJson();
```

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
