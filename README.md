## Datatables Bundle for Laravel 4

**About**

This bundle is created to handle server-side works of DataTables Jquery Plugin (http://datatables.net) by using Eloquent ORM or Fluent Query Builder.

### Feature Overview
- Supporting Eloquent ORM and Fluent Query Builder
- Adding or editing content of columns and removing columns
- Templating new or current columns via Blade Template Engine


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


**License:** Licensed under the MIT License
