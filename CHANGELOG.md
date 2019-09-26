# Laravel DataTables CHANGELOG

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

### [Unreleased]

### [v9.6.1] - 2019-09-26

- Improve orderByNullsLast SQL generation. [#2191]
- Fix [#1822], [#1738].

### [v9.6.0] - 2019-09-04

- Fix deprecated helper functions, then add support for Laravel 6. [#2171], credits to [@lloricode]
- Fix [#2156].

### [v9.5.0] - 2019-08-31

- Add support for startsWithSearch filter. [#2163]
- Fix [#2161].

### [v9.4.1] - 2019-06-12

- Removal of redundant SoftDelete check. [#2103], credits to [@selecod]

### [v9.4.0] - 2019-06-06

- Allow column search on blacklisted columns with custom filter. [#2102], fix [#2091].
- Enable the dotted notation in the ->only() function. [#2084], credits to [@Arkhas]
- Add tests.

### [v9.3.0] - 2019-05-21

- Prevent malformed UTF-8 characters in debug mode. [#2088], credits to [@drsdre].
- Add the possibility to makeHidden() some attribute of a model. [#2085], credits to [@Arkhas].

### [v9.2.0] - 2019-05-09

- Enable the dotted notation in the ->removeColumn() function. [#2082], credits to [@Arkhas].
- Consider black listed columns on column search. [#2079], credits to [@apreiml].
- Using predefined offsets for API-driven server-side(ish) DataTables. [#2083], credits to [@Stokoe0990].

### [v9.1.1] - 2019-04-25

- Revert [#2051], fix [#2058]. [#2072].

### [v9.1.0] - 2019-04-24

#### FIXED

- Fix rendering column from blade file. [#2067], credits to [@lukchojnicki].
- Fix [#2045], [#2054], [#2024], [#1977], [#880], [#577], [#522], etc.

#### ADDED

- Add support for self join relationships. [#2051], credits to [@Morinohtar].

### [v9.0.1] - 2019-03-26

- Allow boolean values for column[i].searchable [#1813], credits to [@sgotre].

### [v9.0.0] - 2019-02-27

- Add support for Laravel 5.8 [#2002].
- Fix [#2001], [#2003].
- Drop support for lower version of dataTables.
- Bump to php ^7.1.3.
- Bump testbench to ^3.8.

### [v8.13.5] - 2019-02-13

- Keep select bindings option. [#1988], credits to [@royduin].
- Fix [#1983].

### [v8.13.4] - 2019-01-29

- Added optional merge of config raw columns to rawColumns method. [#1960], credits to [@Spodnet]

### [v8.13.3] - 2019-01-05

- Revert [#1942].
- Fix [#1951].

### [v8.13.2] - 2019-01-04

- Keep casted attributes. [#1942], credits to [@ridaamirini].
- Fix [#1747].

### [v8.13.1] - 2018-11-23

- Revert v8.12.0 changes.

### [v8.13.0] - 2018-11-23

- Only escape callable output of add and edit column. [#1852], credits to [@sharifzadesina]
- Fix adding of index column bug introduced by [#1852]. [#1915]
- Add tests for [#1852].

### [v8.12.0] - 2018-11-23

- Skipped, bad tagging!

### [v8.11.0] - 2018-11-20

- Use skipTotalRecords as it better describe what the function does. [#1912]
- Remove method `simplePagination` and use `skipTotalRecords` instead.

### [v8.10.0] - 2018-11-20

- Add simple pagination api. [#1911]
- Use `toJson()` on all tests api. [#1911]
- Use dedicated assertCount assertion. [#1903], credits to [@carusogabriel]

### [v8.9.2] - 2018-10-30

- Fix the default name of index column to follow DT syntax. [#1882], credits to [@sharifzadesina].

### [v8.9.1] - 2018-10-05

- DATATABLES_ERROR shouldn't be by default null [#1805] [#1811], credits to [@zeyad82].

### [v8.9.0] - 2018-10-05

- Added ability to pass an array of needed columns on response. [#1860], credits to [@ptuchik].

### [v8.8.0] - 2018-09-05

- Add support for Laravel 5.7
- Fix [#1824], [#1830]

### [v8.7.1] - 2018-07-06

- Add validation for order direction. [#1792]
- Prevents SQL injection on order direction.
- Fix phpunit configuration warning.

### [v8.7.0] - 2018-06-03

- Add withQuery api for query callback. [#1759]
- Revert [#1758] with callback implementation since its BC.

### [v8.6.1] - 2018-06-03

- Fix/Enhance with closure value implementation. [#1758]
- Use filteredQuery as callback parameter.
- Fix [#1752]

### [v8.6.0] - 2018-05-18

- Add support for manual setting of filtered count [#1743], credits to [@forgottencreature]
- Fix [#1516].

### [v8.5.2] - 2018-05-15

- Revert "[8.0] Classify join statements as a complex query." [#1741]
- Fix [#1739]

### [v8.5.1] - 2018-05-12

- Reset select bindings for count query [#1730], credits to [@fschalkwijk]
- Classify join statements as a complex query [#1737].
- Fix [#1600], [#1471].

### [v8.5.0] - 2018-05-10

- Support for Eloquent API Resources [#1702], credits to [@asahasrabuddhe].
- Fixes [#1515], [#1659], [#1351].

### [v8.4.4] - 2018-05-04

- Use array_key_exists instead of in_array + array_keys [#1719], credits to [@carusogabriel].
- Adds support to Laravel 5.6 on readme, [#1724], credits to [@nunomaduro]
- Fixed a bug for "undefined index" errors, [#1728], credits to [@redelschaap]

### [v8.4.3] - 2018-04-05

- [8.0] Fix ambiguous column 'deleted_at'. [#1688], credits to [@sskl].

### [v8.4.2] - 2018-03-29

- Check SoftDeletes on HasOne or BelongsTo relations [#1628], credits to [@drahosistvan].
- Add mention of Datatables Editor pkg to "suggests" [#1658], credits to [@drbyte].

### [v8.4.1] - 2018-02-16

- Change Log contract to LoggerInterface. [#1624], credits to [@LEI].
- Fix [#1626].

### [v8.4.0] - 2018-02-11

- Added Laravel 5.6 Support [#1609], credits to [@marcoocram]
- Fix [#1617]

### [v8.3.3] - 2018-01-11

- Fixes from PHPStan. [#1569], credits to [@carusogabriel].
- Enable no_useless_else. [#1554], credits to [@carusogabriel].
- Remove useless else statements. [#1553], credits to [@carusogabriel].
- Fix typo. [#1536], credits to [@Oussama-Tn].
- Test against PHP 7.2. [#1532], credits to [@carusogabriel].
- Update TestCase with PSR-2. [#1496], credits to [@gabriel-caruso].

### [v8.3.2] - 2017-11-02

- Fix datatables() helper and use singleton instance. [#1487], credits to [@ElfSundae].
- Styling phpdoc for facade. [#1489], credits to [@ElfSundae].
- Apply StyleCI fixes. [#1485], [#1483].
- Patch docs. [#1492]
- Add StyleCI integration. [#1484]

### [v8.3.1] - 2017-10-27

- Fix filtered records total when using filterColumn. [#1473], credits to [@wuwx](https://github.com/wuwx).
- Added Patreon Link. [#1476], credits to [@ChaosPower](https://github.com/ChaosPower).
- Fix missing periods. [#1478], credits to [@jiwom].
- Fix PHP Docs and minor array fixes. Remove unused import. [#1479], credits to [@jiwom].

### [v8.3.0] - 2017-10-26

**ADDED**

- `DataTables` factory class is now Macroable. [#1462]
- `query()` api added as a replacement for `queryBuilder()`. [#1462]

**CHANGED**

- Support for plugin engine methods. [#1462], credits to [@pimlie].
- `datatables.builders` config is now optional/redundant. [#1462]
- Deprecate `queryBuilder()` api and replaced with `query()`.

**FIXED**

- Support for custom engines (eg for mongodb) [#1294],

### [v8.2.0] - 2017-10-25

**FIXED**

- Fix changelog links. [#1449]
- Rename phpunit.xml and add composer script. [#1460], credits to [@pimlie].
- Fix exception/warning for PHP 7.2. [#1465], credits to [@CristianDeluxe](https://github.com/CristianDeluxe).
- Fix facade method annotations. [#1468], credits to [@Guja1501](https://github.com/Guja1501).
- Fix globalSearch not working for 0. [#1467], credits to [@lrtr](https://github.com/lrtr).

**ADDED/CHANGED**

- Make wildcard string a function parameter. [#1461], credits to [@pimlie].

### [v8.1.1] - 2017-10-17

- Fix docs API link. [#1438], credits to [@dextermb](https://github.com/dextermb).
- Fix merging config. [#1444], credits to [@ElfSundae].
- Fix return type. [#1446], credits to [@gabriel-caruso].
- Remove unused provides() from service provider. [#1445], credits to [@ElfSundae].

### [v8.1.0] - 2017-10-08

- Add addColumns() to EloquentDataTable. [#1416], credits to [@ElfSundae].

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

[Unreleased]: https://github.com/yajra/laravel-datatables/compare/v9.6.1...9.0
[v9.6.1]: https://github.com/yajra/laravel-datatables/compare/v9.6.0...v9.6.1
[v9.6.0]: https://github.com/yajra/laravel-datatables/compare/v9.5.0...v9.6.0
[v9.5.0]: https://github.com/yajra/laravel-datatables/compare/v9.4.1...v9.5.0
[v9.4.1]: https://github.com/yajra/laravel-datatables/compare/v9.4.0...v9.4.1
[v9.4.0]: https://github.com/yajra/laravel-datatables/compare/v9.3.0...v9.4.0
[v9.3.0]: https://github.com/yajra/laravel-datatables/compare/v9.2.0...v9.3.0
[v9.2.0]: https://github.com/yajra/laravel-datatables/compare/v9.1.1...v9.2.0
[v9.1.1]: https://github.com/yajra/laravel-datatables/compare/v9.1.0...v9.1.1
[v9.1.0]: https://github.com/yajra/laravel-datatables/compare/v9.0.1...v9.1.0
[v9.0.1]: https://github.com/yajra/laravel-datatables/compare/v9.0.0...v9.0.1
[v9.0.0]: https://github.com/yajra/laravel-datatables/compare/v8.13.5...v9.0.0
[v8.13.5]: https://github.com/yajra/laravel-datatables/compare/v8.13.4...v8.13.5
[v8.13.4]: https://github.com/yajra/laravel-datatables/compare/v8.13.3...v8.13.4
[v8.13.3]: https://github.com/yajra/laravel-datatables/compare/v8.13.2...v8.13.3
[v8.13.2]: https://github.com/yajra/laravel-datatables/compare/v8.13.1...v8.13.2
[v8.13.1]: https://github.com/yajra/laravel-datatables/compare/v8.13.0...v8.13.1
[v8.13.0]: https://github.com/yajra/laravel-datatables/compare/v8.11.0...v8.13.0
[v8.11.0]: https://github.com/yajra/laravel-datatables/compare/v8.10.0...v8.11.0
[v8.10.0]: https://github.com/yajra/laravel-datatables/compare/v8.9.2...v8.10.0
[v8.9.2]: https://github.com/yajra/laravel-datatables/compare/v8.9.1...v8.9.2
[v8.9.1]: https://github.com/yajra/laravel-datatables/compare/v8.9.0...v8.9.1
[v8.9.0]: https://github.com/yajra/laravel-datatables/compare/v8.8.0...v8.9.0
[v8.8.0]: https://github.com/yajra/laravel-datatables/compare/v8.7.1...v8.8.0
[v8.7.1]: https://github.com/yajra/laravel-datatables/compare/v8.7.0...v8.7.1
[v8.7.0]: https://github.com/yajra/laravel-datatables/compare/v8.6.1...v8.7.0
[v8.6.1]: https://github.com/yajra/laravel-datatables/compare/v8.6.0...v8.6.1
[v8.6.0]: https://github.com/yajra/laravel-datatables/compare/v8.5.2...v8.6.0
[v8.5.2]: https://github.com/yajra/laravel-datatables/compare/v8.5.1...v8.5.2
[v8.5.1]: https://github.com/yajra/laravel-datatables/compare/v8.5.0...v8.5.1
[v8.5.0]: https://github.com/yajra/laravel-datatables/compare/v8.4.4...v8.5.0
[v8.4.4]: https://github.com/yajra/laravel-datatables/compare/v8.4.3...v8.4.4
[v8.4.3]: https://github.com/yajra/laravel-datatables/compare/v8.4.2...v8.4.3
[v8.4.2]: https://github.com/yajra/laravel-datatables/compare/v8.4.1...v8.4.2
[v8.4.1]: https://github.com/yajra/laravel-datatables/compare/v8.4.0...v8.4.1
[v8.4.0]: https://github.com/yajra/laravel-datatables/compare/v8.3.3...v8.4.0
[v8.3.3]: https://github.com/yajra/laravel-datatables/compare/v8.3.2...v8.3.3
[v8.3.2]: https://github.com/yajra/laravel-datatables/compare/v8.3.1...v8.3.2
[v8.3.1]: https://github.com/yajra/laravel-datatables/compare/v8.3.0...v8.3.1
[v8.3.0]: https://github.com/yajra/laravel-datatables/compare/v8.2.0...v8.3.0
[v8.2.0]: https://github.com/yajra/laravel-datatables/compare/v8.1.1...v8.2.0
[v8.1.1]: https://github.com/yajra/laravel-datatables/compare/v8.1.0...v8.1.1
[v8.1.0]: https://github.com/yajra/laravel-datatables/compare/v8.0.3...v8.1.0
[v8.0.3]: https://github.com/yajra/laravel-datatables/compare/v8.0.2...v8.0.3
[v8.0.2]: https://github.com/yajra/laravel-datatables/compare/v8.0.1...v8.0.2
[v8.0.1]: https://github.com/yajra/laravel-datatables/compare/v8.0.0...v8.0.1
[v8.0.0]: https://github.com/yajra/laravel-datatables/compare/v7.10.1...v8.0.0

[#1702]: https://github.com/yajra/laravel-datatables/pull/1702
[#1728]: https://github.com/yajra/laravel-datatables/pull/1728
[#1724]: https://github.com/yajra/laravel-datatables/pull/1724
[#1719]: https://github.com/yajra/laravel-datatables/pull/1719
[#1688]: https://github.com/yajra/laravel-datatables/pull/1688
[#1658]: https://github.com/yajra/laravel-datatables/pull/1658
[#1628]: https://github.com/yajra/laravel-datatables/pull/1628
[#1624]: https://github.com/yajra/laravel-datatables/pull/1624
[#1609]: https://github.com/yajra/laravel-datatables/pull/1609
[#1492]: https://github.com/yajra/laravel-datatables/pull/1492
[#1489]: https://github.com/yajra/laravel-datatables/pull/1489
[#1487]: https://github.com/yajra/laravel-datatables/pull/1487
[#1485]: https://github.com/yajra/laravel-datatables/pull/1485
[#1484]: https://github.com/yajra/laravel-datatables/pull/1484
[#1483]: https://github.com/yajra/laravel-datatables/pull/1483
[#1473]: https://github.com/yajra/laravel-datatables/pull/1473
[#1476]: https://github.com/yajra/laravel-datatables/pull/1476
[#1478]: https://github.com/yajra/laravel-datatables/pull/1478
[#1479]: https://github.com/yajra/laravel-datatables/pull/1479
[#1462]: https://github.com/yajra/laravel-datatables/pull/1462
[#1468]: https://github.com/yajra/laravel-datatables/pull/1468
[#1467]: https://github.com/yajra/laravel-datatables/pull/1467
[#1465]: https://github.com/yajra/laravel-datatables/pull/1465
[#1461]: https://github.com/yajra/laravel-datatables/pull/1461
[#1460]: https://github.com/yajra/laravel-datatables/pull/1460
[#1449]: https://github.com/yajra/laravel-datatables/pull/1449
[#1438]: https://github.com/yajra/laravel-datatables/pull/1438
[#1444]: https://github.com/yajra/laravel-datatables/pull/1444
[#1446]: https://github.com/yajra/laravel-datatables/pull/1446
[#1445]: https://github.com/yajra/laravel-datatables/pull/1445
[#1416]: https://github.com/yajra/laravel-datatables/pull/1416
[#1382]: https://github.com/yajra/laravel-datatables/pull/1382
[#1368]: https://github.com/yajra/laravel-datatables/pull/1368
[#1355]: https://github.com/yajra/laravel-datatables/pull/1355
[#1569]: https://github.com/yajra/laravel-datatables/pull/1569
[#1554]: https://github.com/yajra/laravel-datatables/pull/1554
[#1553]: https://github.com/yajra/laravel-datatables/pull/1553
[#1536]: https://github.com/yajra/laravel-datatables/pull/1536
[#1532]: https://github.com/yajra/laravel-datatables/pull/1532
[#1496]: https://github.com/yajra/laravel-datatables/pull/1496
[#1730]: https://github.com/yajra/laravel-datatables/pull/1730
[#1737]: https://github.com/yajra/laravel-datatables/pull/1737
[#1741]: https://github.com/yajra/laravel-datatables/pull/1741
[#1743]: https://github.com/yajra/laravel-datatables/pull/1743
[#1758]: https://github.com/yajra/laravel-datatables/pull/1758
[#1759]: https://github.com/yajra/laravel-datatables/pull/1759
[#1792]: https://github.com/yajra/laravel-datatables/pull/1792
[#1830]: https://github.com/yajra/laravel-datatables/pull/1830
[#1860]: https://github.com/yajra/laravel-datatables/pull/1860
[#1811]: https://github.com/yajra/laravel-datatables/pull/1811
[#1882]: https://github.com/yajra/laravel-datatables/pull/1882
[#1911]: https://github.com/yajra/laravel-datatables/pull/1911
[#1912]: https://github.com/yajra/laravel-datatables/pull/1912
[#1852]: https://github.com/yajra/laravel-datatables/pull/1852
[#1942]: https://github.com/yajra/laravel-datatables/pull/1942
[#1960]: https://github.com/yajra/laravel-datatables/pull/1960
[#1988]: https://github.com/yajra/laravel-datatables/pull/1988
[#2001]: https://github.com/yajra/laravel-datatables/pull/2001
[#2002]: https://github.com/yajra/laravel-datatables/pull/2002
[#1813]: https://github.com/yajra/laravel-datatables/pull/1813
[#2067]: https://github.com/yajra/laravel-datatables/pull/2067
[#2051]: https://github.com/yajra/laravel-datatables/pull/2051
[#2072]: https://github.com/yajra/laravel-datatables/pull/2072
[#2082]: https://github.com/yajra/laravel-datatables/pull/2082
[#2079]: https://github.com/yajra/laravel-datatables/pull/2079
[#2083]: https://github.com/yajra/laravel-datatables/pull/2083
[#2088]: https://github.com/yajra/laravel-datatables/pull/2088
[#2085]: https://github.com/yajra/laravel-datatables/pull/2085
[#2102]: https://github.com/yajra/laravel-datatables/pull/2102
[#2084]: https://github.com/yajra/laravel-datatables/pull/2084
[#2103]: https://github.com/yajra/laravel-datatables/pull/2103
[#2163]: https://github.com/yajra/laravel-datatables/pull/2163
[#2171]: https://github.com/yajra/laravel-datatables/pull/2171
[#2191]: https://github.com/yajra/laravel-datatables/pull/2191

[#2091]: https://github.com/yajra/laravel-datatables/issues/2091
[#2058]: https://github.com/yajra/laravel-datatables/issues/2058
[#1626]: https://github.com/yajra/laravel-datatables/issues/1626
[#1617]: https://github.com/yajra/laravel-datatables/issues/1617
[#1294]: https://github.com/yajra/laravel-datatables/issues/1294
[#1068]: https://github.com/yajra/laravel-datatables/issues/1068
[#1234]: https://github.com/yajra/laravel-datatables/issues/1234
[#1353]: https://github.com/yajra/laravel-datatables/issues/1353
[#1367]: https://github.com/yajra/laravel-datatables/issues/1367
[#1377]: https://github.com/yajra/laravel-datatables/issues/1377
[#1515]: https://github.com/yajra/laravel-datatables/issues/1515
[#1659]: https://github.com/yajra/laravel-datatables/issues/1659
[#1351]: https://github.com/yajra/laravel-datatables/issues/1351
[#1600]: https://github.com/yajra/laravel-datatables/issues/1600
[#1471]: https://github.com/yajra/laravel-datatables/issues/1471
[#1739]: https://github.com/yajra/laravel-datatables/issues/1739
[#1516]: https://github.com/yajra/laravel-datatables/issues/1516
[#1752]: https://github.com/yajra/laravel-datatables/issues/1752
[#1824]: https://github.com/yajra/laravel-datatables/issues/1824
[#1805]: https://github.com/yajra/laravel-datatables/issues/1805
[#1747]: https://github.com/yajra/laravel-datatables/issues/1747
[#1951]: https://github.com/yajra/laravel-datatables/issues/1951
[#1983]: https://github.com/yajra/laravel-datatables/issues/1983
[#2003]: https://github.com/yajra/laravel-datatables/issues/2003
[#2045]: https://github.com/yajra/laravel-datatables/issues/2045
[#2054]: https://github.com/yajra/laravel-datatables/issues/2054
[#2024]: https://github.com/yajra/laravel-datatables/issues/2024
[#1977]: https://github.com/yajra/laravel-datatables/issues/1977
[#880]: https://github.com/yajra/laravel-datatables/issues/880
[#577]: https://github.com/yajra/laravel-datatables/issues/577
[#522]: https://github.com/yajra/laravel-datatables/issues/522
[#2161]: https://github.com/yajra/laravel-datatables/issues/2161
[#2156]: https://github.com/yajra/laravel-datatables/issues/2156
[#1822]: https://github.com/yajra/laravel-datatables/issues/1822
[#1738]: https://github.com/yajra/laravel-datatables/issues/1738


[laravel-datatables-fractal]: https://github.com/yajra/laravel-datatables-fractal

[@sskl]: https://github.com/sskl
[@drbyte]: https://github.com/drbyte
[@drahosistvan]: https://github.com/drahosistvan
[@LEI]: https://github.com/LEI
[@marcoocram]: https://github.com/marcoocram
[@ElfSundae]: https://github.com/ElfSundae
[@carusogabriel]: https://github.com/carusogabriel
[@gabriel-caruso]: https://github.com/gabriel-caruso
[@pimlie]: https://github.com/pimlie
[@jiwom]: https://github.com/jiwom
[@Oussama-Tn]: https://github.com/Oussama-Tn
[@redelschaap]: https://github.com/redelschaap
[@nunomaduro]: https://github.com/nunomaduro
[@asahasrabuddhe]: https://github.com/asahasrabuddhe
[@fschalkwijk]: https://github.com/fschalkwijk
[@forgottencreature]: https://github.com/forgottencreature
[@ptuchik]: https://github.com/ptuchik
[@zeyad82]: https://github.com/zeyad82
[@sharifzadesina]: https://github.com/sharifzadesina
[@ridaamirini]: https://github.com/ridaamirini
[@Spodnet]: https://github.com/Spodnet
[@royduin]: https://github.com/royduin
[@sgotre]: https://github.com/sgotre
[@lukchojnicki]: https://github.com/lukchojnicki
[@Morinohtar]: https://github.com/Morinohtar
[@Arkhas]: https://github.com/Arkhas
[@apreiml]: https://github.com/apreiml
[@Stokoe0990]: https://github.com/Stokoe0990
[@drsdre]: https://github.com/drsdre
[@selecod]: https://github.com/selecod
[@lloricode]: https://github.com/lloricode
