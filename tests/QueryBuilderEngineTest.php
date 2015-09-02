<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Config;
use Mockery as m;
use yajra\Datatables\Datatables;
use yajra\Datatables\Request;

require_once 'helper.php';

class TestDatatablesQueryBuilderEngine extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        parent::setUp();
        $app = m::mock('AppMock');
        $app->shouldReceive('instance')->once()->andReturn($app);

        Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        Config::swap($config = m::mock('ConfigMock'));
    }

    public function tearDown()
    {
        m::close();
    }

    public function test_datatables_make_with_data_using_of_method()
    {
        $builder = $this->setupBuilder();
        // set Input variables
        $this->setupInputVariables();

        $response = Datatables::of($builder)->make();

        $actual   = $response->getContent();
        $expected = '{"draw":1,"recordsTotal":2,"recordsFiltered":2,"data":[[1,"foo"],[2,"bar"]]}';

        $this->assertInstanceOf('Illuminate\Http\JsonResponse', $response);
        $this->assertEquals($expected, $actual);
    }

    public function test_datatables_make_with_data()
    {
        $builder = $this->setupBuilder();
        // set Input variables
        $this->setupInputVariables();

        $datatables = new Datatables(Request::capture());

        $response = $datatables->usingQueryBuilder($builder)->make();

        $actual   = $response->getContent();
        $expected = '{"draw":1,"recordsTotal":2,"recordsFiltered":2,"data":[[1,"foo"],[2,"bar"]]}';

        $this->assertInstanceOf('Illuminate\Http\JsonResponse', $response);
        $this->assertEquals($expected, $actual);
    }

    public function test_datatables_make_with_data_using_alias()
    {
        $builder = $this->setupBuilder();
        // set Input variables
        $this->setupInputVariables();

        $datatables = new Datatables(Request::capture());

        $response = $datatables->queryBuilder($builder)->make();

        $actual   = $response->getContent();
        $expected = '{"draw":1,"recordsTotal":2,"recordsFiltered":2,"data":[[1,"foo"],[2,"bar"]]}';

        $this->assertInstanceOf('Illuminate\Http\JsonResponse', $response);
        $this->assertEquals($expected, $actual);
    }

    protected function setupBuilder($showAllRecords = false)
    {
        Config::shouldReceive('get');

        $cache   = m::mock('stdClass');
        $driver  = m::mock('stdClass');
        $data    = [
            ['id' => 1, 'name' => 'foo'],
            ['id' => 2, 'name' => 'bar'],
        ];
        $builder = m::mock('Illuminate\Database\Query\Builder');
        $builder->shouldReceive('getConnection')->andReturn(m::mock('Illuminate\Database\Connection'));
        $builder->getConnection()->shouldReceive('getDriverName')->once()->andReturn('dbdriver');
        $builder->getConnection()->shouldReceive('getTablePrefix')->once()->andReturn('');

        // setup builder
        $builder->shouldReceive('select')->once()->with(['id', 'name'])->andReturn($builder);
        $builder->shouldReceive('from')->once()->with('users')->andReturn($builder);
        $builder->columns = ['id', 'name'];
        $builder->select(['id', 'name'])->from('users');

        // count total records
        $builder->shouldReceive('toSql')->times(1)->andReturn('select id, name from users');
        $builder->shouldReceive('select')->once()->andReturn($builder);
        $builder->getConnection()->shouldReceive('raw')->once()->andReturn('select \'1\' as row_count');
        $builder->getConnection()->shouldReceive('table')->once()->andReturn($builder);
        $builder->getConnection()->shouldReceive('raw')->andReturn('(select id, name from users) count_row_table');
        $builder->shouldReceive('toSql')->once()->andReturn('select id, name from users');
        $builder->shouldReceive('getBindings')->once()->andReturn([]);
        $builder->shouldReceive('setBindings')->once()->with([])->andReturn($builder);
        $builder->shouldReceive('count')->once()->andReturn(2);

        // get data
        $builder->shouldReceive('get')->once()->andReturn($data);

        // pagination
        if ( ! $showAllRecords) {
            $builder->shouldReceive('skip')->once()->andReturn($builder);
            $builder->shouldReceive('take')->once()->andReturn($builder);
        }

        return $builder;
    }

    protected function setupInputVariables()
    {
        $_GET                                  = [];
        $_GET['draw']                          = 1;
        $_GET['start']                         = 0;
        $_GET['length']                        = 10;
        $_GET['search']['value']               = '';
        $_GET['columns'][0]['name']            = 'foo';
        $_GET['columns'][0]['search']['value'] = '';
        $_GET['columns'][0]['searchable']      = true;
        $_GET['columns'][0]['orderable']       = true;
        $_GET['columns'][1]['name']            = 'bar';
        $_GET['columns'][1]['search']['value'] = '';
        $_GET['columns'][1]['searchable']      = true;
        $_GET['columns'][1]['orderable']       = true;
    }

    public function test_datatables_make_with_data_and_uses_mdata()
    {
        $builder = $this->setupBuilder();
        // set Input variables
        $this->setupInputVariables();

        $datatables = new Datatables(Request::capture());

        $response = $datatables->usingQueryBuilder($builder)->make(true);
        $actual   = $response->getContent();
        $expected = '{"draw":1,"recordsTotal":2,"recordsFiltered":2,"data":[{"id":1,"name":"foo"},{"id":2,"name":"bar"}]}';

        $this->assertInstanceOf('Illuminate\Http\JsonResponse', $response);
        $this->assertEquals($expected, $actual);
    }

    protected function getBuilder()
    {
        $grammar   = new Illuminate\Database\Query\Grammars\Grammar;
        $processor = m::mock('Illuminate\Database\Query\Processors\Processor');

        return new Builder(m::mock('Illuminate\Database\Connection'), $grammar, $processor);
    }
}
