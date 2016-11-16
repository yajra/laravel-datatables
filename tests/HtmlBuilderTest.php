<?php

use Yajra\Datatables\Datatables;
use Yajra\Datatables\Html\Column;
use Yajra\Datatables\Request;

require_once 'helper.php';

class HtmlBuilderTest extends PHPUnit_Framework_TestCase
{
    public function test_generate_table_html()
    {
        $builder = $this->getHtmlBuilder();
        $builder->html->shouldReceive('attributes')->times(8)->andReturn('id="foo"');
        $builder->form->shouldReceive('checkbox')
                      ->once()
                      ->andReturn('<input type="checkbox "id"="dataTablesCheckbox"/>');

        $builder->addCheckbox(['id' => 'foo'])
                ->columns(['foo', 'bar' => ['data' => 'foo']])
                ->addColumn(['name' => 'id', 'data' => 'id', 'title' => 'Id'])
                ->add(new Column(['name' => 'a', 'data' => 'a', 'title' => 'A']))
                ->addAction(['title' => 'Options'])
                ->ajax('ajax-url')
                ->parameters(['bFilter' => false]);
        $table    = $builder->table(['id' => 'foo']);
        $expected = '<table id="foo"><thead><tr><th id="foo"><input type="checkbox "id"="dataTablesCheckbox"/></th><th id="foo">Foo</th><th id="foo">Bar</th><th id="foo">Id</th><th id="foo">A</th><th id="foo">Options</th></tr></thead></table>';
        $this->assertEquals($expected, $table);

        $builder->view->shouldReceive('make')->times(2)->andReturn($builder->view);
        $builder->config->shouldReceive('get')->times(2)->andReturn('datatables::script');
        $template = file_get_contents(__DIR__ . '/../src/resources/views/script.blade.php');
        $builder->view->shouldReceive('render')->times(2)->andReturn(trim($template));
        $builder->html->shouldReceive('attributes')->once()->andReturn();

        $script   = $builder->scripts();
        $expected = '<script>(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","render":null,"orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);</script>' . PHP_EOL;
        $this->assertEquals($expected, $script);

        $expected = '(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","render":null,"orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);';
        $this->assertEquals($expected, $builder->generateScripts());
    }

    /**
     * @return \Mockery\MockInterface|\Yajra\Datatables\Datatables|\Yajra\Datatables\Html\Builder
     */
    protected function getHtmlBuilder()
    {
        $builder = app('datatables.html');

        return $builder;
    }

    public function test_generate_table_html_with_empty_footer()
    {
        $builder = $this->getHtmlBuilder();
        $builder->html->shouldReceive('attributes')->times(8)->andReturn('id="foo"');
        $builder->form->shouldReceive('checkbox')
                      ->once()
                      ->andReturn('<input type="checkbox "id"="dataTablesCheckbox"/>');

        $builder->addCheckbox(['id' => 'foo'])
                ->columns(['foo', 'bar' => ['data' => 'foo']])
                ->addColumn(['name' => 'id', 'data' => 'id', 'title' => 'Id'])
                ->add(new Column(['name' => 'a', 'data' => 'a', 'title' => 'A']))
                ->addAction(['title' => 'Options'])
                ->ajax('ajax-url')
                ->parameters(['bFilter' => false]);
        $table    = $builder->table(['id' => 'foo'], true);
        $expected = '<table id="foo"><thead><tr><th id="foo"><input type="checkbox "id"="dataTablesCheckbox"/></th><th id="foo">Foo</th><th id="foo">Bar</th><th id="foo">Id</th><th id="foo">A</th><th id="foo">Options</th></tr></thead><tfoot><tr><th></th><th></th><th></th><th></th><th></th><th></th></tr></tfoot></table>';
        $this->assertEquals($expected, $table);

        $builder->view->shouldReceive('make')->times(2)->andReturn($builder->view);
        $builder->config->shouldReceive('get')->times(2)->andReturn('datatables::script');
        $template = file_get_contents(__DIR__ . '/../src/resources/views/script.blade.php');
        $builder->view->shouldReceive('render')->times(2)->andReturn(trim($template));
        $builder->html->shouldReceive('attributes')->once()->andReturn();

        $script   = $builder->scripts();
        $expected = '<script>(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","render":null,"orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);</script>' . PHP_EOL;
        $this->assertEquals($expected, $script);

        $expected = '(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","render":null,"orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);';
        $this->assertEquals($expected, $builder->generateScripts());
    }

    public function test_generate_table_html_with_footer_content()
    {
        $builder = $this->getHtmlBuilder();
        $builder->html->shouldReceive('attributes')->times(8)->andReturn('id="foo"');
        $builder->form->shouldReceive('checkbox')
                      ->once()
                      ->andReturn('<input type="checkbox "id"="dataTablesCheckbox"/>');

        $builder->addCheckbox(['id' => 'foo', 'footer' => 'test'])
                ->columns(['foo', 'bar' => ['data' => 'foo']])
                ->addColumn(['name' => 'id', 'data' => 'id', 'title' => 'Id'])
                ->add(new Column(['name' => 'a', 'data' => 'a', 'title' => 'A']))
                ->addAction(['title' => 'Options'])
                ->ajax('ajax-url')
                ->parameters(['bFilter' => false]);
        $table    = $builder->table(['id' => 'foo'], true);
        $expected = '<table id="foo"><thead><tr><th id="foo"><input type="checkbox "id"="dataTablesCheckbox"/></th><th id="foo">Foo</th><th id="foo">Bar</th><th id="foo">Id</th><th id="foo">A</th><th id="foo">Options</th></tr></thead><tfoot><tr><th>test</th><th></th><th></th><th></th><th></th><th></th></tr></tfoot></table>';
        $this->assertEquals($expected, $table);

        $builder->view->shouldReceive('make')->times(2)->andReturn($builder->view);
        $builder->config->shouldReceive('get')->times(2)->andReturn('datatables::script');
        $template = file_get_contents(__DIR__ . '/../src/resources/views/script.blade.php');
        $builder->view->shouldReceive('render')->times(2)->andReturn(trim($template));
        $builder->html->shouldReceive('attributes')->once()->andReturn();

        $script   = $builder->scripts();
        $expected = '<script>(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","render":null,"orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);</script>' . PHP_EOL;
        $this->assertEquals($expected, $script);

        $expected = '(function(window,$){window.LaravelDataTables=window.LaravelDataTables||{};window.LaravelDataTables["foo"]=$("#foo").DataTable({"serverSide":true,"processing":true,"ajax":"ajax-url","columns":[{"defaultContent":"<input type=\"checkbox\" id=\"foo\"\/>","title":"<input type=\"checkbox \"id\"=\"dataTablesCheckbox\"\/>","data":"checkbox","name":"checkbox","orderable":false,"searchable":false,"width":"10px","id":"foo"},{"name":"foo","data":"foo","title":"Foo","orderable":true,"searchable":true},{"name":"bar","data":"foo","title":"Bar","orderable":true,"searchable":true},{"name":"id","data":"id","title":"Id","orderable":true,"searchable":true},{"name":"a","data":"a","title":"A","orderable":true,"searchable":true},{"defaultContent":"","data":"action","name":"action","title":"Options","render":null,"orderable":false,"searchable":false}],"bFilter":false});})(window,jQuery);';
        $this->assertEquals($expected, $builder->generateScripts());
    }

    public function test_setting_table_attribute()
    {
        $builder = $this->getHtmlBuilder();

        $builder->setTableAttribute('attr', 'val');

        $this->assertEquals('val', $builder->getTableAttribute('attr'));
    }

    public function test_settings_multiple_table_attributes()
    {
        $builder = $this->getHtmlBuilder();

        $builder->setTableAttribute(['prop1' => 'val1', 'prop2' => 'val2']);

        $this->assertEquals('val1', $builder->getTableAttribute('prop1'));
        $this->assertEquals('val2', $builder->getTableAttribute('prop2'));
    }

    public function test_getting_inexistent_table_attribute_throws()
    {
        $this->setExpectedExceptionRegExp(\Exception::class, '/Table attribute \'.+?\' does not exist\./');

        $builder = $this->getHtmlBuilder();

        $builder->getTableAttribute('boohoo');
    }

    /**
     * @return \Yajra\Datatables\Datatables
     */
    protected function getDatatables()
    {
        $datatables = new Datatables(Request::capture());

        return $datatables;
    }
}
