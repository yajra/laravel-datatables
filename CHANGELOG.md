## Datatables Package for Laravel 4|5

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

## Change Log

### v8.0-dev UNRELEASED
### ADDED
- Add support for Laravel 5.5.
- Package auto-discovery implemented.
- Add method to get the query used by dataTable.
- Add the raw data to model key when compiling views when using addColumn and editColumn.

### CHANGED
- Preserve `Eloquent\Builder` when overriding the default ordering of dataTables when using `EloquentEngine`.

### REMOVED
- Remove filterColumn api magic query method in favor of closure.
- Remove support on older snake_case methods.
- Remove silly implementation of proxying query builder calls via magic method. 

### FIXED
- Fix #1068.
- Fix orderColumn api where related tables are not joined. 
