## Datatables Package for Laravel 4|5

[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

## Change Log

### v7.10.1 - 08-31-2017
- Flatten array before comparing then revert to original form. #1340
- Fix #1337, sorting on collection relation.

### v7.10.0 - 08-24-2017
- Add limit query callback method. #1338
- Disable offset pagination using limit with additional where clause.
- Fix #1332.

### v7.9.9 - 08-01-2017
- Patch configuration override issue. #1311
- Fix escapeColumns bug. #1306, credits to @lk77.

### v7.9.8 - 08-01-2017
- Fix fetching of column definitions. #1310
- Fix #1307, #1305, #1306, #1302.

### v7.9.7 - 07-29-2017
- Merge array recursive. #1303

### v7.9.6 - 07-29-2017
- Whitelisting src directory in phpunit.xml #1298, credits to @lk77.
- Fixed configuration override issue. #1293, credits to @zgldh.

### v7.9.5 - 07-21-2017
- Add text cast for pgsql regex search #1279, credits to @martimarkov.
- Use static class to resolve datatables instance. #1282, Re-apply fix for #464, #465.
- Fix duplicate queries when transforming the output. PR #1283, Fix #1280.

### v7.9.4 - 07-14-2017
- Change getQualifiedOtherKeyName to getQualifiedOwnerKeyName #1254. Credits to @zizohassan.
- Readme: implictly set "--provider" when publishing #1255. Credits to @uniconstructor.

### v7.9.3 - 07-12-2017
- Revert transformed `DT_Row_Index` back to it's original values. #1259
- Fix searching not triggered with zero value. #1257
- Fix #1243, #1223.

### v7.9.2 - 07-05-2017
- Fix multi-column sort of collection #1238. 
- Fix #1237, credits to @jond64

### v7.9.1 - 06-30-2017
- Fix transformer implementation when parameter requires an object. #1235

### v7.9.0 - 06-29-2017
- Process and transform data first before filtering and ordering collection #1232
- Support searching and sorting of added/edited columns when using collection.
- Refactor make & render method.
- Fix #694, #1229, #1142, etc.
- Fix merging of column definitions. #1233

### v7.8.1 - 06-27-2017
- Set columns orderable & searchable property as true by default. #1228
- In relation to [yajra/laravel-datatables-html#13](https://github.com/yajra/laravel-datatables-html/pull/13).

### v7.8.0 - 06-23-2017
- Extract default columns definition to config. #1175

### v7.7.1 - 06-22-2017
- The global search keywords should not be an array on collection. #1221, credits to @liub1993.

### v7.7.0 - 06-07-2017
- Add config for setting the default JsonResponse header and options. #1177
- Allow sorting for blacklisted columns with custom handler. #1192
- Fix #1034, #1191.

### v7.6.0 - 05-31-2017
- Fix addColumn search/sort query bug by adding it to blacklist. PR #1158, credits to @liub1993.
- Add `pushToBlacklist($column)` api. #1158
- Allow filtering of added column if a custom handler was defined. #1169
- Add more tests. #1170

### v7.5.2 - 05-26-2017
- Remove empty arrays returned from array_dot. PR #1161
- Fix #1160.

### v7.5.1 - 05-26-2017
- Fix column name added to select when relation is belongsToMany. #1155
- Fix rawColumns not working on relationships. #1156
- Fixes #1094, #1151.
- Add docs for artisan serve bug.

### v7.5.0 - 05-22-2017
- Do not use ::class to avoid IDE error when fractal is not installed. #1132
- Add server-side [error handler](https://yajrabox.com/docs/laravel-datatables/7.0/error-handler). #1131

### v7.4.0 - 05-01-2017
- Implement multi-term smart search in collection engine. #1115
- Implement multi-term smart search in QueryBuilderEngine. #1113
- Fix #881, #1109, #998
- Credits to @apreiml.

### v7.3.0 - 02-23-2017
- Add support ordering when search in nested relations #965.
- Credits to @AdrienPoupa and @ethaizone.

### v7.2.1 - 02-16-2017
- Move orchestra/testbench to require-dev.
- Use phpunit 5.7 to match Laravel’s requirement.
- Revert branch alias.

### v7.2.0 - 02-16-2017
- Add support for array data source. [#992](https://github.com/yajra/laravel-datatables/pull/992)
- Minor comment correction [#1002](https://github.com/yajra/laravel-datatables/pull/1002), credits to @lk77.

### v7.1.4 - 02-09-2017
- Fix collection case insensitive ordering.
- Fix [#945](https://github.com/yajra/laravel-datatables/issues/945).

### v7.1.3 - 02-06-2017
- Use stable packages. 
- Fix [#977](https://github.com/yajra/laravel-datatables/issues/977).

### v7.1.2 - 02-06-2017
- Add bindings from relation. [#979](https://github.com/yajra/laravel-datatables/pull/979)
- Fix [#960](https://github.com/yajra/laravel-datatables/issues/960).
- PR [#962](https://github.com/yajra/laravel-datatables/pull/962), credits to @snagytx.

### v7.1.1 - 01-30-2017
- Fix doc block.
- Fix request class usage on collection engine.

### v7.1.0 - 01-30-2017
- Use orchestra testbench to test the package.
- Enhance identification of proper engine to use for a given builder. Fix [#954](https://github.com/yajra/laravel-datatables/issues/954).
- Use Laravel config helper instead of using the facade.
- Enhance Request class to make it testable using phpunit. Address issue [#901](https://github.com/yajra/laravel-datatables/issues/901)

### v7.0.2 - 01-29-2017
- Map all model relations to eloquent engine. Fix #950

### v7.0.1 - 01-27-2017
- Revert getHtmlBuilder method for backward compatibility.
- Add html builder test.
- Rename Test class name.
- Add eloquent engine test.

### v7.0.0 - 01-27-2017
- Support for Laravel 5.4.
- Features are split across packages. #832
    - Buttons service approach extracted to own plugin.
        - Add fluent way to send variables to DataTable class. #845
    - Html builder extracted to own plugin.
- DataTable Engines are now pluggable. #544
- Added option to order by nulls last. #794
- Escape all columns by default for XSS protection. Fix #909
- Add rawColumns method for unescaped columns. https://github.com/yajra/laravel-datatables/commit/81adef8555195795189853f91e326dd056e40bb0

## TODO
- Fix IE compatibility by using POST method when exporting/printing. #826
- Enhance/Fix nested relations support. #789
- Export Selected Rows Datatables Service Provider. #829 #850
