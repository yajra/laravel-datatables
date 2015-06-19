<?php namespace yajra\Datatables\Html;

use Collective\Html\FormBuilder;
use Collective\Html\HtmlBuilder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\View\Factory;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Collection;

/**
 * Class Builder
 *
 * @package yajra\Datatables\Html
 */
class Builder
{
    /**
     * @var string|array
     */
    protected $ajax;

    /**
     * @var array
     */
    protected $tableAttributes = ['class' => 'table', 'id' => 'dataTableBuilder'];

    /**
     * @var array
     */
    protected $attributes = [];

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
     * Generate DataTable javascript
     *
     * @param null $script
     * @param array $attributes
     * @return string
     */
     public function scripts($script = null, array $attributes = ['type' => 'text/javascript'])
     {
        $script = $script ?: $this->generateScripts();

        return '<script' . $this->html->attributes($attributes) . '>' . $script . '</script>' . PHP_EOL;
    }

    /**
     * Get generated raw scripts
     */
    public function generateScripts()
    {
         $args = array_merge($this->attributes, [
             'ajax'    => $this->ajax,
             'columns' => $this->collection->toArray()
         ]);
         $parameters = $this->parameterize($args);

        return sprintf('$(function(){ $("#%s").DataTable(%s);});', $this->tableAttributes['id'], $parameters);
     }

    /**
     * Generate datatable js parameters
     *
     * @param array $attributes
     * @return string
     */
    public function parameterize($attributes = [])
    {
        return json_encode(new Parameters($attributes));
    }

    /**
     * Add a column in collection
     *
     * @param array $attributes
     * @return $this
     */
    public function addColumn(array $attributes)
    {
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * Add a checkbox column
     *
     * @param array $attributes
     * @return $this
     */
    public function addCheckbox(array $attributes = [])
    {
        $attributes = array_merge([
            'defaultContent' => '<input type="checkbox" ' . $this->html->attributes($attributes) . '/>',
            'title'          => $this->form->checkbox('', '', false, ['id' => 'dataTablesCheckbox']),
            'data'           => 'checkbox',
            'name'           => 'checkbox',
            'orderable'      => false,
            'searchable'     => false,
            'width'          => '10px',
        ], $attributes);
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * Add a action column
     *
     * @param array $attributes
     * @return $this
     */
    public function addAction(array $attributes = [])
    {
        $attributes = array_merge([
            'defaultContent' => '',
            'data'           => 'action',
            'name'           => 'action',
            'title'          => 'Action',
            'orderable'      => false,
            'searchable'     => false
        ], $attributes);
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * Setup ajax parameter
     *
     * @param string|array $attributes
     * @return $this
     */
    public function ajax($attributes)
    {
        $this->ajax = $attributes;

        return $this;
    }

    /**
     * Generate DataTable's table html
     *
     * @param array $attributes
     * @return string
     */
    public function table(array $attributes = [])
    {
        $this->tableAttributes = $attributes ?: $this->tableAttributes;

        return '<table' . $this->html->attributes($this->tableAttributes) . '></table>';
    }

    /**
     * Configure DataTable's parameters
     *
     * @param array $attributes
     * @return $this
     */
    public function parameters(array $attributes = [])
    {
        $this->attributes = $attributes;

        return $this;
    }
}
