<?php

use yajra\Datatables\Datatables;
use yajra\Datatables\Html\Column;
use yajra\Datatables\Request;

require_once 'helper.php';

class HtmlBuilderTest extends PHPUnit_Framework_TestCase
{
    public function test_generate_table_html()
    {
        $datatables = $this->getDatatables();
        $builder    = $datatables->getHtmlBuilder();

        $builder->html->shouldReceive('attributes')->times(2)->andReturn('id="foo"');
        $builder->form->shouldReceive('checkbox')->once()->andReturn('<input type="checkbox "id"="dataTablesCheckbox"/>');
        $builder->addCheckbox(['id' => 'foo'])
            ->columns(['foo', 'bar' => ['data' => 'foo']])
            ->addColumn(['name' => 'id', 'data' => 'id', 'title' => 'Id'])
            ->add(new Column(['name' => 'a', 'data' => 'a', 'title' => 'A']))
            ->addAction(['title' => 'Options'])
            ->ajax('ajax-url')
            ->parameters(['bFilter' => false]);
        $table = $builder->table(['id' => 'foo']);
        $this->assertEquals('<table id="foo"></table>', $table);

        $builder->html->shouldReceive('attributes')->once()->andReturn();
        $script   = $builder->scripts();
        $expected = '<script>(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);</script>' . PHP_EOL;
        $this->assertEquals($expected, $script);

        $expected = '(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);';
        $this->assertEquals($expected, $builder->generateScripts());
    }

    /**
     * @return \yajra\Datatables\Datatables
     */
    protected function getDatatables()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }

}
