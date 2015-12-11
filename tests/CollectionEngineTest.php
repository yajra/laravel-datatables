<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Mockery as m;
use yajra\Datatables\Datatables;
use yajra\Datatables\Request;

require_once 'helper.php';

class TestDatatablesCollectionEngine extends PHPUnit_Framework_TestCase
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

    public function test_datatables_make_with_data_using_of()
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

        $response = $datatables->usingCollection($builder)->make();

        $actual   = $response->getContent();
        $expected = '{"draw":1,"recordsTotal":2,"recordsFiltered":2,"data":[[1,"foo"],[2,"bar"]]}';

        $this->assertInstanceOf('Illuminate\Http\JsonResponse', $response);
        $this->assertEquals($expected, $actual);
    }

    protected function setupBuilder()
    {
        Config::shouldReceive('get');
        $data = [
            ['id' => 1, 'name' => 'foo'],
            ['id' => 2, 'name' => 'bar'],
        ];

        return new Collection($data);
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

        $response = $datatables->usingCollection($builder)->make(true);
        $actual   = $response->getContent();
        $expected = '{"draw":1,"recordsTotal":2,"recordsFiltered":2,"data":[{"id":1,"name":"foo"},{"id":2,"name":"bar"}]}';

        $this->assertInstanceOf('Illuminate\Http\JsonResponse', $response);
        $this->assertEquals($expected, $actual);
    }
}
