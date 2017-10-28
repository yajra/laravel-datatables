# jQuery DataTables API for Laravel 4|5

[![Join the chat at https://gitter.im/yajra/laravel-datatables](https://badges.gitter.im/yajra/laravel-datatables.svg)](https://gitter.im/yajra/laravel-datatables?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Donate](https://img.shields.io/badge/donate-paypal-blue.svg)](https://www.paypal.me/yajra)
[![Donate](https://img.shields.io/badge/donate-patreon-blue.svg)](https://www.patreon.com/bePatron?u=4521203)

[![Laravel 4.2|5.x](https://img.shields.io/badge/Laravel-4.2|5.x-orange.svg)](http://laravel.com)
[![Latest Stable Version](https://img.shields.io/packagist/v/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.svg?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yajra/laravel-datatables/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yajra/laravel-datatables/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://img.shields.io/github/license/mashape/apistatus.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

This package is created to handle [server-side](https://www.datatables.net/manual/server-side) works of [DataTables](http://datatables.net) jQuery Plugin via [AJAX option](https://datatables.net/reference/option/ajax) by using Eloquent ORM, Fluent Query Builder or Collection.

```php
return datatables()->of(User::query())->toJson();
return datatables()->of(DB::table('users'))->toJson();
return datatables()->of(User::all())->toJson();

return datatables()->eloquent(User::query())->toJson();
return datatables()->queryBuilder(DB::table('users'))->toJson();
return datatables()->collection(User::all())->toJson();

return datatables(User::query())->toJson();
return datatables(DB::table('users'))->toJson();
return datatables(User::all())->toJson();
```

## Requirements
- [PHP >= 7.0](http://php.net/)
- [Laravel 5.4|5.5](https://github.com/laravel/framework)
- [jQuery DataTables v1.10.x](http://datatables.net/)

## Documentations
- [Laravel DataTables Documentation](http://yajrabox.com/docs/laravel-datatables)
- [Laravel DataTables API](http://yajra.github.io/laravel-datatables/api/)
- [Laravel 5.0 - 5.3 Demo Application](http://datatables.yajrabox.com)
- [Laravel 5.4 Demo Application](http://dt54.yajrabox.com)

## Laravel Version Compatibility

 Laravel  | Package
:---------|:----------
 4.2.x    | 3.x
 5.0.x    | 6.x
 5.1.x    | 6.x
 5.2.x    | 6.x
 5.3.x    | 6.x
 5.4.x    | 7.x, 8.x
 5.5.x    | 8.x

## DataTables 8.x Upgrade Guide
There are breaking changes since DataTables v8.x.
If you are upgrading from v7.x to v8.x, please see [upgrade guide](https://yajrabox.com/docs/laravel-datatables/master/upgrade).

## Quick Installation
```bash
$ composer require yajra/laravel-datatables-oracle:"~8.0"
```

#### Service Provider & Facade (Optional on Laravel 5.5)
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
$ php artisan vendor:publish --provider=Yajra\DataTables\DataTablesServiceProvider
```

And that's it! Start building out some awesome DataTables!

## Debugging Mode
To enable debugging mode, just set `APP_DEBUG=true` and the package will include the queries and inputs used when processing the table.

**IMPORTANT:** Please make sure that APP_DEBUG is set to false when your app is on production.

## PHP ARTISAN SERVE BUG
Please avoid using `php artisan serve` when developing with the package. 
There are known bugs when using this where Laravel randomly returns a redirect and 401 (Unauthorized) if the route requires authentication and a 404 NotFoundHttpException on valid routes.

It is advise to use [Homestead](https://laravel.com/docs/5.4/homestead) or [Valet](https://laravel.com/docs/5.4/valet) when working with the package.

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

## Buy me a coffee
<a href='https://pledgie.com/campaigns/29515'><img alt='Click here to lend your support to: Laravel DataTables and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/29515.png?skin_name=chrome' border='0' ></a>
<a href='https://www.patreon.com/bePatron?u=4521203'><img alt='Become a Patron' src='https://s3.amazonaws.com/patreon_public_assets/toolbox/patreon.png' border='0' width='200px' ></a>