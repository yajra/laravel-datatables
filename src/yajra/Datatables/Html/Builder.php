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
     * @var string
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
     * @param null $script
     * @param array $attributes
     * @return string
     */
    public function scripts($script = null, array $attributes = ['type' => 'text/javascript'])
    {
        $args       = array_merge($this->attributes, [
            'ajax'    => $this->ajax,
            'columns' => $this->collection->toArray()
        ]);
        $parameters = $this->parameterize($args);

        if (! $script) {
            $script = sprintf('$(function(){ $("#%s").DataTable(%s)});', $this->tableAttributes['id'], $parameters);
        }

        return '<script' . $this->html->attributes($attributes) . '>' . $script . '</script>' . PHP_EOL;
    }

    /**
     * @param array $attributes
     * @return string
     */
    public function parameterize($attributes = [])
    {
        return json_encode(new Parameters($attributes));
    }

    /**
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
        $attributes = [
            'defaultContent' => '<input type="checkbox" ' . $this->html->attributes($attributes) . '/>',
            'title'          => $this->form->checkbox('', '', false, ['id' => 'dataTablesCheckbox']),
            'orderable'      => false,
            'searchable'     => false
        ];
        $this->collection->push(new Column($attributes));

        return $this;
    }

    /**
     * @param string|array $attributes
     * @return $this
     */
    public function ajax($attributes)
    {
        $this->ajax = $attributes;

        return $this;
    }

    /**
     * @param array $attributes
     * @return string
     */
    public function table(array $attributes = [])
    {
        $this->tableAttributes = $attributes ?: $this->tableAttributes;

        return '<table' . $this->html->attributes($this->tableAttributes) . '></table>';
    }

    /**
     * @param array $attributes
     * @return $this
     */
    public function parameters(array $attributes = [])
    {
        $this->attributes = $attributes;

        return $this;
    }
}
