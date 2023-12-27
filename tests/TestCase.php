<?php

namespace Yajra\DataTables\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Yajra\DataTables\Tests\Models\AnimalUser;
use Yajra\DataTables\Tests\Models\HumanUser;
use Yajra\DataTables\Tests\Models\Role;
use Yajra\DataTables\Tests\Models\User;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateDatabase();

        $this->seedDatabase();
    }

    protected function migrateDatabase()
    {
        /** @var \Illuminate\Database\Schema\Builder $schemaBuilder */
        $schemaBuilder = $this->app['db']->connection()->getSchemaBuilder();
        if (! $schemaBuilder->hasTable('users')) {
            $schemaBuilder->create('users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->string('email');
                $table->string('color')->nullable();
                $table->string('user_type')->nullable();
                $table->unsignedInteger('user_id')->nullable();
                $table->timestamps();
            });
        }
        if (! $schemaBuilder->hasTable('posts')) {
            $schemaBuilder->create('posts', function (Blueprint $table) {
                $table->increments('id');
                $table->string('title');
                $table->unsignedInteger('user_id');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! $schemaBuilder->hasTable('hearts')) {
            $schemaBuilder->create('hearts', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('user_id');
                $table->string('size');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! $schemaBuilder->hasTable('roles')) {
            $schemaBuilder->create('roles', function (Blueprint $table) {
                $table->increments('id');
                $table->string('role');
                $table->timestamps();
            });
        }
        if (! $schemaBuilder->hasTable('role_user')) {
            $schemaBuilder->create('role_user', function (Blueprint $table) {
                $table->unsignedInteger('role_id');
                $table->unsignedInteger('user_id');
                $table->timestamps();
            });
        }
        if (! $schemaBuilder->hasTable('animal_users')) {
            $schemaBuilder->create('animal_users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (! $schemaBuilder->hasTable('human_users')) {
            $schemaBuilder->create('human_users', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name');
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    protected function seedDatabase()
    {
        $adminRole = Role::create(['role' => 'Administrator']);
        $userRole = Role::create(['role' => 'User']);
        $animal = AnimalUser::create(['name' => 'Animal']);
        $human = HumanUser::create(['name' => 'Human']);

        collect(range(1, 20))->each(function ($i) use ($userRole, $animal, $human) {
            /** @var User $user */
            $user = User::query()->create([
                'name' => 'Record-'.$i,
                'email' => 'Email-'.$i.'@example.com',
            ]);

            collect(range(1, 3))->each(function ($i) use ($user) {
                $user->posts()->create([
                    'title' => "User-{$user->id} Post-{$i}",
                ]);
            });

            $user->heart()->create([
                'size' => 'heart-'.$user->id,
            ]);

            if ($i % 2) {
                $user->roles()->attach(Role::all());
                $human->users()->save($user);
            } else {
                $user->roles()->attach($userRole);
                $animal->users()->save($user);
            }
        });
    }

    /**
     * Set up the environment.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.debug', true);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Yajra\DataTables\DataTablesServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'DataTables' => \Yajra\DataTables\Facades\DataTables::class,
        ];
    }
}
