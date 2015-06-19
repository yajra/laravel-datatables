<?php

namespace yajra\Datatables\Html;

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
     * @var Collection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $attributes = [];

    /**
     * @var Repository
     */
    private $config;

    /**
     * @var Factory
     */
    private $view;

    /**
     * @var HtmlBuilder
     */
    private $html;

    /**
     * @var UrlGenerator
     */
    private $url;


    /**
     * @param Repository $config
     * @param Factory $view
     * @param HtmlBuilder $html
     * @param UrlGenerator $url
     */
    public function __construct(Repository $config, Factory $view, HtmlBuilder $html, UrlGenerator $url)
    {
        $this->config     = $config;
        $this->view       = $view;
        $this->html       = $html;
        $this->url        = $url;
        $this->collection = new Collection;
    }

    /**
     * @param null $script
     * @param array $attributes
     * @return string
     */
    public function scripts($script = null, array $attributes = ['type' => 'text/javascript'])
    {
        $args = array_merge($this->attributes, [
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
