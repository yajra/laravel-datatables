# Datatables Package for Laravel 4|5

[![Laravel 4.2|5.0|5.1|5.2](https://img.shields.io/badge/Laravel-4.2|5.0|5.1|5.2-orange.svg)](http://laravel.com)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.svg?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yajra/laravel-datatables/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yajra/laravel-datatables/?branch=master)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

This package is created to handle [server-side](https://www.datatables.net/manual/server-side) works of [DataTables](http://datatables.net) jQuery Plugin via [AJAX option](https://datatables.net/reference/option/ajax) by using Eloquent ORM, Fluent Query Builder or Collection.

## Feature Overview
- Supports the following data source
    - **Eloquent ORM**
    - **Fluent Query Builder**
    - **Collection** [available on v5.x and later]
- [DataTable Service Implementation (v6.x)](https://github.com/yajra/laravel-datatables/blob/6.0/CHANGELOG.md#v600---datatable-service-implementation). 
- Adding or editing content of columns and removing columns
- Modify column values via Blade Template Engine or by using Closure
- Works with **ALL the DATABASE** supported by Laravel
- Works with **Oracle Database** using [Laravel-OCI8](https://github.com/yajra/laravel-oci8) package
- Works with [DataTables](http://datatables.net) v1.10++.
    - **Note:** DT Legacy code is not supported on v5.x
- Works with [DataTables](http://datatables.net) v1.9 and v1.10 legacy code.
    - **Note:** Use [v4.x](https://github.com/yajra/laravel-datatables-oracle/tree/v4.3.2) for Laravel 5 and [v3.x](https://github.com/yajra/laravel-datatables-oracle/tree/L4) for Laravel 4
- Extended column filtering via [`filterColumn`](http://yajra.github.io/laravel-datatables/api/source-class-yajra.Datatables.Engines.BaseEngine.html#489-503) API.
- Extended column ordering via [`orderColumn`](http://yajra.github.io/laravel-datatables/api/source-class-yajra.Datatables.Engines.BaseEngine.html#505-519) API.
- Extended Query Builder functionality allowing you to filter using Datatables class directly.
- Decorate your data output using [`league\fractal`](https://github.com/thephpleague/fractal) Transformer with Serializer support.
- Works with Laravel Dependency Injection and IoC Container.
- Provides a [DataTable Html Builder](http://datatables.yajrabox.com/html) to help you use the package with less code.
- Provides XSS filtering function to optionally escape all or specified column values using `escapeColumns('*'\['column'])` method.
- Provides Query Logging when application is in debug state. 
    **Important: Make sure that debug is set to false when your code is in production**
- Easily attach a resource on json response via `->with()` method.
- Built-in support for exporting to CSV, EXCEL and PDF using [Laravel-Excel](https://github.com/Maatwebsite/Laravel-Excel).
- Built-in printer friendly view or create your own by overriding `printPreview()` method.
- Provides an artisan command for generating a DataTable service and scope.
- See [change logs](https://github.com/yajra/laravel-datatables/blob/6.0/CHANGELOG.md) for more details.
    
## Requirements:
- PHP 5.5.9 or later.
- Laravel 5.0 or later.
- [DataTables jQuery Plugin](http://datatables.net/) v1.10.x

## Laravel 4.2 & DataTables v1.9.x Users
Most of the latest updates/features are not available on these versions. Please check [L4 Branch](https://github.com/yajra/laravel-datatables/tree/L4) and [L5 DT1.9](https://github.com/yajra/laravel-datatables/tree/L5-DT1.9) for old documentations of its features.

## Buy me a beer
<a href='https://pledgie.com/campaigns/29515'><img alt='Click here to lend your support to: Laravel Datatables and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/29515.png?skin_name=chrome' border='0' ></a>

## Documentations
- You will find user friendly and updated documentation in the wiki here: [Laravel Datatables Wiki](https://github.com/yajra/laravel-datatables/wiki)
- You will find the API Documentation here: [Laravel Datatables API](http://yajra.github.io/laravel-datatables/api/)
- [Demo Application](http://datatables.yajrabox.com) is available for artisan's reference.

## Quick Installation
`composer require yajra/laravel-datatables-oracle:~6.0`

#### Service Provider
`Yajra\Datatables\DatatablesServiceProvider::class`

#### Facade
`Datatables` facade are automatically registered as an alias for `Yajra\Datatables\Datatables` class. 

#### Configuration and Assets
`$ php artisan vendor:publish --tag=datatables`

And that's it! Start building out some awesome DataTables!

## Upgrading from v5.x to v6.x
  - Change all occurrences of `yajra\Datatables` to `Yajra\Datatables`. (Use Sublime's find and replace all for faster update). 
  - Remove `Datatables` facade registration.
  - Temporarily comment out `Yajra\Datatables\DatatablesServiceProvider`.
  - Update package version on your composer.json and use `yajra/laravel-datatables-oracle: ~6.0`
  - Uncomment the provider `Yajra\Datatables\DatatablesServiceProvider`. 

## Contributing

Please see [CONTRIBUTING](https://github.com/yajra/laravel-datatables/blob/master/CONTRIBUTING.md) for details.

## Security

If you discover any security related issues, please email [aqangeles@gmail.com](mailto:aqangeles@gmail.com) instead of using the issue tracker.

## Credits

- This project is used to be a fork from [bllim/laravel4-datatables-package](https://github.com/bllim/laravel4-datatables-package).
- [All Contributors](https://github.com/yajra/laravel-datatables/graphs/contributors)

## License

The MIT License (MIT). Please see [License File](https://github.com/yajra/laravel-datatables/blob/master/LICENSE.md) for more information.
