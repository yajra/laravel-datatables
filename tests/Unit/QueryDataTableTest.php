<?php

namespace Yajra\DataTables\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class QueryDataTableTest extends TestCase
{
    public function test_complex_query_are_wrapped_and_countable()
    {
        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            DB::table('module_telers')
                ->selectRaw('module_telers.id, module_telers.publiceren, module_telers.archief, module_telers.uitgelicht, module_telers.image_header, module_telers.bedrijfsnaam, module_telers.titel, module_telers.plaats, group_concat(DISTINCT productenAlias.titel SEPARATOR \', \') as producten')
                ->leftJoin('relation_producten_telers', 'module_telers.id', '=', 'relation_producten_telers.telers_id')
                ->leftJoin('module_producten as productenAlias', 'productenAlias.id', '=', 'relation_producten_telers.producten_id')
                ->groupBy('module_telers.id')
        );

        $this->assertQueryWrapped(true, $dataTable->prepareCountQuery());

        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            DB::table('posts')->selectRaw('title AS state')->groupBy('state')->having('state', '!=', 'deleted')
        );

        $this->assertQueryWrapped(true, $dataTable->prepareCountQuery());
        $this->assertQueryHasNoSelect(false, $dataTable->prepareCountQuery());
        $this->assertEquals(60, $dataTable->count());
    }

    public function test_complex_query_use_select_in_count()
    {
        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            DB::table('users')
                ->select('users.*')
                ->addSelect([
                    'last_post_id' => DB::table('posts')
                                        ->whereColumn('posts.user_id', 'users.id')
                                        ->orderBy('created_at')
                                        ->select('id'),
                ])
                ->orderBy(
                    DB::table('posts')->whereColumn('posts.user_id', 'users.id')->orderBy('created_at')->select('created_at')
                )
        );

        $this->assertQueryHasNoSelect(false, $dataTable->prepareCountQuery());
        $this->assertEquals(20, $dataTable->count());
    }

    public function test_complex_query_can_ignore_select_in_count()
    {
        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            DB::table('users')
                ->select('users.*')
                ->addSelect([
                    'last_post_id' => DB::table('posts')
                                        ->whereColumn('posts.user_id', 'users.id')
                                        ->orderBy('created_at')
                                        ->select('id'),
                ])
                ->orderBy(
                    DB::table('posts')->whereColumn('posts.user_id', 'users.id')->orderBy('created_at')->select('created_at')
                )
        )->ignoreSelectsInCountQuery();

        $this->assertQueryHasNoSelect(true, $dataTable->prepareCountQuery());
        $this->assertEquals(20, $dataTable->count());
    }

    public function test_simple_queries_with_complexe_select_are_wrapped_without_selects()
    {
        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            DB::table('users')
              ->select('users.*')
              ->addSelect([
                  'last_post_id' => DB::table('posts')
                                      ->whereColumn('posts.user_id', 'users.id')
                                      ->orderBy('created_at')
                                      ->select('id'),
              ])
        );

        $this->assertQueryWrapped(true, $dataTable->prepareCountQuery());
        $this->assertQueryHasNoSelect(true, $dataTable->prepareCountQuery());
        $this->assertEquals(20, $dataTable->count());
    }

    public function test_simple_queries_are_not_wrapped_and_countable()
    {
        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            User::with('posts')->select('users.*')
        );

        $this->assertQueryWrapped(false, $dataTable->prepareCountQuery());
        $this->assertEquals(20, $dataTable->count());
    }

    public function test_complexe_queries_can_be_wrapped_and_countable()
    {
        /** @var \Yajra\DataTables\QueryDataTable $dataTable */
        $dataTable = app('datatables')->of(
            User::with('posts')->select('users.*')
        );

        $this->assertQueryWrapped(false, $dataTable->prepareCountQuery());
        $this->assertEquals(20, $dataTable->count());
    }

    /**
     * @param  $expected  bool
     * @param  $query  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     * @return void
     */
    protected function assertQueryWrapped($expected, $query)
    {
        $sql = $query->toSql();

        $this->assertSame($expected, Str::endsWith($sql, 'count_row_table'), "'{$sql}' is not wrapped");
    }

    /**
     * @param  $expected  bool
     * @param  $query  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     * @return void
     */
    public function assertQueryHasNoSelect($expected, $query)
    {
        $sql = $query->toSql();

        $this->assertSame($expected, Str::startsWith($sql, 'select * from (select 1 as dt_row_count from'), "'{$sql}' is not wrapped");
    }
}
