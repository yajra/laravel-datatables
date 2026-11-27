<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class GlobalSearchBareColumnReproTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $router = $this->app['router'];

        $router->get('/repro/join', fn (DataTables $dataTable) => $dataTable->query(
            DB::table('posts')
                ->join('users', 'users.id', '=', 'posts.user_id')
                ->select('posts.id', 'users.name', 'posts.title')
        )->toJson());

        $router->get('/repro/eloquent-join', fn () => DataTables::of(
            User::query()
                ->join('posts', 'posts.user_id', '=', 'users.id')
                ->select('users.id', 'posts.title')
        )->toJson());

        $router->get('/repro/self-join', fn (DataTables $dataTable) => $dataTable->query(
            DB::table('users as u1')
                ->join('users as u2', 'u2.id', '=', 'u1.id')
                ->select('u1.id', 'u2.name')
        )->toJson());
    }

    protected function capturedSearchSql(callable $request): string
    {
        $sql = '';

        DB::connection()->pretend(function ($connection) use (&$sql, $request) {
            $request();

            $sql = collect(DB::getConnections())
                ->flatMap(fn ($c) => $c->getQueryLog())
                ->pluck('query')
                ->filter(fn ($query) => str_contains($query, 'LIKE'))
                ->implode(' || ');
        });

        return $sql;
    }

    #[Test]
    public function global_search_resolves_bare_column_against_the_select_list()
    {
        $sql = $this->capturedSearchSql(fn () => $this->call('GET', '/repro/join', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record-19'],
        ]));

        $this->assertStringContainsString('"users"."name") LIKE', $sql);
        $this->assertStringNotContainsString('"posts"."name") LIKE', $sql);
    }

    #[Test]
    public function global_search_with_a_bare_joined_column_works()
    {
        $crawler = $this->call('GET', '/repro/join', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record-19'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertNull($crawler->json('error'));
    }

    #[Test]
    public function global_search_with_a_bare_joined_column_works_on_eloquent_queries()
    {
        $crawler = $this->call('GET', '/repro/eloquent-join', [
            'columns' => [
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'User-19 Post-2'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 1,
        ]);

        $this->assertNull($crawler->json('error'));
    }

    #[Test]
    public function global_search_with_a_qualified_column_works()
    {
        $crawler = $this->call('GET', '/repro/join', [
            'columns' => [
                ['data' => 'name', 'name' => 'users.name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record-19'],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }

    #[Test]
    public function per_column_search_with_a_bare_joined_column_works()
    {
        $crawler = $this->call('GET', '/repro/join', [
            'columns' => [
                [
                    'data' => 'name',
                    'name' => 'name',
                    'searchable' => 'true',
                    'orderable' => 'true',
                    'search' => ['value' => 'Record-19'],
                ],
            ],
        ]);

        $crawler->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);
    }

    #[Test]
    public function global_search_binds_a_bare_column_to_the_declared_table_alias()
    {
        $sql = $this->capturedSearchSql(fn () => $this->call('GET', '/repro/self-join', [
            'columns' => [
                ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true'],
            ],
            'search' => ['value' => 'Record-19'],
        ]));

        $this->assertStringContainsString('"u2"."name") LIKE', $sql);
        $this->assertStringNotContainsString('"u1"."name") LIKE', $sql);
    }
}
