# jQuery DataTables API for Laravel

[![Join the chat at https://gitter.im/yajra/laravel-datatables](https://badges.gitter.im/yajra/laravel-datatables.svg)](https://gitter.im/yajra/laravel-datatables?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/yajra)
[![Donate](https://img.shields.io/badge/donate-patreon-blue.svg)](https://www.patreon.com/bePatron?u=4521203)

[![Laravel 12](https://img.shields.io/badge/Laravel-12-orange.svg)](http://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Continuous Integration](https://github.com/yajra/laravel-datatables/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/yajra/laravel-datatables/actions/workflows/continuous-integration.yml)
[![Static Analysis](https://github.com/yajra/laravel-datatables/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/yajra/laravel-datatables/actions/workflows/static-analysis.yml)

[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/d/total.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
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
                <img src="https://www.npmjs.com/npm-avatar/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdmF0YXJVUkwiOiJodHRwczovL3MuZ3JhdmF0YXIuY29tL2F2YXRhci9lZDQwMmM1NjY2YjJlNjUxMTIyOWE4ZjM0NDdkNWMzYT9zaXplPTEwMCZkZWZhdWx0PXJldHJvIn0.aWU-snChAWu9abJV3dtBo-iy-2v_7JAxXUN1UHL_pDQ" height="50" alt="DataTables Logo">
            </td>
            <td>A big thank you to <a href="https://editor.datatables.net">DataTables</a> for supporting this project with a free DataTables Editor license.</td>
        </tr>
    </body>
</table>

<table>
    <body>
        <tr>
            <td>
                <img height="50" src="https://resources.jetbrains.com/storage/products/company/brand/logos/jetbrains.png" alt="JetBrains logo.">
            </td>
            <td>A big thank you to <a href="https://jb.gg/OpenSource">JetBrains</a> for supporting this project with free open-source licenses of their IDEs.</td>
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
- [PHP >= 8.2](http://php.net/)
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

## Quick Installation

### Option 1: Install all DataTables libraries

```bash
composer require yajra/laravel-datatables:"^12"
```

### Option 2: Install only this library

```bash
composer require yajra/laravel-datatables-oracle:"^12"
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
