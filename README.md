## Datatables Package for Laravel 4|5

[![Build Status](https://travis-ci.org/yajra/laravel-datatables.png?branch=master)](https://travis-ci.org/yajra/laravel-datatables)
[![Latest Stable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/stable.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Total Downloads](https://poser.pugx.org/yajra/laravel-datatables-oracle/downloads.png)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![Latest Unstable Version](https://poser.pugx.org/yajra/laravel-datatables-oracle/v/unstable.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)
[![License](https://poser.pugx.org/yajra/laravel-datatables-oracle/license.svg)](https://packagist.org/packages/yajra/laravel-datatables-oracle)

**About**

This package is created to handle [server-side](https://www.datatables.net/manual/server-side) works of [DataTables](http://datatables.net) jQuery Plugin via [AJAX option](https://datatables.net/reference/option/ajax) by using Eloquent ORM, Fluent Query Builder or Collection.

### Feature Overview
- Supports the following data source
    - **Eloquent ORM**
    - **Fluent Query Builder**
    - **Collection** [available on v5.x and later]
- Adding or editing content of columns and removing columns
- Templating new or current columns via Blade Template Engine or by using Closure
- Works with **ALL the DATABASE** supported by Laravel
- Works with **Oracle Database** using [Laravel-OCI8](https://github.com/yajra/laravel-oci8) package
- Works with [DataTables](http://datatables.net) v1.10++

**If you are using Datatables v1.9, please use package version [v4.x](https://github.com/yajra/laravel-datatables-oracle/tree/v4.3.2) and [v3.x](https://github.com/yajra/laravel-datatables-oracle/tree/L4) for Laravel 5 and Laravel 4 respectively**

### Install through composer
`composer require yajra/laravel-datatables-oracle:~5.0`

> For Laravel 4 users, checkout [L4](https://github.com/yajra/laravel-datatables-oracle/tree/L4) branch or use `composer require yajra/laravel-datatables-oracle:~3.0`.

### Register provider and alias and publish configuration
Once the package was downloaded, open "config/app.php" and register the `provider` and `alias` of the package:
```php
    'providers' => [
        ...
        'yajra\Datatables\DatatablesServiceProvider',
    ],

    'aliases' => [
        ...
        'Datatables'      => 'yajra\Datatables\Datatables',
    ],
```
Finally you need to publish a configuration file by running the following Artisan command.

```php
$ php artisan vendor:publish --provider="yajra\Datatables\DatatablesServiceProvider"
// or simply run below to publish all vendor's config files
$ php artisan vendor:publish
```

### Demo Application
- Check the [Laravel Datatables Demo App](https://github.com/yajra/laravel-datatables-demo) for a real example/working code. **Hosted demo site coming soon.**

### Usage

It is very simple to use this package. Just create your own fluent query object or eloquent object without getting results (that means don't use get(), all() or similar methods) and give it to Datatables.
You are free to use all Eloquent ORM and Fluent Query Builder features.

It is better, you know these:
- When you use select method on Eloquent or Fluent Query Builder, you choose columns
- You can easily edit columns by using `editColumn($column, $content)`
- You can remove any column by using `removeColumn($column)` method
- You can add columns by using `addColumn($column_name, $content, $order)`
- You can override the default filter function by using `filter(function($query){})`
- You can use Blade Template Engine in your `$content` values
- The name of columns is set by returned array.
    - That means, for 'posts.id' it is 'id' and also for 'owner.name as ownername' it is 'ownername'
- You can easily toggle datatables mdata support by passing `true/false` on `make` function. `->make(true)`
- You can add `DT_RowId` via `->setRowId('id')` function. Parameters could be like below:
    - a `column` on selected query if exists, else will just return the parameter
    - a `closure` function
    - a `blade` template
- You can add `DT_RowClass` via `->setRowClass('id')` function. Parameters could be like below:
    - a `column` on selected query if exists, else will just return the parameter
    - a `closure` function
    - a `blade` template
- You can add `DT_RowData` via these functions:
    - `->setRowData(array())` to add batch data using an array
    - `->addRowData($key, $value)` to append single data on array (Note: `setRowData` should be called first if you plan on using both functions)
    - the value parameter can also be a `string`, `closure` or `blade` template.
- You can add `DT_RowAttr` via these functions:
    - Note: This option will only work on DataTables 1.10.5 or newer
    - `->setRowAttr(array())` to add batch data using an array
    - `->addRowAttr($key, $value)` to append single data on array (Note: `setRowAttr` should be called first if you plan on using both functions)
    - the value parameters can also be a `string`, `closure` or `blade` template.
- You can override default filter function on each column by using `filterColumn` function.
    - Usage: `filterColumn($column, $method, $param_1, $param_2, ..., $param_n )`
        - `$column` - the column name that search filter is be applied to
        - `$method` - can be any of QueryBuilder methods (where, whereIn, whereBetween, having etc.).

            > Note: For global search, these methods are automaticaly converted to their "or" equivalents (if applicable, if not applicable, the column is not searched).
            If you do not want some column to be searchable in global search, set the javascript column flag `searchable: false`.
        - `$param_1 ... $param_n` - these are parameters that will be passed to the selected where function ($method). Possible types:
            - string
            - DB::raw() - The DB::raw() can output literaly everything into the query. For example, subqueries or branching if you need some really sophisticated wheres.
            - function - or any other callable
            - array of any of the above
- Datatables now extends [Query Builder](http://laravel.com/docs/5.0/queries#advanced-wheres)'s functionality which means that you can directy filter results using the `Datatables` class.

    ```php
    // Query Builder's extended function.
    // See Laravel Query Builder docs for more details.
    return Datatables::of($user)
        ->whereIn('id', [1,2,3])
        ->whereNull('id')
        ->make(true);
    ```
- You can use `league\fractal` to transform API data output by using `setTransformer('App\Transformer\DataTransformer')`. See [Fractal Transformer](http://fractal.thephpleague.com/transformers/) docs for details.
- [v5.2++] Datatables can now be use using IOC container like `app('datatables')`
- [v5.2++] Datatables Engines can now be called along with IOC container. `app('datatables')->usingQueryBuilder($builder)->make(true)`
    - Available Engines:
        - Query Builder Engine. `->usingQueryBuilder($builder)->make()`
        - Eloquent Engine. `->usingEloquent($model)->make()`
        - Collection Engine. `->usingCollection($collection)->make()`

### Examples

**Example 1:**
```php
    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)->make();
```

**Example 2:**
```php
    $place = Place::leftJoin('owner','places.author_id','=','owner.id')
                    ->select(array('places.id','places.name','places.created_at','owner.name as ownername','places.status'));


    return Datatables::of($place)
    ->addColumn('operations','<a href="{{ URL::route( \'admin.post\', array( \'edit\',$id )) }}">edit</a>
                    <a href="{{ URL::route( \'admin.post\', array( \'delete\',$id )) }}">delete</a>
                ')
    ->editColumn('status','@if($status)
                                Active
                            @else
                                Passive
                            @endif')
    // you can also give a function as parameter to editColumn and addColumn instead of blade string
    ->editColumn('ownername','Author of this post is {{ $ownername }}')
    ->removeColumn('id')
    ->make();
```

**Example 3: Overriding default filter option**
```php
    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)
        ->filter(function($query){
            if (Input::get('id')) {
                $query->where('id','=',Input::get('id'));
            }
        })->make();
```

**Example 4: Accessing Carbon object on timestamps and/or any objects in model**
> Note: Only applicable if you use Eloquent object.

```php
    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)
        ->editColumn('created_at', function($data){ $data->created_at->toDateTimeString() })
        ->filter(function($query){
            if (Input::get('id')) {
                $query->where('id','=',Input::get('id'));
            }
        })->make();
```

**Example 5:** Returning object data source
```php
    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)->make(true);
```

**Example 6:** DT_RowId, DT_RowClass, DT_RowData and DT_RowAttr

```php
$users = User::select('*');

return Datatables::of($users)
    ->setRowId('id') // via column name if exists else just return the value
    ->setRowId(function($user) {
        return $user->id;
    }) // via closure
    ->setRowId('{{ $id }}') // via blade parsing

    ->setRowClass('id') // via column name if exists else just return the value
    ->setRowClass(function($user) {
        return $user->id;
    }) // via closure
    ->setRowClass('{{ $id }}') // via blade parsing

    ->setRowData([
        'string' => 'data',
        'closure' => function($user) {
            return $user->name;
        },
        'blade' => '{{ $name }}'
        ])
    ->addRowData('a_string', 'value')
    ->addRowData('a_closure', function($user) {
        return $user->name;
    })
    ->addRowData('a_blade', '{{ $name }}')
    ->setRowAttr([
        'color' => 'data',
        'closure' => function($user) {
            return $user->name;
        },
        'blade' => '{{ $name }}'
        ])
    ->addRowAttr('a_string', 'value')
    ->addRowAttr('a_closure', function($user) {
        return $user->name;
    })
    ->addRowAttr('a_blade', '{{ $name }}')
    ->make(true);
```

###Example View and Controller
On your view:
```php
<table id="users" class="table table-hover table-condensed">
    <thead>
        <tr>
            <th class="col-md-3">{{{ Lang::get('users/table.username') }}}</th>
            <th class="col-md-3">{{{ Lang::get('users/table.email') }}}</th>
            <th class="col-md-3">{{{ Lang::get('users/table.created_at') }}}</th>
            <th class="col-md-3">{{{ Lang::get('table.actions') }}}</th>
        </tr>
    </thead>
</table>

<script type="text/javascript">
$(document).ready(function() {
    oTable = $('#users').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": "/users/data",
        "columns": [
            {data: 'username', name: 'username'},
            {data: 'email', name: 'email'},
            {data: 'created_at', name: 'created_at'},
            {data: 'actions', name: 'actions'}
        ]
    });
});
</script>
```
On your controller:
```php
public function getData()
{
    $users = $this->users->select('*');

    return Datatables::of($users)
        ->addColumn('action', 'action here')
        ->make(true);
}
```


**Notice:** If you use double quotes while giving content of addColumn or editColumn, you should escape variables with backslash (\\) else you get error. For example:
```php
    editColumn('id',"- {!! \$id !!}") .
```

**License:** Licensed under the MIT License

### Credits
* This project is used to be a fork from [bllim/laravel4-datatables-package](https://github.com/bllim/laravel4-datatables-package).
