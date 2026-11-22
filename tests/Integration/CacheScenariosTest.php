<?php

namespace Yajra\DataTables\Tests\Integration;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Yajra\DataTables\DataTables;
use Yajra\DataTables\Facades\DataTables as DataTablesFacade;
use Yajra\DataTables\Tests\Models\User;
use Yajra\DataTables\Tests\TestCase;

/**
 * Usage scenarios of the cache combined with the other DataTables features.
 */
class CacheScenariosTest extends TestCase
{
    private const string FILTER_COLUMN_URI = '/scenarios/filter-column';

    private const string ORDER_COLUMN_URI = '/scenarios/order-column';

    private const string PER_VIEWER_URI = '/scenarios/per-viewer';

    private const string RELATION_URI = '/scenarios/relation';

    private const string SEARCH_MODE_URI = '/scenarios/search-mode';

    private const string SKIP_TOTAL_URI = '/scenarios/skip-total';

    private const string TOTAL_RECORDS_URI = '/scenarios/total-records';

    private const string WITH_QUERY_URI = '/scenarios/with-query';

    /**
     * @var list<string>
     */
    protected array $queries = [];

    #[Test]
    public function it_caches_a_datatable_built_from_a_relation()
    {
        [$miss] = $this->fetch(self::RELATION_URI);
        [$hit, $queries] = $this->fetch(self::RELATION_URI);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($hit));
        // Only the query loading the parent model runs, before DataTables is called.
        $this->assertSame(['select * from "users" where "users"."id" = ? limit 1'], $queries);
        $this->assertSame(3, $hit['recordsTotal']);
        $this->assertSame([1, 1, 1], array_column($hit['data'], 'user_id'));
        $this->assertSame($this->withoutDebug($miss), $this->withoutDebug($hit));
    }

    #[Test]
    public function it_runs_the_column_callbacks_of_the_current_request_on_a_hit()
    {
        [$admin] = $this->fetch(self::PER_VIEWER_URI, ['viewer' => 'admin']);
        [$guest] = $this->fetch(self::PER_VIEWER_URI, ['viewer' => 'guest']);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($guest));
        $this->assertSame('Email-1@example.com', $admin['data'][0]['email']);
        $this->assertSame('hidden', $guest['data'][0]['email']);
    }

    #[Test]
    public function it_does_not_cache_the_with_query_callbacks()
    {
        [$first] = $this->fetch(self::WITH_QUERY_URI);
        DB::table('users')->where('id', 1)->update(['name' => 'Renamed']);
        [$second] = $this->fetch(self::WITH_QUERY_URI);

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($second));
        $this->assertSame('Record-1', $first['first_name']);
        $this->assertSame('Renamed', $second['first_name']);
        $this->assertSame('Record-1', $second['data'][0]['name']);
    }

    #[Test]
    public function it_only_caches_the_reads_that_are_performed()
    {
        [$total] = $this->fetch(self::TOTAL_RECORDS_URI);
        [$skip] = $this->fetch(self::SKIP_TOTAL_URI);
        [$skipHit] = $this->fetch(self::SKIP_TOTAL_URI);

        $this->assertSame(['rows:miss'], $this->statuses($total));
        $this->assertSame(99, $total['recordsTotal']);
        // Both DataTables read the same rows with the same tags: they share the entry.
        $this->assertSame(['count:miss', 'rows:hit'], $this->statuses($skip));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($skipHit));
        $this->assertSame(20, $skipHit['recordsTotal']);
    }

    #[Test]
    public function it_keeps_apart_the_keywords_of_a_custom_column_filter()
    {
        [$first] = $this->fetch(self::FILTER_COLUMN_URI, $this->columnSearch('Record-19'));
        [$second] = $this->fetch(self::FILTER_COLUMN_URI, $this->columnSearch('Record-2'));
        [$firstAgain] = $this->fetch(self::FILTER_COLUMN_URI, $this->columnSearch('Record-19'));

        $this->assertSame(1, $first['recordsFiltered']);
        $this->assertSame(['count:hit', 'count:miss', 'rows:miss'], $this->statuses($second));
        $this->assertSame(2, $second['recordsFiltered']);
        $this->assertSame(['count:hit', 'count:hit', 'rows:hit'], $this->statuses($firstAgain));
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($firstAgain));
    }

    #[Test]
    public function it_keeps_apart_the_orders_and_the_pages()
    {
        $page = fn (string $direction, int $start) => [
            'columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true']],
            'order' => [['column' => 0, 'dir' => $direction]],
            'start' => $start,
            'length' => 5,
        ];

        [$ascending] = $this->fetch(self::ORDER_COLUMN_URI, $page('asc', 0));
        [$descending] = $this->fetch(self::ORDER_COLUMN_URI, $page('desc', 0));
        [$secondPage] = $this->fetch(self::ORDER_COLUMN_URI, $page('asc', 5));
        [$ascendingAgain] = $this->fetch(self::ORDER_COLUMN_URI, $page('asc', 0));

        $this->assertSame([1, 2, 3, 4, 5], array_column($ascending['data'], 'id'));
        $this->assertSame([20, 19, 18, 17, 16], array_column($descending['data'], 'id'));
        $this->assertSame([6, 7, 8, 9, 10], array_column($secondPage['data'], 'id'));
        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($ascendingAgain));
        $this->assertSame([1, 2, 3, 4, 5], array_column($ascendingAgain['data'], 'id'));
    }

    #[Test]
    public function it_keeps_apart_the_search_modes_set_at_runtime()
    {
        $search = ['columns' => [['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true']], 'search' => ['value' => 'ecord-1']];

        [$smart] = $this->fetch(self::SEARCH_MODE_URI, $search + ['smart' => 1]);
        [$strict] = $this->fetch(self::SEARCH_MODE_URI, $search + ['smart' => 0]);

        $this->assertSame(11, $smart['recordsFiltered']);
        $this->assertSame(0, $strict['recordsFiltered']);
        $this->assertSame('miss', $strict['cache'][1]['status']);
    }

    #[Test]
    public function it_works_through_the_facade_and_to_array()
    {
        $first = DataTablesFacade::of(User::query())->cache('users')->toArray();
        $second = DataTablesFacade::of(User::query())->cache('users')->toArray();

        $this->assertSame(['count:hit', 'rows:hit'], $this->statuses($second));
        $this->assertSame($this->withoutDebug($first), $this->withoutDebug($second));
    }

    protected function setUp(): void
    {
        parent::setUp();

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) {
            $this->queries[] = $event->sql;
        });

        $router = $this->app['router'];

        $router->get(self::RELATION_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query()->findOrFail(1)->posts())
            ->cache('posts:user:1')
            ->toJson());

        $router->get(self::PER_VIEWER_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query())
            ->editColumn('email', fn (User $user) => request('viewer') === 'admin' ? $user->email : 'hidden')
            ->cache('users')
            ->toJson());

        $router->get(self::WITH_QUERY_URI, fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users'))
            ->withQuery('first_name', fn ($query) => $query->getConnection()->table('users')->where('id', 1)->value('name'))
            ->cache('users')
            ->toJson());

        $router->get(self::TOTAL_RECORDS_URI, fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users'))
            ->setTotalRecords(99)
            ->cache('users')
            ->toJson());

        $router->get(self::SKIP_TOTAL_URI, fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users'))
            ->skipTotalRecords()
            ->cache('users')
            ->toJson());

        $router->get(self::FILTER_COLUMN_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query())
            ->filterColumn('name', fn ($query, $keyword) => $query->where('name', 'like', $keyword.'%'))
            ->cache('users')
            ->toJson());

        $router->get(self::ORDER_COLUMN_URI, fn (DataTables $dataTables) => $dataTables
            ->eloquent(User::query())
            ->orderColumn('name', 'id $1')
            ->cache('users')
            ->toJson());

        $router->get(self::SEARCH_MODE_URI, fn (DataTables $dataTables) => $dataTables
            ->query(DB::table('users'))
            ->smart((bool) request('smart'))
            ->cache('users')
            ->toJson());
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('cache.default', 'array');
    }

    /**
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function fetch(string $uri, array $parameters = []): array
    {
        $this->queries = [];

        $response = $this->call('GET', $uri, $parameters);
        $response->assertOk();

        return [$response->json(), $this->queries];
    }

    protected function columnSearch(string $keyword): array
    {
        return ['columns' => [
            ['data' => 'name', 'name' => 'name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => $keyword]],
        ]];
    }

    /**
     * @return list<string>
     */
    protected function statuses(array $response): array
    {
        return array_map(fn (array $read) => $read['read'].':'.$read['status'], $response['cache']);
    }

    protected function withoutDebug(array $response): array
    {
        unset($response['queries'], $response['input'], $response['cache']);

        return $response;
    }
}
