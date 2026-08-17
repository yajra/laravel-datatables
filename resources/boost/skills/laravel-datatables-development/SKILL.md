---
name: laravel-datatables-development
description: Build and work with Yajra Laravel DataTables across Bootstrap, Tailwind, Livewire, and Vite/Webpack stacks — including server-side processing, DataTable service classes, HTML builder, export buttons, queued exports, Fractal transformers, and column manipulation.
---

# Laravel DataTables Development

## When to use this skill

Use this skill when working with server-side DataTables, AJAX tables, `DataTable` service classes, the HTML builder, export/queue plugins, Livewire integration, or any code using `Yajra\DataTables` with yajra/laravel-datatables.

## Core Concepts

- Laravel DataTables bridges **jQuery DataTables server-side processing** with Laravel's Eloquent, Query Builder, or Collection APIs.
- The **core package** (`yajra/laravel-datatables-oracle`) handles JSON responses; optional plugins add HTML builder, buttons, export, Fractal, and Editor support.
- Prefer **service classes** (`php artisan datatables:make`) over inline route closures for maintainable tables.
- Use **`addColumn`** for computed columns; use **`editColumn`** when modifying existing database columns that need search/sort.
- **Pick one frontend stack** (Bootstrap, Tailwind, or minimal/unstyled) and match CSS/JS imports — do not mix Bootstrap DataTables CSS with Tailwind layouts.

## Package Architecture

| Package | Purpose |
| --- | --- |
| `yajra/laravel-datatables-oracle` | Core server-side engine (required) |
| `yajra/laravel-datatables-html` | HTML builder and script generation |
| `yajra/laravel-datatables-buttons` | Excel, CSV, PDF, print buttons |
| `yajra/laravel-datatables-export` | Queued exports via Livewire or JS button |
| `yajra/laravel-datatables-fractal` | Fractal API response transformers |
| `yajra/laravel-datatables-editor` | DataTables Editor (premium license) |
| `yajra/laravel-datatables` | All-in-one meta package |

## Choose Your Stack

| Stack | When to use | Key setup |
| --- | --- | --- |
| **Bootstrap 5 + Vite** | Default; official quick starter | `laravel-datatables-vite`, `datatables.net-bs5` CSS |
| **Tailwind CSS** | Tailwind-first apps (Breeze, Filament-adjacent UIs) | Base `datatables.net` + Tailwind table classes; or `gorlabs/tailwind-datatables` |
| **Livewire** | Livewire layouts, queued export buttons, dynamic table chrome | `drawCallbackWithLivewire()`, `Layout::addLivewire()`, `<livewire:export-button>` |
| **Webpack / Mix** | Legacy asset pipeline | `Builder::useWebpack()` instead of `useVite()` |
| **API / JSON only** | Headless DataTables, SPA, mobile apps | `DataTables::eloquent(...)->toJson()` without HTML builder |

## Installation

```bash
# Core only
composer require yajra/laravel-datatables-oracle:"^13.0"

# All-in-one (HTML builder, buttons, export, fractal)
composer require yajra/laravel-datatables:"^13.0"

# Optional: publish configuration
php artisan vendor:publish --tag=datatables
php artisan vendor:publish --tag=datatables-html
```

Register Vite module scripts globally in `AppServiceProvider`:

```php
use Yajra\DataTables\Html\Builder;

public function boot(): void
{
    Builder::useVite(); // or Builder::useWebpack() for Mix/Webpack
}
```

## Basic Server-Side Processing

```php
use Yajra\DataTables\Facades\DataTables;
use App\Models\User;

return DataTables::eloquent(User::query())->toJson();
return DataTables::query(DB::table('users'))->toJson();
return DataTables::collection(User::all())->toJson(); // small datasets only
return DataTables::make(User::query())->toJson();    // auto-detects source
```

## DataTable Service Class Pattern

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
use Yajra\DataTables\Html\Enums\LayoutPosition;
use Yajra\DataTables\Html\Layout;
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
            ->drawCallbackWithLivewire() // omit if not using Livewire
            ->layout(function (Layout $layout) {
                $layout->topStart('buttons');
                $layout->topEnd('search');
                $layout->bottomStart('info');
                $layout->bottomEnd('paging');
            })
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

---

## Frontend Variants

### Bootstrap 5 + Vite (official default)

```bash
npm i -D laravel-datatables-vite bootstrap @popperjs/core bootstrap-icons
```

```js
// resources/js/app.js
import './bootstrap';
import 'bootstrap';
import 'laravel-datatables-vite';
```

```css
/* resources/css/app.css */
@import 'bootstrap/dist/css/bootstrap.min.css';
@import 'bootstrap-icons/font/bootstrap-icons.css';
@import 'datatables.net-bs5/css/dataTables.bootstrap5.min.css';
@import 'datatables.net-buttons-bs5/css/buttons.bootstrap5.min.css';
@import 'datatables.net-select-bs5/css/select.bootstrap5.css';
```

Use Bootstrap pagination alongside tables:

```php
use Illuminate\Pagination\Paginator;

Paginator::useBootstrapFive();
```

Set the DataTables 2.x renderer (default is `bootstrap`):

```php
$this->builder()->renderer('bootstrap');
```

### Tailwind CSS

Yajra's official Vite helper targets Bootstrap. For Tailwind apps, use one of these approaches:

**Option A — Base DataTables + Tailwind classes (manual)**

```bash
npm i -D datatables.net datatables.net-buttons jquery
```

Do **not** import `datatables.net-bs5` CSS. Style the table with Tailwind utilities:

```php
$this->builder()
    ->setTableAttribute('class', 'min-w-full divide-y divide-gray-200 dark:divide-gray-700')
    ->columns([
        Column::make('name')->addClass('px-4 py-2 text-left text-sm font-medium text-gray-700'),
    ]);
```

Use Tailwind classes in `addColumn` / `editColumn` HTML (badges, buttons, links).

**Option B — Community Tailwind package**

For a Tailwind + Alpine.js native UI on top of Yajra's server-side engine:

```bash
composer require gorlabs/tailwind-datatables
```

Use when the project already uses Tailwind and you want pre-built Tailwind table chrome instead of Bootstrap styling.

### Webpack / Laravel Mix

```php
Builder::useWebpack(); // scripts output as text/javascript
```

```blade
{{ $dataTable->scripts() }} {{-- no module type --}}
```

### API-only (no HTML builder)

Skip service class `html()` entirely — return JSON from a dedicated route:

```php
Route::get('users/data', fn () => DataTables::eloquent(User::query())->toJson());
```

Wire up a frontend DataTables instance (React, Vue, Alpine) against that endpoint.

---

## Livewire Integration

Requires `livewire/livewire` (^3.4 or ^4.x). The HTML builder package supports Livewire natively.

### Rescan Livewire after table redraw

Call on tables inside Livewire components so wire: directives re-bind after pagination/sort:

```php
$this->builder()->drawCallbackWithLivewire();
```

### Embed Livewire components in table layout

```php
use Yajra\DataTables\Html\Enums\LayoutPosition;

$this->builder()->layout(function (Layout $layout) {
    $layout->addLivewire('filters.user-filter', LayoutPosition::TopStart);
    $layout->topEnd('search');
});
```

### Queued export via Livewire button

Requires `yajra/laravel-datatables-export` and a running queue worker:

```php
use Yajra\DataTables\WithExportQueue;

class UsersDataTable extends DataTable
{
    use WithExportQueue;
}
```

```blade
{{ $dataTable->table() }}
<livewire:export-button :table-id="$dataTable->getTableId()" filename="users.xlsx" type="xlsx" />
<livewire:export-button :table-id="$dataTable->getTableId()" type="csv" buttonName="Export CSV" />

@push('scripts')
    {{ $dataTable->scripts(attributes: ['type' => 'module']) }}
@endpush
```

```bash
php artisan queue:batches-table && php artisan migrate
php artisan queue:work
```

Schedule export file cleanup:

```php
$schedule->command('datatables:purge-export')->weekly();
```

### Queued export via JS button (no Livewire)

```bash
php artisan vendor:publish --tag=datatables-export
```

```html
<script src="/vendor/datatables/dataTables.queuedExport.js"></script>
```

```php
Button::make([
    'extend' => 'queuedExport',
    'text' => 'Export Excel',
    'exportType' => 'xlsx',
    'filename' => 'users.xlsx',
    'sheetName' => 'Users',
    'autoDownload' => true,
]),
```

---

## Column Manipulation

### addColumn (computed — search/sort disabled by default)

```php
return DataTables::eloquent(User::query())
    ->addColumn('full_name', fn (User $user) => $user->first_name.' '.$user->last_name)
    ->addColumn('action', fn (User $user) => '<a href="'.route('users.edit', $user).'">Edit</a>')
    ->rawColumns(['action'])
    ->toJson();
```

### editColumn (modify DB columns — search/sort enabled)

```php
return DataTables::eloquent(User::query())
    ->editColumn('created_at', fn (User $user) => $user->created_at->format('M d, Y'))
    ->editColumn('status', function (User $user) {
        // Tailwind badge example
        $classes = $user->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';

        return '<span class="px-2 py-1 rounded text-xs '.$classes.'">'.$user->status.'</span>';
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

```php
return DataTables::eloquent(User::query())
    ->filterColumn('full_name', function ($query, $keyword) {
        $query->where(function ($q) use ($keyword) {
            $q->where('first_name', 'like', "%{$keyword}%")
                ->orWhere('last_name', 'like', "%{$keyword}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$keyword}%"]);
        });
    })
    ->toJson();
```

## Eloquent Relationships

```php
return DataTables::eloquent(User::with('posts'))
    ->addColumn('posts', fn (User $user) => $user->posts
        ->map(fn ($post) => Str::limit($post->title, 30))
        ->implode('<br>'))
    ->rawColumns(['posts'])
    ->toJson();
```

Use `name: 'posts.title'` in JS column config for relationship search; `data: 'posts'` is the display key.

When joining or aliasing, always `select('table.*')` to prevent ID column conflicts.

## Optional Plugins

### Buttons (client-side export)

Included in all-in-one package. Requires matching CSS for your UI framework (Bootstrap buttons CSS or custom Tailwind button markup in views).

```php
->buttons([
    Button::make('excel'),
    Button::make('csv'),
    Button::make('pdf'),
    Button::make('print'),
])
```

### Fractal (API transformers)

```bash
composer require yajra/laravel-datatables-fractal:"^13.0"
```

```php
return DataTables::eloquent(User::query())
    ->setTransformer(new UserTransformer)
    ->toJson();
```

Generate transformers: `php artisan datatables:transformer User`

### Editor (premium license)

```php
use Yajra\DataTables\Html\Editor\Editor;

Editor::make()
    ->display(Editor::DISPLAY_BOOTSTRAP)   // bootstrap (default)
    // ->display(Editor::DISPLAY_FOUNDATION)
    // ->display(Editor::DISPLAY_JQUERYUI)
    ->fields([...]);
```

## Performance

- Always **eager load** relationships used in column closures: `User::with('posts')`.
- Select only needed columns: `User::select(['id', 'name', 'email'])`.
- Use `withCount()` instead of counting in closures.
- Use `filterColumn()` instead of loading all records into memory.
- Prefer Eloquent engine over Collection engine for large datasets.

## Do and Don't

Do:
- Match frontend CSS to your UI framework — Bootstrap CSS for Bootstrap apps, Tailwind classes for Tailwind apps.
- Use `DataTable` service classes for reusable, testable table definitions.
- Call `->rawColumns([...])` when columns contain HTML.
- Use `Column::computed()` for action columns in the HTML builder.
- Call `drawCallbackWithLivewire()` when the table lives inside a Livewire component.
- Use Valet or Herd for local development instead of `php artisan serve`.
- Include `@stack('scripts')` in the layout when using `$dataTable->scripts()`.
- Run `php artisan queue:work` when using queued exports.

Don't:
- Don't mix Bootstrap DataTables CSS (`datatables.net-bs5`) with Tailwind-styled layouts.
- Don't use `addColumn` when you need search/sort on a database field — use `editColumn`.
- Don't forget `select('table.*')` when joining or aliasing tables.
- Don't use the Collection engine for large datasets.
- Don't use `php artisan serve` with authenticated DataTables — known redirect/401 issues; use Valet or Herd.
