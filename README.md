## Datatables Bundle for Laravel 4

**About**

This bundle is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net) by using Eloquent ORM or Fluent Query Builder.

### Feature Overview
- Supporting Eloquent ORM and Fluent Query Builder
- Adding or editing content of columns and removing columns
- Templating new or current columns via Blade Template Engine
- Customizable search in columns


### Installation

Add the `bllim/datatables` under the `require` key after that run the `composer update`.

    {
        "require": {
            "laravel/framework": "4.0.*",
            ...
            "bllim/datatables": "*"
        }
        ...
    }

Composer will download the package. After package downloaded, open "app/config/app.php" and edit like below:

    'providers' => array(
        ...
        'Bllim\Datatables\DatatablesServiceProvider',
    ),



    'aliases' => array(
        ...
        'Datatables'      => 'Bllim\Datatables\Datatables',
    ),

Finally you need to publish a configuration file by running the following Artisan command.

```php
$ php artisan config:publish bllim/datatables
```

### Usage

It is very simple to use this bundle. Just create your own fluent query object or eloquent object without getting results (that means don't use get(), all() or similar methods) and give it to Datatables.
You are free to use all Eloquent ORM and Fluent Query Builder features.

It is better, you know these:
- When you use select method on Eloquent or Fluent Query, you choose columns
- You can easily edit columns by using edit_column($column,$content)
- You can remove any column by using remove_column($column) method
- You can add columns by using add_column($column_name, $content, $order)
- You can use Blade Template Engine in your $content values
- The name of columns is set by returned array.
    - That means, for 'posts.id' it is 'id' and also for 'owner.name as ownername' it is 'ownername'
- You can set the "index" column (http://datatables.net/reference/api/row%28%29.index%28%29) using set_index_column($name)
- You can add search filters for each column to override default search functionality


### Examples

**Example 1:**

    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)->make();


**Example 2:**

    $place = Place::left_join('owner','places.author_id','=','owner.id')
                    ->select(array('places.id','places.name','places.created_at','owner.name as ownername','places.status'));


    return Datatables::of($place)
    ->add_column('operations','<a href="{{ URL::route( \'admin.post\', array( \'edit\',$id )) }}">edit</a>
                    <a href="{{ URL::route( \'admin.post\', array( \'delete\',$id )) }}">delete</a>
                ')
    ->edit_column('status','@if($status)
                                Active
                            @else
                                Passive
                            @endif')
    // you can also give a function as parameter to edit_column and add_column instead of blade string
    ->edit_column('ownername','Author of this post is {{ $ownername }}')
    ->remove_column('id')
    ->make();

**Notice:** If you use double quotes while giving content of add_column or edit_column, you should escape variables with backslash (\) else you get error. For example:

    edit_column('id',"- {{ \$id }}") .


**Example 3:**

    $clients = Client::select(array(
    		'Client.id',
    		DB::raw('CONCAT(Client.firstname," ",Client.lastname) as ClientName'),
    		'Client.email',
    		'Client.code',
    		'Client.updated_at',
    		'Client.isActive',
    		'Language.name as LanguageName',
    	))
    	->leftJoin('Language', 'Client.Language_id', '=', 'Language.id')
    	->where('isDeleted', '!=', '1');
    
    return Datatables::of($clients)
    		->filter_column('id', 'where', 'Client.id', '=', '$1')
    		->filter_column('code', 'where', 'Client.code', '=', DB::raw('UPPER($1)'))
    		->filter_column('LanguageName', 'whereIn', 'Language.name', function($value) { return explode(',',$value); })
    		->filter_column('updated_at', 'whereBetween', 'Client.updated_at', function($value) { return explode(',',$value); }, 'and')
    		->edit_column('isActive', '@if($isActive) <span class="label label-success">Active</span> @else <span class="label label-danger">Inactive</span> @endif')
    		->make();

**Notes on filter_column:**

Usage: `filter_column ( $column_name,  $method,  $param_1, $param_2  ...  $param_n  )`
* `$column_name` - the column name that search filter is be applied to
* `$method` - can be any of QueryBuilder methods (where, whereIn, whereBetween, having etc.). 
    * Note: For global search these methods are automaticaly converted to their "or" equivalents (if applicable, if not applicable, the column is not searched).
    * If you do not want some column to be searchable in global search set the last where's parameter to "and" (see line 17 in example above). Doing this way the filter cannot be switched into its "or" equivalent therefore will not be searched in global search .
* `$param_1 ... $param_n` -  these are parameters that will be passed to the selected where function. Possible types:
  * `string`
  * `DB::raw()` - The DB::raw() can output literaly everything into the query, for example subqueries or branching if you need some really sophisticated wheres.
  * `function` - or any other callable
  * `array` of any above
* the search value is passed to the query by `$1` string placed anywhere in parameters. If callable (function) is used the searched value is passed to callable as first parameter. The callable must returns value that will be passed to the QueryBuilder's function.



**License:** Licensed under the MIT License
