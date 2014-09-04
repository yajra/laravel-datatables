## Datatables Bundle for Laravel 4

**About**

This bundle is created to handle the server-side processing of the DataTables Jquery Plugin (http://datatables.net) by using Eloquent ORM or Fluent Query Builder.

### Feature Overview
- Supporting Eloquent ORM and Fluent Query Builder
- Adding or editing the contents of columns and removing columns
- Templating new or current columns via Blade Template Engine
- Customizable search in columns


### Installation

Require `bllim/datatables` in composer.json and run `composer update`.

    {
        "require": {
            "laravel/framework": "4.0.*",
            ...
            "bllim/datatables": "*"
        }
        ...
    }

Composer will download the package. After the package is downloaded, open `app/config/app.php` and add the service provider and alias as below:

    'providers' => array(
        ...
        'Bllim\Datatables\DatatablesServiceProvider',
    ),



    'aliases' => array(
        ...
        'Datatables'      => 'Bllim\Datatables\Facade\Datatables',
    ),

Finally you need to publish a configuration file by running the following Artisan command.

```php
$ php artisan config:publish bllim/datatables
```

### Usage

It is very simple to use this bundle. Just create your own fluent query object or eloquent object without getting results (that means don't use get(), all() or similar methods) and give it to Datatables.
You are free to use all Eloquent ORM and Fluent Query Builder features.

Some things you should know:
- When you call the `select` method on Eloquent or Fluenty Query, you choose columns.
- Modifying columns
    - You can easily edit columns by using `edit_column($column, $content)`
    - You can remove any column by using `remove_column($column)`
    - You can add columns by using `add_column($column_name, $content, $order)
    - You **can** use the Blade Template Engine in your `$content` values.
    - You may pass a function as the $content to the `add_column` or `edit_column` calls. The function receives a single parameter: the query row/model record. (see Example 2)
- The column identifiers are set by the returned array.
    - That means, for `posts.id` the relevant identifier is `id`, and for `owner.name as ownername` it is `ownername`
- You can set the "index" column (http://datatables.net/reference/api/row().index()) using `set_index_column($name)`
- You can add class (DT_RowClass) to each row using `set_row_class($content)` function.
- You can add jquery's data (DT_RowData) to each row using `set_row_data($name,$content)` function.
- You can add customized search filters for each column to override the default search functionality
- You can call `make(true)` to return an array of objects instead of an array of arrays. (see Example 4)

### Examples

**Example 1: Simple use**

    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)->make();


**Example 2: Adding and editing columns**

    $place = Place::left_join('owner','places.author_id','=','owner.id')
       ->select(array('places.id','places.name','places.created_at','owner.name as ownername','places.status'));


    return Datatables::of($place)
        ->add_column('operations', '<a href="{{ URL::route( \'admin.post\', array( \'edit\',$id )) }}">edit</a>
                        <a href="{{ URL::route( \'admin.post\', array( \'delete\',$id )) }}">delete</a>
                    ')
        ->edit_column('status', '{{ $status ? 'Active' : 'Passive' }}')
        ->edit_column('ownername', function($row) {
            return "The author of this post is {$row->ownername}";
        })
        ->remove_column('id')
        ->make();

**Notice:** If you use double quotes while assigning the $content in an `add_column` or `edit_column` call,  you should escape variables with a backslash (\\) to prevent an error. For example:

    edit_column('id', "{{ \$id }}") .


**Example 3: Using filter_column**

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

Usage: `filter_column (  $column_name,  $method,  $param_1,  $param_2,  ...,  $param_n  )`
* `$column_name` - the column name that search filter is be applied to
* `$method` - can be any of QueryBuilder methods (where, whereIn, whereBetween, having etc.). 
    * Note: For global search these methods are automaticaly converted to their "or" equivalents (if applicable, if not applicable, the column is not searched).
    * If you do not want some column to be searchable in global search, set the last parameter to "and" (see line 17 in example above). Doing this, the filter cannot be switched into its "or" equivalent and therefore will not be searched in global search .
* `$param_1 ... $param_n` -  these are parameters that will be passed to the selected where function (`$method`). Possible types:
  * `string`
  * `DB::raw()` - The DB::raw() can output literaly everything into the query. For example, subqueries or branching if you need some really sophisticated wheres.
  * `function` - or any other callable
  * `array` of any of the above
* The search value is passed to the query by the string `$1` placed anywhere in parameters. If a callable (function) is used the searched value is passed to callable as first parameter the first parameter (again see line 17).
    * The callable must return a value that will be passed to the QueryBuilder's function.


**Example 4: Returning an array of objects**

    $posts = Post::select(array('posts.id','posts.name','posts.created_at','posts.status'));

    return Datatables::of($posts)->make(true);

This returns a JSON array with data like below:

    data: {
        {
            id: 12,
            name: 'Dummy Post',
            created_at: '1974-06-20 13:09:51'
            status: true
        }
        {
            id: 15,
            name: 'Test post please ignore',
            created_at: '1974-06-20 13:15:51',
            status: true
        }
    }
    
**Example 5: DT_RowID, DT_RowClass and DT_RowData**

```php
$todo = ToDoList::select(array('todo.id','todo.name','todo.created_at','todo.status'));

return Datatables::of($todo)
    ->set_index_column('id')
    ->set_row_class('@if($status=="done") success @endif')
    ->set_row_data('created_at','{{$created_at}}')
    ->make();
```

**License:** Licensed under the MIT License
