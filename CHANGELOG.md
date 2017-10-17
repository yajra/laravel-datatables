# Laravel DataTables CHANGELOG

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

### [Unreleased]

### [v8.1.1] - 2017-10-17
- Fix docs API link. [#1438], credits to [@dextermb](https://github.com/dextermb).
- Fix merging config. [#1444], credits to [@ElfSundae](https://github.com/ElfSundae).
- Fix return type. [#1446], credits to [@gabriel-caruso](https://github.com/gabriel-caruso).
- Remove unused provides() from service provider. [#1445], credits to [@ElfSundae](https://github.com/ElfSundae).

### [v8.1.0] - 2017-10-08
- Add addColumns() to EloquentDataTable. [#1416], credits to [@ElfSundae](https://github.com/ElfSundae).

### [v8.0.3] - 2017-09-12
- Fix compatibility with Lumen. [#1382]
- Fix [#1377].

### [v8.0.2] - 2017-09-06
- Remove void return type. 
- Fix [#1367], [#1368].

### [v8.0.1] - 2017-08-31
- Do not resolve column if relation is not eager loaded. [#1355]
- Fix [#1353], sort/search not working when using join statements.
- Add tests for join statements.

### [v8.0.0] - 2017-08-31
### ADDED
- Add support for Laravel 5.5.
- Package auto-discovery implemented.
- Add the raw data to model key when compiling views when using addColumn and editColumn.
- Make multi-term search configurable.
- Source code clean-up, refactoring and type-hinting.
- Improved scrutinizer code quality score from 6 to ~9 pts.
- On the fly support for `SoftDeletes`. No need to use `withTrashed` and `onlyTrashed`.
- Add `getQuery` api to get the query used by dataTable.
- Add `getFilteredQuery` api to get the prepared (filtered, ordered & paginated) query.
- Add `Arrayable` and `Jsonable` interface for a more Laravel like response.
```php
use Yajra\DataTables\Facades\DataTables;

return DataTables::eloquent(User::query())->toJson();
return DataTables::eloquent(User::query())->toArray();
```
- Introducing a new OOP / intuitive syntax.
```php
// using DataTables Factory
use Yajra\DataTables\DataTables;

return DataTables::of(User::query())->toJson();
return (new DataTables)->eloquent(User::query())->toJson();
return (new DataTables)->queryBuilder(DB::table('users'))->toJson();
return (new DataTables)->collection(User::all())->toJson();

// using DataTable class directly
use Yajra\DataTables\EloquentDataTable;
return (new EloquentDataTable(User::query())->toJson();

use Yajra\DataTables\QueryDataTable;
return (new QueryDataTable(DB::table('users'))->toJson();

use Yajra\DataTables\CollectionDataTable;
return (new CollectionDataTable(User::all())->toJson();
```
- Add `datatables()` function helper.

### CHANGED
- Namespace changed from `Yajra\Datatables` to `Yajra\DataTables`.
- Rename `Datatables` to `DataTables` class.
- Rename Facade from `Datatables` to `DataTables` class.
- Preserve `Eloquent\Builder` when overriding the default ordering of dataTables when using `EloquentEngine`.
- Preserve `Eloquent\Builder` when using filterColumn api. Allows us to use model scope and any eloquent magics.
- Fractal integration extracted to own plugin [laravel-datatables-fractal].
- Raw output are always passed on transformer instance.
- Object response is now the default output `public function make($mDataSupport = true)`.

### REMOVED
- Remove `filterColumn` api magic query method in favor of closure.
- Remove support on older `snake_case` methods.
- Remove silly implementation of proxying query builder calls via magic method. 
- Removed unused methods.
- Remove `withTrashed` and `onlyTrashed` api.

### FIXED
- How to get full used query ? [#1068]
- Is there a way to build the query (with filtering and sorting) but without execute it? [#1234] 
- Fix orderColumn api where related tables are not joined. 
- Fix nested with relation search and sort function.

[Unreleased]: https://github.com/yajra/laravel-datatables/compare/v8.1.1...8.0
[v8.1.1]: https://github.com/yajra/laravel-datatables/compare/v8.1.1...v8.1.0
[v8.1.0]: https://github.com/yajra/laravel-datatables/compare/v8.1.0...v8.0.3
[v8.0.3]: https://github.com/yajra/laravel-datatables/compare/v8.0.3...v8.0.2
[v8.0.2]: https://github.com/yajra/laravel-datatables/compare/v8.0.2...v8.0.1
[v8.0.1]: https://github.com/yajra/laravel-datatables/compare/v8.0.1...v8.0.0
[v8.0.0]: https://github.com/yajra/laravel-datatables/compare/v7.10.1...v8.0.0

[#1438]: https://github.com/yajra/laravel-datatables/pull/1438
[#1444]: https://github.com/yajra/laravel-datatables/pull/1444
[#1446]: https://github.com/yajra/laravel-datatables/pull/1446
[#1445]: https://github.com/yajra/laravel-datatables/pull/1445
[#1416]: https://github.com/yajra/laravel-datatables/pull/1416
[#1382]: https://github.com/yajra/laravel-datatables/pull/1382
[#1368]: https://github.com/yajra/laravel-datatables/pull/1368
[#1355]: https://github.com/yajra/laravel-datatables/pull/1355

[#1068]: https://github.com/yajra/laravel-datatables/issues/1068
[#1234]: https://github.com/yajra/laravel-datatables/issues/1234
[#1353]: https://github.com/yajra/laravel-datatables/issues/1353
[#1367]: https://github.com/yajra/laravel-datatables/issues/1367
[#1377]: https://github.com/yajra/laravel-datatables/issues/1377

[laravel-datatables-fractal]: https://github.com/yajra/laravel-datatables-fractal
