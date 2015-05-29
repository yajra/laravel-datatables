# Datatables Package for Laravel 4|5

[![Build Status](https://img.shields.io/travis/yajra/laravel-datatables.svg)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Stable Version](https://img.shields.io/packagist/v/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://img.shields.io/packagist/dt/yajra/laravel-datatables-oracle.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](https://github.com/yajra/laravel-datatables/blob/master/LICENSE)
[![Support via Gratipay](https://img.shields.io/gratipay/yajra.svg)](https://gratipay.com/yajra/)
[![Flattr this git repo](http://api.flattr.com/button/flattr-badge-large.png)](https://flattr.com/submit/auto?user_id=yajra&url=https://github.com/yajra/laravel-datatables&title=laravel-datatables&language=PHP&tags=github&category=software)

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
- Works with Laravel Dependency Injection and IoC Container

## Documentations
- You will find user friendly and updated documentation in the wiki here: [Laravel Datatables Wiki](https://github.com/yajra/laravel-datatables/wiki)
- You will find the API Documentation here: [Laravel Datatables API](http://yajra.github.io/laravel-datatables/api/)

## Quick Installation
**Laravel 5:** `composer require yajra/laravel-datatables-oracle:~5.0`

**Laravel 4:** `composer require yajra/laravel-datatables-oracle:~3.0`

#### Service Provider
`yajra\Datatables\DatatablesServiceProvider`

#### Facade
`'Datatables'      => 'yajra\Datatables\Datatables',`

#### Configuration
**Laravel 5:** `$ php artisan vendor:publish`

**Laravel 4:** `$ php artisan config:publish yajra/laravel-datatables-oracle`


And that's it! Start building out some awesome DataTables!

## License

Licensed under the [MIT License](https://github.com/yajra/laravel-datatables/blob/master/LICENSE).
