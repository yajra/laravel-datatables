<?php

use yajra\Datatables\Request;

class RequestTest extends PHPUnit_Framework_TestCase
{

    public function test_check_legacy_code()
    {
        $_GET['sEcho'] = 1;
        $request       = Request::capture();
        try {
            $request->checkLegacyCode();
        } catch (Exception $e) {
            $this->assertContains('DataTables legacy code is not supported!', $e->getMessage());
        }
    }

    public function test_check_legacy_code_insufficient_parameters()
    {
        $_GET['draw'] = 1;
        $request      = Request::capture();
        try {
            $request->checkLegacyCode();
        } catch (Exception $e) {
            $this->assertContains('Insufficient parameters', $e->getMessage());
        }
    }

    public function test_is_searchable()
    {
        $_GET['search']['value'] = '';
        $request                 = Request::capture();
        $this->assertFalse($request->isSearchable());

        $_GET['search']['value'] = 'foo';
        $request                 = Request::capture();
        $this->assertTrue($request->isSearchable());

        $_GET['search']['value'] = '0';
        $request                 = Request::capture();
        $this->assertTrue($request->isSearchable());
    }

    public function test_column_keyword()
    {
        $_GET['columns']   = [];
        $_GET['columns'][] = [
            'search' => [
                'value' => 'foo'
            ]
        ];
        $_GET['columns'][] = [
            'search' => [
                'value' => 'bar'
            ]
        ];

        $request = Request::capture();
        $this->assertEquals('foo', $request->columnKeyword(0));
        $this->assertEquals('bar', $request->columnKeyword(1));
    }

    public function test_orderable_columns()
    {
        $_GET['columns']   = [];
        $_GET['columns'][] = [
            'orderable' => 'true',
            'search'    => [
                'value' => 'foo'
            ]
        ];
        $_GET['order']     = [];
        $_GET['order'][]   = [
            'column' => 0,
            'dir'    => 'bar',
        ];

        $request = Request::capture();
        $this->assertEquals([
            ['column' => 0, 'direction' => 'bar']
        ], $request->orderableColumns());

        $this->assertTrue($request->isOrderable());
        $this->assertTrue($request->isColumnOrderable(0));
    }

    public function test_searchable_column_index()
    {
        $_GET['columns']   = [];
        $_GET['columns'][] = ['name' => 'foo', 'searchable' => 'true', 'search' => ['value' => 'foo']];
        $_GET['columns'][] = ['name' => 'bar', 'searchable' => 'false', 'search' => ['value' => 'foo']];

        $request = Request::capture();
        $this->assertEquals([0], $request->searchableColumnIndex());

        $this->assertTrue($request->isColumnSearchable(0, false));
        $this->assertFalse($request->isColumnSearchable(1, false));

        $this->assertTrue($request->isColumnSearchable(0, true));
        $this->assertFalse($request->isColumnSearchable(1, false));

        $this->assertEquals('foo', $request->columnName(0));
        $this->assertEquals('bar', $request->columnName(1));
    }

    public function test_keyword()
    {
        $_GET['search'] = [];
        $_GET['search'] = ['value' => 'foo'];

        $request = Request::capture();
        $this->assertEquals('foo', $request->keyword());
    }

    public function test_is_paginationable()
    {
        $_GET['start']  = 1;
        $_GET['length'] = 10;

        $request = Request::capture();
        $this->assertTrue($request->isPaginationable());

        $_GET['start']  = 1;
        $_GET['length'] = -1;

        $request = Request::capture();
        $this->assertFalse($request->isPaginationable());

        $_GET['start']  = null;
        $_GET['length'] = 1;

        $request = Request::capture();
        $this->assertFalse($request->isPaginationable());
    }
}
