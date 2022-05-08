# Laravel DataTables CHANGELOG

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

### [Unreleased]

### [v10.0.4] - 2022-05-08

- Fix accidental formatter issue on eloquent 
- Add formatColumn test for eloquent

### [v10.0.3] - 2022-05-08

- Additional fix & test for zero total records

### [v10.0.2] - 2022-05-08

- Fix set total & filtered records count https://github.com/yajra/laravel-datatables/pull/2778
- Fix set total & filtered records count
- Fix #1453 #1454 #2050 #2609
- Add feature test
- Deprecate `skipTotalRecords`, just use `setTotalRecords` directly.

### [v10.0.1] - 2022-05-08

- Code clean-up and several phpstan fixes

### [v10.0.0] - 2022-05-08

- Laravel DataTables v10.x to support Laravel 9.x
- Added PHPStan with max level static analysis
- Drop `queryBuilder()` method
- Drop support for `ApiResourceDataTable`
- PHP8 syntax / method signature changed

[Unreleased]: https://github.com/yajra/laravel-datatables/compare/v10.0.4...10.x
[v10.0.4]: https://github.com/yajra/laravel-datatables/compare/v10.0.4...v10.0.3
[v10.0.3]: https://github.com/yajra/laravel-datatables/compare/v10.0.3...v10.0.2
[v10.0.2]: https://github.com/yajra/laravel-datatables/compare/v10.0.2...v10.0.1
[v10.0.1]: https://github.com/yajra/laravel-datatables/compare/v10.0.1...v10.0.0
[v10.0.0]: https://github.com/yajra/laravel-datatables/compare/v10.0.0...10.x
