## Datatables Package for Laravel 4|5

[![Latest Stable Version](https://poser.pugx.org/rafaelqm/laravel-datatables/v/stable.png)](https://packagist.org/packages/rafaelqm/laravel-datatables)
[![Total Downloads](https://poser.pugx.org/rafaelqm/laravel-datatables/downloads.png)](https://packagist.org/packages/rafaelqm/laravel-datatables)
[![Build Status](https://travis-ci.org/rafaelqm/laravel-datatables.png?branch=master)](https://travis-ci.org/rafaelqm/laravel-datatables)
[![Latest Unstable Version](https://poser.pugx.org/rafaelqm/laravel-datatables/v/unstable.svg)](https://packagist.org/packages/rafaelqm/laravel-datatables)
[![License](https://poser.pugx.org/rafaelqm/laravel-datatables/license.svg)](https://packagist.org/packages/rafaelqm/laravel-datatables)

##Change Log

###v6.3.1
    - Fix artisan datatables:make service stub.

###v6.3.0
    - Add option to override default ordering via `->order(\Closure $callback)` method.
    - Add editor config.
    - Add some new features docs.
    - Remove Laravel 4.2 documentation on 6.0 branch.

###v6.2.4
    - Add git attributes.

###v6.2.3
    - Add setter/getter for filename.
    - Add html_entity_decode when exporting file.
    - Decode column title when exporting.

###v6.2.2
    - Extract data transformation task to own class.
    - Refactor duplicate response mapping code.
    - Increase scrutinizer score.

###v6.2.1
    - Fix data when exporting with html tags.
    - Add filename method in stub.
    - Fix some doc blocks.
    - Scrutinizer refactoring.

###v6.2.0
    - Enhance printing function to match what is displayed in UI.
    - Enhance export function to match what is displayed in UI.
    - Enhance datatables service stub.
    - Address issue #310.
    - Add option to set column as exportable and/or printable.
    - Action and checkbox column is not exportable but printable by default.

###v6.1.3
    - Fix logical bug with totalRecords and filteredRecords. Fix #333

###v6.1.2
    - Fix possible conflict with Laravel helpers.php file auto-loading. Fix #330.
    - Update dataTable service class stub. 

###v6.1.1
    - Fix ordering when using basic array response. Fix #322.

###v6.1.0
    - Add support for Lumen.
    - Fixes #317, #318.

###v6.0.0 - DataTable Service implementation.
    - Provides DataTable Service.
    - Provides artisan command for creating a service. php artisan datatables:make UsersDataTable
    - Provides artisan command for creating a DataTable scope. php artisan datatables:scope ActiveUserScope
    - Provides built-in support for server-side buttons. (Formerly TableTools).
    - Available buttons are csv, excel, pdf, print.
    - Built-in support for exporting to CSV, EXCEL and PDF using Laravel-Excel.
    - Built-in printer friendly view or create your own by overriding printPreview() method.
    - Change of namespace from rafaelqm\Datatables to Rafaelqm\Datatables.
    - Deprecated of() method when using DataTable service.
    - Automatic registration of required 3rd party providers.
        - [Laravel Excel](https://github.com/Maatwebsite/Laravel-Excel)
        - [Laravel Collective HTML & Forms](https://github.com/LaravelCollective/html)
    - Automatic registration of Datatables facade.
    - HTML Builder with javascript from template. #298 - Credits to @vladkucherov.
    - HTML Builder column render now accepts a string, view or closure. #300 - Credits to @vladkucherov
    - Add resource on json response by using `->with('key', 'value')` method. #277

###v5.12.5
    - Get order column name from the request. Fix #307.

###v5.12.4
    - Fix searching when aliasing a column. Fix #274.

###v5.12.3
    - Remove checking of columns - name index and let setupColumnName method to identify the proper column name.

###v5.12.2
    - Fix double prefix when using join queries. Fix #272, #273

###v5.12.1
    - Fix support for PHP5.4.

###v5.12.0
    - Added support for Fractal Serializer.
    - Added config for default serializer.
    - Note: Should be used along with setTransformer method.
    - Usage:
        return Datatables::of($model)
            ->setTransformer(ModelTransformer::class)
            ->setSerializer(ModelSerializer::class)
            ->make(true);

###v5.11.14
    - Sort by a multi-line 'select as' query. PR #245

###v5.11.13
    - Allow fractal v0.12 and up. Fix #237.

###v5.11.12
    - Use connection grammar to wrap columns and table name.

###v5.11.11
    - Parse includes in fractal. Fix #225.

###v5.11.10
    - CollectionEngine: fix sorting of relation columns by using seralize (like filtering). PR #197.

###v5.11.9
    - Add fix for QueryBuilder: ORDER BY *. PR #194.

###v5.11.8
    - Skip search, order and pagination on empty records.
    - Fix #149 and #176 empty collection error when using make(false).
    - Fix credits to @gabrielwelsche.

###v5.11.7
    - Fix escaping of row when using eager loading. Fix #164
    - Add support for escaping rows using array dot notation when declaring escapeColumns.
        Example: `->escapeColumns(['name', 'post.title'])`

###v5.11.6
    - Refactor eloquent and query builder engine duplicate codes.

###v5.11.5
    - Fix issues on database prefix. #161 and #162
    - Fix database prefix value when using Eloquent.

###v5.11.4
    - Fix Undefined offset issue when using addColumn.
    - Credits to @openvast for PR #158

###v5.11.3
    - Pass object or array to transformer. PR #155

###v5.11.2
    - Add support for regular expressions search on column.

###v5.11.1
    - Collection engine enhancement.
    - Add support for filtering compound key PR #146.
    - Add support for ordering using compound key.

###v5.11.0
    - Add support for rendering view directly on addColumn and editColumn.

###v5.10.0
    - Add LaravelDataTables on js window namespace. Issue #129. Credits to @greabock.

###v5.9.2
    - Fix possible error when rendering table and overriding the attributes.
    - Merge DT parameters.

###v5.9.1
    - Fix default ajax value causing js data null error.

###v5.9.0
    - Added escapeColumns feature to escape the values.
    - Addresses XSS filtering issue #128.

###v5.8.6
    - Fix DT_Row options when returning a flatten array response.
    - Fix PR #126.

###v5.8.5
    - Revert try-catch when compiling blade.
    - Fix html builder unit test.

###v5.8.4
    - Fix html builder merging of column attributes.

###v5.8.3
    - Added space when setting html builder table attributes.
    - Set a default data value when adding a column.
    - Removed unnecessary slash when getting html builder.
    - Added html builder unit test.
    - Improved test coverage.

###v5.8.2
    - Fix count when using DISTINCT query. Fix #125

###v5.8.1
    - Fix compatiblity with PHP 5.4.

###v5.8.0
    - Enhanced html builder class.
    - Added function to load html builder `columns` via mixed array.
        - Automatic resolution of qualified title based on field name.
        - Overriding of column attributes.
    - Added html builder and request object getter from main Datatables class.
    - Added more unit tests.

###v5.7.0
    - Added orderColumn feature.

###v5.6.1
    - Make BaseEngine $request property public.
    - Fix global searching when search value is zero (0).
    - Refactor methods from v5.6.0.

###v5.6.0
    - Re-implement filterColumn function with special variable $1.
    - Fix filterColumn not getting included on OR statements within global search.
    - Fix #115.

###v5.5.11
    - Fix ordering for when using column alias and make(false). Fix #103.

###v5.5.10
    - Fix casting specific to stdClass only. Fix #114.

###v5.5.9
    - Fix ordering of collection when data is stdClass.

###v5.5.8
    - Fix issue when converting object to array. Fix #108.

###v5.5.7
    - Fix and enhance support when passing object variables using blade templating approach.
    - Random code clean-up.

###v5.5.6
    - Fix eager loading of hasOne and hasMany relationships. Issue #105.

###v5.5.5
    - Fix collection engine sorting when columns is not defined

###v5.5.4
    - Fix support for collection of objects

###v5.5.3
    - Fix total filtered records count when overriding global search.
    - Fix implementation of PR #95 on Collection Engine.

###v5.5.2
    - Fix database driver detection on Eloquent Engine.

###v5.5.1
    - Fix missing import of Helper class.

###v5.5.0
    - Refactor classes to improve code quality.
    - Implemented PR #95.

###v5.4.4
    - Added column wrapper for SQLITE.

###v5.4.3
    - Added column wrapper for Postgres. Bugfix #82.

###v5.4.2
    - Throws Exception when using DataTable's legacy code.
    - Fixed CS - PSR2.

###v5.4.1
    - Fixed Builder generateScript method.

###v5.4
    - Added Html Builder.
    - Added magic methods to call enginges without the "using" word.
    - Minor Bugfixes.

###v5.3
    - Added scrutinizer.
    - Code refactor/cleanup based on scrutinizers suggestions.
    - Bugfix #75.

###v5.2
    - Datatables can now be used via Laravel IOC container `app('datatables')`.
    - Datatables Engine can now be used directly along with Laravel IOC.
        - Available Engines:
            - Query Builder Engine. `app('datatables')->usingQueryBuilder($builder)->make()`.
            - Eloquent Engine. `app('datatables')->usingEloquent($model)->make()`.
            - Collection Engine. `app('datatables')->usingCollection($collection)->make()`.
    - Datatables is now more testable and works with https://github.com/laracasts/integrated.
    - Bugfix #56.

###v5.1
    - Added filterColumn function to override default global search in each column.
    - Datatables class extending Query Builder's functionality along with global search.
    - Restore queries on result when app is in debug mode.
    - Added input on result when app is in debug mode.
    - Force enable query log when app is in debug mode.
    - Convert string search from preg_match to Str::contains to improve performance.
    - Added support for having clause queries.
    - Added support for `league/fractal` for transforming data API output.

###v5.0
    - Strictly for Laravel 5++.
    - Drop support for DT1.9 and below.
    - Strict implmentation of DT1.10 script pattern.
    - Added support for Collection as data source.

###v4.3.x
    - Stable version for Laravel 5 with support for DT1.9.
    - Collection Engine not available.

###v3.6.x
    - Stable version for Laravel 4.2.

###v2.x
    - Stable version for Laravel 4.0 and 4.1
