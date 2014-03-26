## Datatables Bundle for Laravel 4 (Oracle Support)

**About**

This bundle is created to handle server-side works of [DataTables](http://datatables.net) Jquery Plugin by using Eloquent ORM or Fluent Query Builder.

### Feature Overview
- Supporting Eloquent ORM and Fluent Query Builder
- Adding or editing content of columns and removing columns
- Templating new or current columns via Blade Template Engine


### Installation

Add the `yajra/datatables` under the `require` key after that run the `composer update`.
```php
    {
        "require": {
            "laravel/framework": "4.1.*",
            ...
            "yajra/laravel-datatables-oracle": "*"
        }
        ...
    }
```
Composer will download the package. After package downloaded, open "app/config/app.php" and edit like below:
```php
    'providers' => array(
        ...
        'yajra\Datatables\DatatablesServiceProvider',
    ),



    'aliases' => array(
        ...
        'Datatables'      => 'yajra\Datatables\Datatables',
    ),
```
Finally you need to publish a configuration file by running the following Artisan command.

```
$ php artisan config:publish yajra/laravel-datatables-oracle
```

### Usage

It is very simple to use this bundle. Just create your own fluent query object or eloquent object without getting results (that means don't use get(), all() or similar methods) and give it to Datatables.
You are free to use all Eloquent ORM and Fluent Query Builder features.

It is better, you know these:
- When you use select method on Eloquent or Fluent Query, you choose columns
- You can easily edit columns by using ```editColumn($column, $content)```
- You can remove any column by using ```removeColumn($column)``` method
- You can add columns by using ```addColumn($column_name, $content, $order)```
- You can use Blade Template Engine in your $content values
- The name of columns is set by returned array.
    - That means, for 'posts.id' it is 'id' and also for 'owner.name as ownername' it is 'ownername'


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
**Notice:** If you use double quotes while giving content of addColumn or editColumn, you should escape variables with backslash (\\) else you get error. For example:
```php
    editColumn('id',"- {{ \$id }}") .
```

**License:** Licensed under the MIT License

### Credits
* [bllim/laravel4-datatables-package](https://github.com/bllim/laravel4-datatables-package)
