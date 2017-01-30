<?php

namespace Test;

use Illuminate\Database\Schema\Blueprint;
use Test\Models\User;
use Yajra\Datatables\Request;

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
    }

    protected function seedDatabase()
    {
        collect(range(1, 20))->each(function ($i) {
            User::forceCreate([
                'name'  => 'Record ' . $i,
                'email' => 'Email ' . $i,
            ]);
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
