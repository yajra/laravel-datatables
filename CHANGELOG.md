# Laravel DataTables CHANGELOG

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

### [Unreleased]

### [v10.4.3] - 2023-06-07

- Fix: Prevent the filteredCount() query if no filter is applied to the initial query #3007

### [v10.4.2] - 2023-05-31

- Fix return type for setTransformer() and setSerializer() #3003

### [v10.4.1] - 2023-05-27

- fix: Error when setting language config for "editor" #2983

### [v10.4.0] - 2023-03-28

- feat: Allow any callable in ->addColumn() #2977
- fix: #2976

### [v10.3.1] - 2023-02-20

- fix: Fix anonymous resource collection data formatting #2944
- fix: phpunit 10 deprecation #2955
- fix: bump orchestra/testbench to 8 #2949

### [v10.3.0] - 2023-02-07

- Add Laravel 10 compatibility #2948

### [v10.2.3] - 2023-01-18

- fix: Custom Order on eager loaded relationships was not working
- fix #2905

### [v10.2.2] - 2023-01-11

- fix: prevent deprecation errors in php 8.1+ #2931
- fixes #2930

### [v10.2.1] - 2022-12-07

- fix: case insensitive starts with search #2917 #2916

### [v10.2.0] - 2022-11-03

- PHP 8.1 Depreciation Fix #2877
- Methods pointing to the "uncustomizable" classes. #2861

### [v10.1.6] - 2022-10-10

- Fix anonymous resource collection #2870
- Fix #2827
- Add stale workflow

### [v10.1.5] - 2022-10-06

- Fix with method error with static analysis #2865

### [v10.1.4] - 2022-09-27

- Fixed the search column for same table relations #2856

### [v10.1.3] - 2022-09-20

- Fix relation key name for BelongsToMany #2850

### [v10.1.2] - 2022-07-12

- Fix HasOneThrough #2818

### [v10.1.1] - 2022-06-24

- Fix null recordsFiltered on empty collection #2806
- Fix #2793

### [v10.1.0] - 2022-06-21

- Add support for dependency injection when using closure. #2800

### [v10.0.8] - 2022-06-21

- Make canCreate at QueryDataTable accept QueryBuilder only #2798

### [v10.0.7] - 2022-05-23

- Fix create eloquent datatable from relation #2789

### [v10.0.6] - 2022-05-18

- Added null parameter type as allowed to handle default Action column from laravel-datatables-html #2787

### [v10.0.5] - 2022-05-17

- Fix Return value must be of type int, string returned.

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

[Unreleased]: https://github.com/yajra/laravel-datatables/compare/v10.3.1...10.x
[v10.3.1]: https://github.com/yajra/laravel-datatables/compare/v10.3.1...v10.3.0
[v10.3.1]: https://github.com/yajra/laravel-datatables/compare/v10.3.1...v10.3.0
[v10.3.0]: https://github.com/yajra/laravel-datatables/compare/v10.3.0...v10.2.3
[v10.2.3]: https://github.com/yajra/laravel-datatables/compare/v10.2.3...v10.2.2
[v10.2.2]: https://github.com/yajra/laravel-datatables/compare/v10.2.2...v10.2.1
[v10.2.1]: https://github.com/yajra/laravel-datatables/compare/v10.2.1...v10.2.0
[v10.2.0]: https://github.com/yajra/laravel-datatables/compare/v10.2.0...v10.1.6
[v10.1.6]: https://github.com/yajra/laravel-datatables/compare/v10.1.6...v10.1.5
[v10.1.5]: https://github.com/yajra/laravel-datatables/compare/v10.1.5...v10.1.4
[v10.1.4]: https://github.com/yajra/laravel-datatables/compare/v10.1.4...v10.1.3
[v10.1.3]: https://github.com/yajra/laravel-datatables/compare/v10.1.3...v10.1.2
[v10.1.2]: https://github.com/yajra/laravel-datatables/compare/v10.1.2...v10.1.1
[v10.1.1]: https://github.com/yajra/laravel-datatables/compare/v10.1.1...v10.1.0
[v10.1.0]: https://github.com/yajra/laravel-datatables/compare/v10.1.0...v10.0.8
[v10.0.8]: https://github.com/yajra/laravel-datatables/compare/v10.0.8...v10.0.7
[v10.0.7]: https://github.com/yajra/laravel-datatables/compare/v10.0.7...v10.0.6
[v10.0.6]: https://github.com/yajra/laravel-datatables/compare/v10.0.6...v10.0.5
[v10.0.5]: https://github.com/yajra/laravel-datatables/compare/v10.0.5...v10.0.4
[v10.0.4]: https://github.com/yajra/laravel-datatables/compare/v10.0.4...v10.0.3
[v10.0.3]: https://github.com/yajra/laravel-datatables/compare/v10.0.3...v10.0.2
[v10.0.2]: https://github.com/yajra/laravel-datatables/compare/v10.0.2...v10.0.1
[v10.0.1]: https://github.com/yajra/laravel-datatables/compare/v10.0.1...v10.0.0
[v10.0.0]: https://github.com/yajra/laravel-datatables/compare/v10.0.0...10.x
