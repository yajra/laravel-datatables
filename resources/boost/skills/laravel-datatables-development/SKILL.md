---
name: laravel-datatables-development
description: Build and work with Yajra Laravel DataTables features, including server-side processing, DataTable service classes, column manipulation, custom filtering, Eloquent relationships, and HTML builder integration.
---

# Laravel DataTables Development

## When to use this skill

Use this skill when working with server-side DataTables, AJAX-powered tables, `DataTable` service classes, the HTML builder, export buttons, or any code using `Yajra\DataTables` with yajra/laravel-datatables.

## Core Concepts

- Laravel DataTables bridges **jQuery DataTables server-side processing** with Laravel's Eloquent, Query Builder, or Collection APIs.
- The **core package** (`yajra/laravel-datatables-oracle`) handles JSON responses; optional plugins add HTML builder, buttons, export, and editor support.
- Prefer **service classes** (`php artisan datatables:make`) over inline route closures for maintainable tables.
- Use **`addColumn`** for computed columns; use **`editColumn`** when modifying existing database columns that need search/sort.

## Installation

```bash
# Core only
composer require yajra/laravel-datatables-oracle:"^13.0"

# All-in-one (includes HTML builder, buttons, and common plugins)
composer require yajra/laravel-datatables:"^13.0"

# Optional: publish configuration
php artisan vendor:publish --tag=datatables
```

For frontend assets with Vite:

```bash
npm i -D laravel-datatables-vite bootstrap @popperjs/core bootstrap-icons
```

## Basic Server-Side Processing

```php
use Yajra\DataTables\Facades\DataTables;
use App\Models\User;

// Eloquent (preferred for models)
return DataTables::eloquent(User::query())->toJson();

// Query builder
return DataTables::query(DB::table('users'))->toJson();

// Collection (small datasets only)
return DataTables::collection(User::all())->toJson();

// Unified API (auto-detects source type)
return DataTables::make(User::query())->toJson();
```

## DataTable Service Class Pattern

Generate a service class:

```bash
php artisan datatables:make Users
```

```php
<?php

namespace App\DataTables;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder as QueryBuilder;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Html\Builder as HtmlBuilder;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Yajra\DataTables\Services\DataTable;

class UsersDataTable extends DataTable
{
    public function dataTable(QueryBuilder $query): EloquentDataTable
    {
        return (new EloquentDataTable($query))
            ->addColumn('action', fn (User $user) => view('users.partials.actions', compact('user')))
            ->rawColumns(['action'])
            ->setRowId('id');
    }

    public function query(User $model): QueryBuilder
    {
        return $model->newQuery();
    }

    public function html(): HtmlBuilder
    {
        return $this->builder()
            ->setTableId('users-table')
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->orderBy(1)
            ->buttons([
                Button::make('excel'),
                Button::make('csv'),
                Button::make('pdf'),
                Button::make('print'),
                Button::make('reset'),
                Button::make('reload'),
            ]);
    }

    public function getColumns(): array
    {
        return [
            Column::make('name'),
            Column::make('email'),
            Column::make('created_at'),
            Column::computed('action')
                ->exportable(false)
                ->printable(false)
                ->width(60)
                ->addClass('text-center'),
        ];
    }

    protected function filename(): string
    {
        return 'Users_'.date('YmdHis');
    }
}
```

Controller and Blade:

```php
public function index(UsersDataTable $dataTable)
{
    return $dataTable->render('users.index');
}
```

```blade
{{ $dataTable->table() }}

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
```

The layout must include `@stack('scripts')` before `</body>` and load Vite assets.

## Column Manipulation

### addColumn (computed columns — search/sort disabled by default)

```php
return DataTables::eloquent(User::query())
    ->addColumn('full_name', fn (User $user) => $user->first_name.' '.$user->last_name)
    ->addColumn('action', fn (User $user) => '<a href="'.route('users.edit', $user).'">Edit</a>')
    ->rawColumns(['action'])
    ->toJson();
```

### editColumn (modify existing DB columns — search/sort enabled)

```php
return DataTables::eloquent(User::query())
    ->editColumn('created_at', fn (User $user) => $user->created_at->format('M d, Y'))
    ->editColumn('status', function (User $user) {
        $color = $user->is_active ? 'success' : 'secondary';

        return '<span class="badge bg-'.$color.'">'.$user->status.'</span>';
    })
    ->rawColumns(['status'])
    ->toJson();
```

| Feature | `addColumn` | `editColumn` |
| --- | --- | --- |
| Search | Disabled | Enabled |
| Sort | Disabled | Enabled |
| Database column | Not required | Required |
| Use case | Computed values, actions | Formatting existing fields |

## Custom Column Filtering

Use `filterColumn()` for custom search logic on computed or aliased columns:

```php
return DataTables::eloquent(User::query())
    ->filterColumn('full_name', function ($query, $keyword) {
        $query->where(function ($q) use ($keyword) {
            $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$keyword}%"]);
        });
    })
    ->filterColumn('created_at', fn ($query, $keyword) => $query->whereDate('created_at', Carbon::parse($keyword)))
    ->toJson();
```

## Eloquent Relationships

Eager load relationships and use dot notation for search/sort:

```php
return DataTables::eloquent(User::with('posts'))
    ->addColumn('posts', fn (User $user) => $user->posts
        ->map(fn ($post) => Str::limit($post->title, 30))
        ->implode('<br>'))
    ->rawColumns(['posts'])
    ->toJson();
```

In JavaScript column config, use `name: 'posts.title'` for relationship search while `data: 'posts'` is the display key.

When using table aliases, always include `select('table.*')` to prevent ID column conflicts:

```php
$model = Post::with('user')->select('posts.*');

return DataTables::eloquent($model)->toJson();
```

## HTML Builder

Requires `yajra/laravel-datatables-html` (included in the all-in-one package):

```php
use Yajra\DataTables\Html\Column;

$html = $builder
    ->columns([
        Column::make('name'),
        Column::make('email'),
        Column::computed('action')->orderable(false)->searchable(false),
    ])
    ->minifiedAjax()
    ->orderBy(1)
    ->selectStyleSingle();
```

For Vite projects, set the script type globally:

```php
use Yajra\DataTables\Html\Builder;

Builder::useVite();
```

## Frontend Setup (Vite)

```js
// resources/js/app.js
import './bootstrap';
import 'bootstrap';
import 'laravel-datatables-vite';
```

```css
/* resources/css/app.css */
@import 'bootstrap/dist/css/bootstrap.min.css';
@import "datatables.net-bs5/css/dataTables.bootstrap5.min.css";
@import "datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css";
```

## Performance

- Always **eager load** relationships used in column closures: `User::with('posts')`.
- Select only needed columns when possible: `User::select(['id', 'name', 'email'])`.
- Avoid N+1 queries inside `addColumn` / `editColumn` closures — load counts with `withCount()` instead.
- Use `filterColumn()` instead of loading all records into memory.
- Prefer Eloquent engine over Collection engine for large datasets.

## Do and Don't

Do:
- Use `DataTable` service classes for reusable, testable table definitions.
- Call `->rawColumns([...])` when columns contain HTML.
- Use `Column::computed()` for action columns in the HTML builder.
- Use Valet or Herd for local development instead of `php artisan serve`.
- Include `@stack('scripts')` in the layout when using `$dataTable->scripts()`.

Don't:
- Don't use `addColumn` when you need search/sort on a database field — use `editColumn` instead.
- Don't forget `select('table.*')` when joining or aliasing tables.
- Don't use the Collection engine for large datasets — use Eloquent or Query builder.
- Don't use `php artisan serve` when developing DataTables with authentication — known redirect/401 issues exist; use Valet or Herd.
