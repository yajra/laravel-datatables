<?php

namespace Rafaelqm\Datatables\Html;

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class Builder
 *
 * @package Rafaelqm\Datatables\Html
 */
class Builder
{
    /**
     * @var Collection
     */
    public $collection;

    /**
     * @var Repository
     */
    public $config;

    /**
     * @var Factory
     */
    public $view;

    /**
     * @var HtmlBuilder
     */
    public $html;

    /**
     * @var UrlGenerator
     */
    public $url;

    /**
     * @var FormBuilder
     */
    public $form;

    /**
     * @var string|array
     */
    protected $ajax = '';

    /**
     * @var array
     */
    protected $tableAttributes = ['class' => 'table', 'id' => 'dataTableBuilder'];

    /**
     * @var string
     */
    protected $template = '';

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @param Repository $config
     * @param Factory $view
     * @param HtmlBuilder $html
     * @param UrlGenerator $url
     * @param FormBuilder $form
     */
    public function __construct(
        Repository $config,
        Factory $view,
        HtmlBuilder $html,
        UrlGenerator $url,
        FormBuilder $form
    ) {
        $this->config     = $config;
        $this->view       = $view;
        $this->html       = $html;
        $this->url        = $url;
        $this->collection = new Collection;
        $this->form       = $form;
    }

    /**
     * Generate DataTable javascript.
     *
     * @param  null $script
     * @param  array $attributes
     * @return string
     */
    public function scripts($script = null, array $attributes = ['type' => 'text/javascript'])
    {
        $script = $script ?: $this->generateScripts();

        return '<script' . $this->html->attributes($attributes) . '>' . $script . '</script>' . PHP_EOL;
    }

    /**
     * Get generated raw scripts.
     *
     * @return string
     */
    public function generateScripts()
    {
        $args = array_merge(
            $this->attributes, [
                'ajax'    => $this->ajax,
                'columns' => $this->collection->toArray(),
            ]
        );

        $parameters = $this->parameterize($args);

        return sprintf(
            $this->template(),
            $this->tableAttributes['id'], $parameters
        );
    }

    /**
     * Generate datatable js parameters.
     *
     * @param  array $attributes
     * @return string
     */
    public function parameterize($attributes = [])
    {
        $parameters       = (new Parameters($attributes))->toArray();
        $column_functions = [];

        foreach ($parameters['columns'] as $i => $column) {
            unset($parameters['columns'][$i]['exportable']);
            unset($parameters['columns'][$i]['printable']);

            if (isset($column['render'])) {
                $column_functions[$i]                = $column['render'];
                $parameters['columns'][$i]['render'] = "#column_function.{$i}#";
            }
        }

        $json = json_encode($parameters);

        foreach ($column_functions as $i => $function) {
            $json = str_replace("\"#column_function.{$i}#\"", $function, $json);
        }

        return $json;
    }

    /**
     * Get javascript template to use.
     *
     * @return string
     */
    protected function template()
    {
        return $this->view->make(
            $this->template ?: $this->config->get('datatables.script_template', 'datatables::script')
        )->render();
    }

    /**
     * Add a column in collection using attributes.
     *
     * @param  array $attributes
     * @return $this
     */
    public function addColumn(array $attributes)
    {
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * Add a Column object in collection.
     *
     * @param \Rafaelqm\Datatables\Html\Column $column
     * @return $this
     */
    public function add(Column $column)
    {
        $this->collection->push($column);

        return $this;
    }

    /**
     * Set datatables columns from array definition.
     *
     * @param array $columns
     * @return $this
     */
    public function columns(array $columns)
    {
        foreach ($columns as $key => $value) {
            if (is_array($value)) {
                $attributes = array_merge(['name' => $key, 'data' => $key], $this->setTitle($key, $value));
            } else {
                $attributes = [
                    'name'  => $value,
                    'data'  => $value,
                    'title' => $this->getQualifiedTitle($value),
                ];
            }

            $this->collection->push(new Column($attributes));
        }

        return $this;
    }

    /**
     * Set title attribute of an array if not set.
     *
     * @param string $title
     * @param array $attributes
     * @return array
     */
    public function setTitle($title, array $attributes)
    {
        if (! isset($attributes['title'])) {
            $attributes['title'] = $this->getQualifiedTitle($title);
        }

        return $attributes;
    }

    /**
     * Convert string into a readable title.
     *
     * @param string $title
     * @return string
     */
    public function getQualifiedTitle($title)
    {
        return Str::title(str_replace(['.', '_'], ' ', Str::snake($title)));
    }

    /**
     * Add a checkbox column.
     *
     * @param  array $attributes
     * @return $this
     */
    public function addCheckbox(array $attributes = [])
    {
        $attributes = array_merge(
            [
                'defaultContent' => '<input type="checkbox" ' . $this->html->attributes($attributes) . '/>',
                'title'          => $this->form->checkbox('', '', false, ['id' => 'dataTablesCheckbox']),
                'data'           => 'checkbox',
                'name'           => 'checkbox',
                'orderable'      => false,
                'searchable'     => false,
                'exportable'     => false,
                'printable'      => true,
                'width'          => '10px',
            ], $attributes
        );
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * Add a action column.
     *
     * @param  array $attributes
     * @return $this
     */
    public function addAction(array $attributes = [])
    {
        $attributes = array_merge(
            [
                'defaultContent' => '',
                'data'           => 'action',
                'name'           => 'action',
                'title'          => 'Action',
                'render'         => null,
                'orderable'      => false,
                'searchable'     => false,
                'exportable'     => false,
                'printable'      => true,
            ], $attributes
        );
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * Setup ajax parameter
     *
     * @param  string|array $attributes
     * @return $this
     */
    public function ajax($attributes)
    {
        $this->ajax = $attributes;

        return $this;
    }

    /**
     * Generate DataTable's table html.
     *
     * @param  array $attributes
     * @return string
     */
    public function table(array $attributes = [])
    {
        $this->tableAttributes = array_merge($this->tableAttributes, $attributes);

        return '<table ' . $this->html->attributes($this->tableAttributes) . '></table>';
    }

    /**
     * Configure DataTable's parameters.
     *
     * @param  array $attributes
     * @return $this
     */
    public function parameters(array $attributes = [])
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * Set custom javascript template.
     *
     * @param string $template
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * Get collection of columns.
     *
     * @return Collection
     */
    public function getColumns()
    {
        return $this->collection;
    }
}
