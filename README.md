# jQuery DataTables API for Laravel 4|5|6|7|8|9|10

[![Join the chat at https://gitter.im/yajra/laravel-datatables](https://badges.gitter.im/yajra/laravel-datatables.svg)](https://gitter.im/yajra/laravel-datatables?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/yajra)
[![Donate](https://img.shields.io/badge/donate-patreon-blue.svg)](https://www.patreon.com/bePatron?u=4521203)

[![Laravel 4.2|5.x|6|7|8|9|10](https://img.shields.io/badge/Laravel-4.2|5.x|6|7|8|9|10-orange.svg)](http://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Continuous Integration](https://github.com/yajra/laravel-datatables/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/yajra/laravel-datatables/actions/workflows/continuous-integration.yml)
[![Static Analysis](https://github.com/yajra/laravel-datatables/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/yajra/laravel-datatables/actions/workflows/static-analysis.yml)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://img.shields.io/github/license/mashape/apistatus.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

Laravel package for handling [server-side](https://www.datatables.net/manual/server-side) works of [DataTables](http://datatables.net) jQuery Plugin via [AJAX option](https://datatables.net/reference/option/ajax) by using Eloquent ORM, Fluent Query Builder or Collection.

```php
return datatables()->eloquent(User::query())->toJson();
return datatables()->query(DB::table('users'))->toJson();
return datatables()->collection(User::all())->toJson();

return datatables(User::query())->toJson();
return datatables(DB::table('users'))->toJson();
return datatables(User::all())->toJson();
```

## Sponsors

<a href="https://editor.datatables.net?utm_source=laravel-datatables&utm_medium=github_readme&utm_campaign=logo">
    <img src="http://datatables.net/media/images/logo.png" alt="DataTables" height="64">
</a>

<a href="https://jb.gg/OpenSourceSupport">
    <img src="https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.png" alt="JetBrains.com" height="128">
</a>

<a href="https://blackfire.io/docs/introduction?utm_source=laravel-datatables&utm_medium=github_readme&utm_campaign=logo">
    <img src="https://i.imgur.com/zR8rsqk.png" alt="Blackfire.io" height="64">
</a>



## Requirements
- [PHP >= 8.0.2](http://php.net/)
- [Laravel Framework](https://github.com/laravel/framework)
- [jQuery DataTables v1.10.x](http://datatables.net/)

## Documentations

- [Github Docs](https://github.com/yajra/laravel-datatables-docs)
- [Laravel DataTables Quick Starter](https://yajrabox.com/docs/laravel-datatables/master/quick-starter)
- [Laravel DataTables Documentation](https://yajrabox.com/docs/laravel-datatables)
- [Laravel 5.0 - 5.3 Demo Application](https://datatables.yajrabox.com)

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
| 6.x.x   | 9.x      |
| 7.x.x   | 9.x      |
| 8.x.x   | 9.x      |
| 9.x.x   | 10.x     |
| 10.x.x  | 10.x     |

## Quick Installation

```bash
composer require yajra/laravel-datatables-oracle:"^10.0"
```

#### Service Provider & Facade (Optional on Laravel 5.5+)

Register provider and facade on your `config/app.php` file.
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

**IMPORTANT:** Please make sure that APP_DEBUG is set to false when your app is on production.

## PHP ARTISAN SERVE BUG

Please avoid using `php artisan serve` when developing with the package.
There are known bugs when using this where Laravel randomly returns a redirect and 401 (Unauthorized) if the route requires authentication and a 404 NotFoundHttpException on valid routes.

It is advised to use [Homestead](https://laravel.com/docs/5.4/homestead) or [Valet](https://laravel.com/docs/5.4/valet) when working with the package.

## Contributing

Please see [CONTRIBUTING](https://github.com/yajra/laravel-datatables/blob/master/.github/CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [aqangeles@gmail.com](mailto:aqangeles@gmail.com) instead of using the issue tracker.

## Credits

- [Arjay Angeles](https://github.com/yajra)
- [bllim/laravel4-datatables-package](https://github.com/bllim/laravel4-datatables-package)
- [All Contributors](https://github.com/yajra/laravel-datatables/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/yajra/laravel-datatables/blob/master/LICENSE.md) for more information.
