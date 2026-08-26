<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;
use Yajra\DataTables\Utilities\Request;

class MaxLengthTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_all_records_when_no_max_length_is_set()
    {
        $response = $this->call('GET', '/max-length');

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertCount(20, $response->json()['data']);
    }

    #[Test]
    public function it_caps_a_request_without_a_length()
    {
        config(['datatables.max_length' => 5]);

        $response = $this->call('GET', '/max-length');

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 20,
            'recordsFiltered' => 20,
        ]);

        $this->assertCount(5, $response->json()['data']);
    }

    #[Test]
    public function it_caps_a_request_asking_for_all_the_records()
    {
        config(['datatables.max_length' => 5]);

        $response = $this->call('GET', '/max-length', ['start' => 0, 'length' => -1]);

        $this->assertCount(5, $response->json()['data']);
    }

    #[Test]
    public function it_caps_a_length_that_is_over_the_maximum()
    {
        config(['datatables.max_length' => 5]);

        $response = $this->call('GET', '/max-length', ['start' => 0, 'length' => 100]);

        $this->assertCount(5, $response->json()['data']);
    }

    #[Test]
    public function it_keeps_a_length_that_is_below_the_maximum()
    {
        config(['datatables.max_length' => 5]);

        $response = $this->call('GET', '/max-length', ['start' => 0, 'length' => 2]);

        $this->assertCount(2, $response->json()['data']);
    }

    #[Test]
    public function it_still_paginates_from_the_requested_start()
    {
        config(['datatables.max_length' => 5]);

        $response = $this->call('GET', '/max-length', ['start' => 5, 'length' => -1]);

        $data = $response->json()['data'];

        $this->assertCount(5, $data);
        $this->assertEquals('Record-6', $data[0]['name']);
    }

    #[Test]
    public function it_can_ignore_the_maximum_on_the_data_table()
    {
        config(['datatables.max_length' => 5]);

        $this->app['router']->get('/max-length-ignored', fn (DataTables $datatables) => $datatables
            ->eloquent(User::query())
            ->ignoreMaxLength()
            ->toJson());

        $response = $this->call('GET', '/max-length-ignored', ['start' => 0, 'length' => -1]);

        $this->assertCount(20, $response->json()['data']);
    }

    #[Test]
    public function it_can_ignore_the_maximum_on_the_request()
    {
        config(['datatables.max_length' => 5]);

        // This is how an export gets every filtered record, as done by
        // laravel-datatables-buttons before calling the ajax response.
        app('datatables.request')->ignoreMaxLength();

        $response = $this->call('GET', '/max-length', ['start' => 0, 'length' => -1]);

        $this->assertCount(20, $response->json()['data']);
    }

    #[Test]
    public function it_does_not_keep_ignoring_the_maximum_on_the_next_request()
    {
        config(['datatables.max_length' => 5]);

        app('datatables.request')->ignoreMaxLength();

        // A long running worker, e.g. Octane, reuses the container between
        // requests, so the flag must not survive into the next one.
        $this->app->forgetScopedInstances();

        $response = $this->call('GET', '/max-length', ['start' => 0, 'length' => -1]);

        $this->assertCount(5, $response->json()['data']);
    }

    #[Test]
    public function it_resolves_the_same_request_instance_from_the_class_name()
    {
        // The request has to be shared, otherwise ignoreMaxLength() would be
        // set on another instance than the one used by the engines.
        $this->assertSame(app('datatables.request'), app(Request::class));
    }

    #[Test]
    public function it_can_ignore_the_maximum_through_the_request_class_name()
    {
        config(['datatables.max_length' => 5]);

        app(Request::class)->ignoreMaxLength();

        $response = $this->call('GET', '/max-length', ['start' => 0, 'length' => -1]);

        $this->assertCount(20, $response->json()['data']);
    }

    #[Test]
    public function it_caps_a_collection_data_table_as_well()
    {
        config(['datatables.max_length' => 5]);

        $this->app['router']->get('/max-length-collection', fn (DataTables $datatables) => $datatables
            ->collection(User::all())
            ->toJson());

        $response = $this->call('GET', '/max-length-collection', ['start' => 0, 'length' => -1]);

        $this->assertCount(5, $response->json()['data']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/max-length', fn (DataTables $datatables) => $datatables
            ->eloquent(User::query())
            ->toJson());
    }
}
