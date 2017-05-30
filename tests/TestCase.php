<?php

namespace Yajra\Datatables\Tests;

use Illuminate\Database\Schema\Blueprint;
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
    }

    protected function seedDatabase()
    {
        collect(range(1, 20))->each(function ($i) {
            /** @var User $user */
            $user = User::query()->create([
                'name'  => 'Record-' . $i,
                'email' => 'Email-' . $i,
            ]);

            collect(range(1, 3))->each(function ($i) use ($user) {
                $user->posts()->create([
                    'title' => "User-{$user->id} Post-{$i}",
                ]);
            });
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
