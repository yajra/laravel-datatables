<?php

namespace Yajra\DataTables\Tests\Unit;

use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\TestCase;

class GlobalSearchRelationTest extends TestCase
{
    #[Test]
    public function it_groups_columns_of_the_same_relation_into_a_single_sub_query()
    {
        $sql = $this->globalSearchSql(Post::with('user')->select('posts.*'), [
            'user.name',
            'user.email',
            'posts.title',
        ]);

        $this->assertEquals(1, substr_count($sql, 'exists (select'));
        $this->assertStringContainsString('"users"."name"', $sql);
        $this->assertStringContainsString('"users"."email"', $sql);
        $this->assertStringContainsString('"posts"."title"', $sql);
    }

    #[Test]
    public function it_uses_a_single_sub_query_per_relation()
    {
        $sql = $this->globalSearchSql(Post::with(['user', 'heart'])->select('posts.*'), [
            'user.name',
            'heart.size',
            'user.email',
        ]);

        $this->assertEquals(2, substr_count($sql, 'exists (select'));
        $this->assertStringContainsString('"users"."name"', $sql);
        $this->assertStringContainsString('"users"."email"', $sql);
        $this->assertStringContainsString('"hearts"."size"', $sql);
    }

    #[Test]
    public function it_does_not_use_a_sub_query_when_the_relation_is_not_eager_loaded()
    {
        $sql = $this->globalSearchSql(Post::query()->select('posts.*'), [
            'user.name',
            'user.email',
        ]);

        $this->assertStringNotContainsString('exists (select', $sql);
        $this->assertStringContainsString('"user"."name"', $sql);
        $this->assertStringContainsString('"user"."email"', $sql);
    }

    #[Test]
    public function it_keeps_the_relation_constraint_and_the_searched_columns_grouped()
    {
        $sql = $this->globalSearchSql(Post::with('user')->select('posts.*'), [
            'user.name',
            'user.email',
        ]);

        // The relation constraint must be joined with "and" to the grouped
        // column conditions, otherwise the "or" would leak out of it.
        $this->assertMatchesRegularExpression(
            '/and \(lower\("users"\."name"\) LIKE \? or lower\("users"\."email"\) LIKE \?\)/i',
            $sql
        );
    }

    #[Test]
    public function it_returns_the_same_records_as_an_ungrouped_search()
    {
        $this->mergeRequest(['user.name', 'user.email'], 'Email-19@example.com');

        $dataTable = new EloquentDataTable(Post::with('user')->select('posts.*'));
        $dataTable->filtering();

        $this->assertCount(3, $dataTable->getQuery()->get());
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function globalSearchSql(Builder $query, array $columns, string $keyword = 'foo'): string
    {
        $this->mergeRequest($columns, $keyword);

        $dataTable = new EloquentDataTable($query);
        $dataTable->filtering();

        return $dataTable->getQuery()->toSql();
    }

    /**
     * @param  array<int, string>  $columns
     */
    protected function mergeRequest(array $columns, string $keyword): void
    {
        app('datatables.request')->merge([
            'columns' => array_map(fn ($column) => [
                'data' => $column,
                'name' => $column,
                'searchable' => 'true',
                'orderable' => 'true',
                'search' => ['value' => null, 'regex' => 'false'],
            ], $columns),
            'search' => ['value' => $keyword, 'regex' => 'false'],
        ]);
    }
}
