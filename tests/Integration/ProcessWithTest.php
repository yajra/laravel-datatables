<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class ProcessWithTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_runs_the_callback_on_each_row()
    {
        $processed = 0;

        $this->app['router']->get('/process-with', function (DataTables $datatables) use (&$processed) {
            return $datatables->eloquent(User::query())
                ->processWith(function (User $user) use (&$processed) {
                    $processed++;
                    $user->name = 'Processed-'.$user->getKey();
                })
                ->toJson();
        });

        $response = $this->call('GET', '/process-with');

        $this->assertEquals(20, $processed);
        $this->assertCount(20, $response->json()['data']);

        foreach ($response->json()['data'] as $row) {
            $this->assertEquals('Processed-'.$row['id'], $row['name']);
        }
    }

    #[Test]
    public function it_does_not_query_the_database_for_a_relation_set_via_process_with()
    {
        $user = User::query()->firstOrFail();

        $this->app['router']->get('/process-with-relation', fn (DataTables $datatables) => $datatables->eloquent(Post::query())
            ->processWith(fn (Post $post) => $post->setRelation('user', $user))
            ->addColumn('user_name', fn (Post $post) => $post->user->name)
            ->toJson());

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $response = $this->call('GET', '/process-with-relation');

        $this->assertCount(60, $response->json()['data']);
        $this->assertEquals($user->name, $response->json()['data'][0]['user_name']);
        $this->assertEmpty(array_filter($queries, fn ($sql) => str_contains($sql, '"users"')));
    }

    #[Test]
    public function it_can_process_rows_of_a_query_data_table()
    {
        $this->app['router']->get('/process-with-query', fn (DataTables $datatables) => $datatables->query(DB::table('users'))
            ->processWith(function (\stdClass $user) {
                $user->name = strtoupper((string) $user->name);
            })
            ->toJson());

        $response = $this->call('GET', '/process-with-query');

        $this->assertCount(20, $response->json()['data']);

        foreach ($response->json()['data'] as $row) {
            $this->assertEquals(strtoupper((string) $row['name']), $row['name']);
            $this->assertStringStartsWith('RECORD-', $row['name']);
        }
    }

    #[Test]
    public function it_can_process_rows_of_a_collection_data_table()
    {
        $this->app['router']->get('/process-with-collection', fn (DataTables $datatables) => $datatables->collection(User::all())
            ->processWith(function (User $user) {
                $user->name = 'Processed-'.$user->getKey();
            })
            ->toJson());

        $response = $this->call('GET', '/process-with-collection');

        $this->assertCount(20, $response->json()['data']);

        foreach ($response->json()['data'] as $row) {
            $this->assertEquals('Processed-'.$row['id'], $row['name']);
        }
    }

    #[Test]
    public function it_is_not_called_when_not_set()
    {
        $this->app['router']->get('/process-with-none', fn (DataTables $datatables) => $datatables->eloquent(User::query())->toJson());

        $response = $this->call('GET', '/process-with-none');

        $this->assertCount(20, $response->json()['data']);
        $this->assertEquals('Record-1', $response->json()['data'][0]['name']);
    }
}
