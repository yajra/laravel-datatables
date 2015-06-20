## Datatables Package for Laravel 4|5

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

##Change Log

###v5.4.3
    - add column wrapper for postgres. Bugfix #82

###v5.4.2
    - Throws Exception when using DataTable's legacy code
    - Fixed CS - PSR2

###v5.4.1
    - Fixed Builder generateScript method.

###v5.4
    - Added Html Builder
    - Added magic methods to call enginges without the "using" word
    - Minor Bugfixes

###v5.3
    - Added scrutinizer
    - Code refactor/cleanup based on scrutinizers suggestions
    - Bugfix #75

###v5.2
    - Datatables can now be used via Laravel IOC container `app('datatables')`
    - Datatables Engine can now be used directly along with Laravel IOC
        - Available Engines:
            - Query Builder Engine. `app('datatables')->usingQueryBuilder($builder)->make()`
            - Eloquent Engine. `app('datatables')->usingEloquent($model)->make()`
            - Collection Engine. `app('datatables')->usingCollection($collection)->make()`
    - Datatables is now more testable and works with https://github.com/laracasts/integrated
    - Bugfix #56

###v5.1
    - Added filterColumn function to override default global search in each column
    - Datatables class extending Query Builder's functionality along with global search.
    - Restore queries on result when app is in debug mode
    - Added input on result when app is in debug mode
    - Force enable query log when app is in debug mode
    - Convert string search from preg_match to Str::contains to improve performance.
    - Added support for having clause queries
    - Added support for `league/fractal` for transforming data API output

###v5.0
    - Strictly for Laravel 5++
    - Drop support for DT1.9 and below
    - Strict implmentation of DT1.10 script pattern
    - Added support for Collection as data source

###v4.3.x
    - Latest stable version for Laravel 5 with support for DT1.9
    - Collection Engine not available

###v3.6.x
    - Latest stable version for Laravel 4
