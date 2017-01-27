## Datatables Package for Laravel 4|5

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

## Change Log

### TODO
- Fix IE compatibility by using POST method when exporting/printing. #826
- Enhance/Fix nested relations support. #789
- Export Selected Rows Datatables Service Provider. #829 #850

### v7.0.0 - 01-27-2017
- Features are split across packages. #832
    - Buttons service approach extracted to own plugin.
        - Add fluent way to send variables to DataTable class. #845
    - Html builder extracted to own plugin.
- DataTable Engines are now pluggable. #544
- Added option to order by nulls last. #794
- Escape all columns by default for XSS protection. Fix #909
- Add rawColumns method for unescaped columns. https://github.com/yajra/laravel-datatables/commit/81adef8555195795189853f91e326dd056e40bb0
