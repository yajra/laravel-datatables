<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Tests\Models\Post;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

class DeepRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_all_records_with_the_relation_when_called_without_parameters()
    {
        $response = $this->getJsonResponse();
        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 60,
        ]);

        $this->assertCount(60, $response->json()['data']);
    }

    #[Test]
    public function it_can_perform_global_search_on_the_relation()
    {
        $response = $this->getJsonResponse([
            'search' => ['value' => 'email-19@example.com'],
        ]);

        $response->assertJson([
            'draw' => 0,
            'recordsTotal' => 60,
            'recordsFiltered' => 3,
        ]);

        $this->assertCount(3, $response->json()['data']);
    }

    #[Test]
    #[TestWith([Post::class, 'unlistedRelation.user.id', true])]
    #[TestWith([Post::class, 'unlistedRelation.user.id', false])]
    #[TestWith([User::class, 'user.unlistedRelation.size', true])]
    #[TestWith([User::class, 'user.unlistedRelation.size', false])]
    public function it_only_resolves_configured_relations(string $modelClass, string $column, bool $global): void
    {
        $calls = 0;
        $modelClass::resolveRelationUsing('unlistedRelation', static function (Post|User $model) use (&$calls) {
            $calls++;

            return $model->heart();
        });

        $this->getJsonResponse([
            'columns' => [[
                'data' => $column,
                'searchable' => 'true',
                'search' => ['value' => $global ? '' : 'heart-20'],
            ]],
            'search' => ['value' => $global ? 'heart-20' : ''],
        ]);

        $this->assertSame(0, $calls);
    }

    #[Test]
    public function it_resolves_an_allowed_nested_relation_on_its_own_model(): void
    {
        $this->app['router']->get('/relations/nested-heart', function (DataTables $datatables) {
            $post = new class extends Post
            {
                protected $table = 'posts';

                public function heart(): never
                {
                    throw new LogicException('Only the related user should resolve heart.');
                }
            };

            return $datatables->eloquent($post->newQuery()->with('user.heart'))->toJson();
        });

        $this->call('GET', '/relations/nested-heart', [
            'columns' => [['data' => 'user.heart.size', 'searchable' => 'true']],
            'search' => ['value' => 'heart-20'],
        ])->assertJson(['recordsTotal' => 60, 'recordsFiltered' => 3]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->get('/relations/deep', function (DataTables $datatables) {
            $query = Post::with('user.roles')->select('posts.*');

            return $datatables
                ->eloquent($query)
                ->toJson();
        });
    }

    protected function getJsonResponse(array $params = [])
    {
        $data = [
            'columns' => [
                ['data' => 'user.roles.role', 'name' => 'user.roles.role', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'user.name', 'name' => 'user.name', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'user.email', 'name' => 'user.email', 'searchable' => 'true', 'orderable' => 'true'],
                ['data' => 'title', 'name' => 'title', 'searchable' => 'true', 'orderable' => 'true'],
            ],
        ];

        return $this->call('GET', '/relations/deep', array_merge($data, $params));
    }
}
