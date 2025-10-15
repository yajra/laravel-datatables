<?php

/**
 * Example usage of accent-insensitive search in Laravel DataTables
 * 
 * This example shows how to use the accent-insensitive search feature
 * to handle Portuguese Brazilian accents.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use DataTables;
use App\Models\User;

class UserController extends Controller
{
    /**
     * Example 1: Basic Eloquent DataTable with accent-insensitive search
     */
    public function getUsersData(Request $request)
    {
        // Make sure ignore_accents is enabled in config/datatables.php:
        // 'search' => ['ignore_accents' => true]
        
        return DataTables::of(User::query())
            ->addColumn('action', function ($user) {
                return '<button class="btn btn-sm btn-primary">View</button>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Example 2: Collection DataTable with accent-insensitive search
     */
    public function getBrazilianCitiesData()
    {
        $cities = collect([
            ['id' => 1, 'name' => 'São Paulo', 'state' => 'SP'],
            ['id' => 2, 'name' => 'João Pessoa', 'state' => 'PB'],
            ['id' => 3, 'name' => 'Ribeirão Preto', 'state' => 'SP'],
            ['id' => 4, 'name' => 'Florianópolis', 'state' => 'SC'],
            ['id' => 5, 'name' => 'Maceió', 'state' => 'AL'],
            ['id' => 6, 'name' => 'São Luís', 'state' => 'MA'],
        ]);

        return DataTables::of($cities)->make(true);
    }

    /**
     * Example 3: Query Builder with accent-insensitive search
     */
    public function getEmployeesData()
    {
        $query = DB::table('employees')
            ->select(['id', 'name', 'department', 'position'])
            ->where('active', true);

        return DataTables::of($query)
            ->addColumn('formatted_name', function ($employee) {
                return ucwords(strtolower($employee->name));
            })
            ->make(true);
    }
}

/**
 * Example Blade template for the DataTable
 */
?>

{{-- resources/views/users/index.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <title>Users with Accent-Insensitive Search</title>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.25/css/jquery.dataTables.css">
    <script type="text/javascript" charset="utf8" src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.js"></script>
</head>
<body>
    <div class="container">
        <h1>Users - Accent-Insensitive Search Example</h1>
        
        <p>Try searching for:</p>
        <ul>
            <li><strong>simoes</strong> to find "Simões"</li>
            <li><strong>joao</strong> to find "João"</li>
            <li><strong>sao paulo</strong> to find "São Paulo"</li>
            <li><strong>jose</strong> to find "José"</li>
        </ul>

        <table id="users-table" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>City</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>

    <script>
        $(document).ready(function() {
            $('#users-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{!! route('users.data') !!}',
                columns: [
                    {data: 'id', name: 'id'},
                    {data: 'name', name: 'name'},
                    {data: 'email', name: 'email'},
                    {data: 'city', name: 'city'},
                    {data: 'action', name: 'action', orderable: false, searchable: false},
                ]
            });
        });
    </script>
</body>
</html>

<?php
/**
 * Example Migration for creating test data with accented names
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('city');
            $table->timestamps();
        });

        // Insert sample data with Portuguese accents
        DB::table('users')->insert([
            ['name' => 'João Silva', 'email' => 'joao@example.com', 'city' => 'São Paulo'],
            ['name' => 'María Santos', 'email' => 'maria@example.com', 'city' => 'Rio de Janeiro'],
            ['name' => 'José Oliveira', 'email' => 'jose@example.com', 'city' => 'Belo Horizonte'],
            ['name' => 'Ana Conceição', 'email' => 'ana@example.com', 'city' => 'Salvador'],
            ['name' => 'Paulo Ribeirão', 'email' => 'paulo@example.com', 'city' => 'Ribeirão Preto'],
            ['name' => 'Tatiane Simões', 'email' => 'tatiane@example.com', 'city' => 'João Pessoa'],
            ['name' => 'Carlos São', 'email' => 'carlos@example.com', 'city' => 'São Luís'],
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
};

/**
 * Example Routes
 */

// routes/web.php
Route::get('/users', [UserController::class, 'index'])->name('users.index');
Route::get('/users/data', [UserController::class, 'getUsersData'])->name('users.data');
Route::get('/cities/data', [UserController::class, 'getBrazilianCitiesData'])->name('cities.data');

/**
 * Configuration Example
 */

// config/datatables.php
return [
    'search' => [
        'smart' => true,
        'multi_term' => true,
        'case_insensitive' => true,
        'use_wildcards' => false,
        'starts_with' => false,
        'ignore_accents' => true, // <-- Enable accent-insensitive search
    ],
    // ... rest of configuration
];