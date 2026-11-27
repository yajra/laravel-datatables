# jQuery DataTables API for Laravel

[![Join the chat at https://gitter.im/yajra/laravel-datatables](https://badges.gitter.im/yajra/laravel-datatables.svg)](https://gitter.im/yajra/laravel-datatables?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/yajra)
[![Donate](https://img.shields.io/badge/donate-patreon-blue.svg)](https://www.patreon.com/bePatron?u=4521203)

[![Laravel 13](https://img.shields.io/badge/Laravel-13-orange.svg)](http://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Continuous Integration](https://github.com/yajra/laravel-datatables/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/yajra/laravel-datatables/actions/workflows/continuous-integration.yml)
[![Static Analysis](https://github.com/yajra/laravel-datatables/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/yajra/laravel-datatables/actions/workflows/static-analysis.yml)

[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/d/total.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Monthly Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/d/monthly)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://img.shields.io/github/license/mashape/apistatus.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

Laravel package for handling [server-side](https://www.datatables.net/manual/server-side) works of [DataTables](http://datatables.net) jQuery Plugin via [AJAX option](https://datatables.net/reference/option/ajax) by using Eloquent ORM, Fluent Query Builder or Collection.

```php
use Yajra\DataTables\Facades\DataTables;

return DataTables::eloquent(User::query())->toJson();
return DataTables::query(DB::table('users'))->toJson();
return DataTables::collection(User::all())->toJson();

return DataTables::make(User::query())->toJson();
return DataTables::make(DB::table('users'))->toJson();
return DataTables::make(User::all())->toJson();
```

## Sponsors

<table>
    <body>
        <tr>
            <td>
                <img src="https://datatables.net/media/images/nav-dt.png" height="50" alt="DataTables Logo">
            </td>
            <td>A big thank you to <a href="https://editor.datatables.net">DataTables</a> for supporting this project with a free DataTables Editor license.</td>
        </tr>
    </body>
</table>

<table>
    <body>
        <tr>
            <td><img src="https://i.imgur.com/zR8rsqk.png" height="50" alt="Blackfire.io Logo"></td>
            <td>A big thank you to <a href="https://blackfire.io/docs/introduction?utm_source=laravel-datatables&utm_medium=github_readme&utm_campaign=logo">Blackfire.io</a> for supporting this project with a free open-source license.</td>
        </tr>
    </body>
</table>

## Requirements
- [PHP >= 8.3](http://php.net/)
- [Laravel Framework](https://github.com/laravel/framework)
- [DataTables](http://datatables.net/)

## Documentations

- [Github Docs](https://github.com/yajra/laravel-datatables-docs)
- [Laravel DataTables Quick Starter](https://yajrabox.com/docs/laravel-datatables/master/quick-starter)
- [Laravel DataTables Documentation](https://yajrabox.com/docs/laravel-datatables)

## Laravel Version Compatibility

| Laravel | Package  |
|:--------|:---------|
| 4.2.x   | 3.x      |
| 5.0.x   | 6.x      |
| 5.1.x   | 6.x      |
| 5.2.x   | 6.x      |
| 5.3.x   | 6.x      |
| 5.4.x   | 7.x, 8.x |
| 5.5.x   | 8.x      |
| 5.6.x   | 8.x      |
| 5.7.x   | 8.x      |
| 5.8.x   | 9.x      |
| 6.x     | 9.x      |
| 7.x     | 9.x      |
| 8.x     | 9.x      |
| 9.x     | 10.x     |
| 10.x    | 10.x     |
| 11.x    | 11.x     |
| 12.x    | 12.x     |
| 13.x    | 13.x     |

## Quick Installation

### Option 1: Install all DataTables libraries

```bash
composer require yajra/laravel-datatables:"^13"
```

### Option 2: Install only this library

```bash
composer require yajra/laravel-datatables-oracle:"^13"
```

#### Service Provider & Facade (Optional on Laravel 5.5+)

Register the provider and facade on your `config/app.php` file.
```php
'providers' => [
    ...,
    Yajra\DataTables\DataTablesServiceProvider::class,
]

'aliases' => [
    ...,
    'DataTables' => Yajra\DataTables\Facades\DataTables::class,
]
```

#### Configuration (Optional)

```bash
php artisan vendor:publish --provider="Yajra\DataTables\DataTablesServiceProvider"
```

And that's it! Start building out some awesome DataTables!

## Debugging Mode

To enable debugging mode, just set `APP_DEBUG=true` and the package will include the queries and inputs used when processing the table.

> [!IMPORTANT]
> Please ensure that the `APP_DEBUG` config is set to false when your app is in production.

## Caching Query Results

The counts and the rows read by the query and Eloquent engines can be cached with the Laravel cache, until the tags the result depends on are flushed. Nothing is cached unless `cache()` is called:

```php
use Yajra\DataTables\Facades\DataTables;

// Orders of a team.
return DataTables::eloquent(Order::query()->where('team_id', $team->id)->with('customer'))
    ->cache(['orders', "team:{$team->id}"], ttl: 120)
    ->toJson();

// Late orders of every team.
return DataTables::eloquent(Order::query()->where('status', 'late'))
    ->cache('orders', ttl: 120)
    ->toJson();
```

Flush the tags when their data changes. A tag flushes every result that declared it: flushing `orders` refreshes both tables above, flushing `team:1` only refreshes the orders of team 1. Inside a transaction, the flush runs once it is committed and is discarded on rollback:

```php
// Flush the previous and the new team when an order moves.
Order::saved(fn (Order $order) => DataTables::flushCache(array_unique([
    "team:{$order->getOriginal('team_id')}",
    "team:{$order->team_id}",
])));

// Mass updates and raw SQL do not fire model events.
Order::query()->where('status', 'pending')->update(['status' => 'late']);
DataTables::flushCache('orders');

// Writing on another connection than the default one.
DB::connection('billing')->transaction(function () {
    Invoice::on('billing')->where('paid', false)->update(['status' => 'late']);
    DataTables::flushCache('invoices', connection: 'billing');
});
```

`flushCache()` throws a `CacheFlushException` when the cache store fails or keeps a flushed tag, since the previous results may still be served. To cache the counts only, often the slowest reads, and read the rows of every page from the database, pass `rows: false`:

```php
return DataTables::eloquent(Order::query())->cache('orders', ttl: 300, rows: false)->toJson();
```

Any cache store works, even without tag support. Keep in mind that:

- An entry is reused by the requests sending the same SQL and bindings to the same connection with the same tags. Keep authorization in the query, or after it, e.g. in `editColumn()`.
- Every table read by the query must be tagged and flushed: a change that is not flushed, e.g. a mass update or a write from another system, is served from the cache until the TTL expires.
- When the database itself returns different rows for the same SQL, e.g. with row level security or a per tenant `search_path`, segment the cache with `by: ['schema' => $schema]`. Do not cache queries using `NOW()`, `RAND()` or `CURRENT_USER`: pass such values as bindings.
- Eager loads, the queries of your callbacks, the reads inside a transaction, the locking reads, the reads routed to the primary (`useWritePdo()`, or after a write on a `sticky` connection), the queries with a custom fetch mode (`fetchUsing()`) and the queries with `afterQuery()` callbacks are never cached.
- Only cache the expensive reads: for a fast query, reading the cache can be slower than reading the database. Reads returning more than `datatables.cache.max_rows` rows (1000 by default), e.g. exports, are not stored.
- Rows cached before a migration are served without its new columns: flush the tags of the changed tables in the migration, or add the release to `datatables.cache.prefix`. Using the model class as tag, e.g. `cache(User::class)`, avoids typos between `cache()` and `flushCache()`.
- With an eventually consistent store, e.g. DynamoDB, whose reads are not strongly consistent, a read right after a flush may still see the flushed tag and serve the previous results, or `flushCache()` may throw although the deletion succeeded. Use a strongly consistent store, e.g. Redis, Memcached or a database, when the results must be fresh right after a flush.

The store, the default TTL, the write and row limits and a switch to bypass every cache are set in the `cache` section of `config/datatables.php`, or with the `DATATABLES_CACHE_*` environment variables. If the cache store fails while reading or storing, a warning is logged and the data is read from the database. When `APP_DEBUG` is enabled, the response includes the status of every cached read.

## PHP ARTISAN SERVE BUG

Please avoid using `php artisan serve` when developing the package.
There are known bugs when using this where Laravel randomly returns a redirect and 401 (Unauthorized) if the route requires authentication and a 404 NotFoundHttpException on valid routes.

It is advised to use [Homestead](https://laravel.com/docs/5.4/homestead) or [Valet](https://laravel.com/docs/5.4/valet) when working with the package.

## Contributing

Please see [CONTRIBUTING](https://github.com/yajra/laravel-datatables/blob/master/.github/CONTRIBUTING.md) for details.

## Security

If you discover any security-related issues, please email [aqangeles@gmail.com](mailto:aqangeles@gmail.com) instead of using the issue tracker.

## Credits

- [Arjay Angeles](https://github.com/yajra)
- [bllim/laravel4-datatables-package](https://github.com/bllim/laravel4-datatables-package)
- [All Contributors](https://github.com/yajra/laravel-datatables/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/yajra/laravel-datatables/blob/master/LICENSE.md) for more information.
