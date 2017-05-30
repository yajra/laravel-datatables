<?php

namespace Yajra\Datatables\Tests;

use Illuminate\Database\Schema\Blueprint;
use Yajra\Datatables\Tests\Models\Role;
use Yajra\Datatables\Tests\Models\User;

abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->migrateDatabase();

        $this->seedDatabase();
    }

    protected function migrateDatabase()
    {
        /** @var \Illuminate\Database\Schema\Builder $schemaBuilder */
        $schemaBuilder = $this->app['db']->connection()->getSchemaBuilder();
        $schemaBuilder->create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });
        $schemaBuilder->create('posts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });
        $schemaBuilder->create('hearts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('size');
            $table->timestamps();
        });
        $schemaBuilder->create('roles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('role');
            $table->timestamps();
        });
        $schemaBuilder->create('role_user', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();
        });
    }

    protected function seedDatabase()
    {
        $adminRole = Role::create(['role' => 'Administrator']);
        $userRole  = Role::create(['role' => 'User']);

        collect(range(1, 20))->each(function ($i) use ($adminRole, $userRole) {
            /** @var User $user */
            $user = User::query()->create([
                'name'  => 'Record-' . $i,
                'email' => 'Email-' . $i . '@example.com',
            ]);

            collect(range(1, 3))->each(function ($i) use ($user) {
                $user->posts()->create([
                    'title' => "User-{$user->id} Post-{$i}",
                ]);
            });

            $user->heart()->create([
                'size' => 'heart-' . $user->id,
            ]);

            if ($i % 2) {
                $user->roles()->attach(Role::all());
            } else {
                $user->roles()->attach($userRole);
            }
        });
    }

    /**
     * Set up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.debug', true);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Yajra\Datatables\DatatablesServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Datatables' => \Yajra\Datatables\Facades\Datatables::class,
        ];
    }
}
