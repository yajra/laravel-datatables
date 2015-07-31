# Datatables Package for Laravel 4|5

[![Laravel 4.2|5.0|5.1](https://img.shields.io/badge/Laravel-4.2|5.0|5.1-orange.svg)](http://laravel.com)
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
- Adding or editing content of columns and removing columns
- Templating new or current columns via Blade Template Engine or by using Closure
- Works with **ALL the DATABASE** supported by Laravel
- Works with **Oracle Database** using [Laravel-OCI8](https://github.com/yajra/laravel-oci8) package
- Works with [DataTables](http://datatables.net) v1.10++.
    - **Note:** DT Legacy code is not supported on v5.x
- Works with [DataTables](http://datatables.net) v1.9 and v1.10 legacy code.
    - **Note:** Use [v4.x](https://github.com/yajra/laravel-datatables-oracle/tree/v4.3.2) for Laravel 5 and [v3.x](https://github.com/yajra/laravel-datatables-oracle/tree/L4) for Laravel 4
- Extended column filtering via [`filterColumn`](http://yajra.github.io/laravel-datatables/api/source-class-yajra.Datatables.Engines.BaseEngine.html#489-503) API.
- Extended column ordering via [`orderColumn`](http://yajra.github.io/laravel-datatables/api/source-class-yajra.Datatables.Engines.BaseEngine.html#505-519) API.
- Extended Query Builder functionality allowing you to filter using Datatables class directly.
- Decorate your data output using [`league\fractal`](https://github.com/thephpleague/fractal) Transformer.
- Works with Laravel Dependency Injection and IoC Container.
- Provides a [DataTable Html Builder](http://datatables.yajrabox.com/html) to help you use the package with less code.
- Provides XSS filtering function to optionally escape all or specified column values using `escapeColumns('*'\['column'])` method.

## Buy me a beer
<a href='https://pledgie.com/campaigns/29515'><img alt='Click here to lend your support to: Laravel Datatables and make a donation at pledgie.com !' src='https://pledgie.com/campaigns/29515.png?skin_name=chrome' border='0' ></a>

## Documentations
- You will find user friendly and updated documentation in the wiki here: [Laravel Datatables Wiki](https://github.com/yajra/laravel-datatables/wiki)
- You will find the API Documentation here: [Laravel Datatables API](http://yajra.github.io/laravel-datatables/api/)
- [Demo Application](http://datatables.yajrabox.com) is available for artisan's reference.

## Quick Installation
**Laravel 5:** `composer require yajra/laravel-datatables-oracle:~5.0`

**Laravel 4:** `composer require yajra/laravel-datatables-oracle:~3.0`

#### Service Provider
`yajra\Datatables\DatatablesServiceProvider`

#### Facade
**Laravel 4**
`'Datatables'      => 'yajra\Datatables\Facades\Datatables',`

**Laravel 5**
`'Datatables'      => 'yajra\Datatables\Datatables',`

**Laravel 5.1**
`'Datatables'      => yajra\Datatables\Datatables::class,`

#### Configuration
**Laravel 5:** `$ php artisan vendor:publish --provider="yajra\Datatables\DatatablesServiceProvider"`

**Laravel 4:** `$ php artisan config:publish yajra/laravel-datatables-oracle`


And that's it! Start building out some awesome DataTables!

## License

Licensed under the [MIT License](https://github.com/yajra/laravel-datatables/blob/master/LICENSE).

## Credits

- This project is used to be a fork from [bllim/laravel4-datatables-package](https://github.com/bllim/laravel4-datatables-package).
- Credits to all the contributors of this package
