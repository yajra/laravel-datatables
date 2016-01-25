<?php

namespace Yajra\Datatables\Html;

use Illuminate\Support\Fluent;

/**
 * Class Column
 *
 * @package Yajra\Datatables\Html
 * @see     https://datatables.net/reference/option/ for possible columns option
 */
class Column extends Fluent
{
    /**
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        $attributes['orderable']  = isset($attributes['orderable']) ? $attributes['orderable'] : true;
        $attributes['searchable'] = isset($attributes['searchable']) ? $attributes['searchable'] : true;
        $attributes['exportable'] = isset($attributes['exportable']) ? $attributes['exportable'] : true;
        $attributes['printable']  = isset($attributes['printable']) ? $attributes['printable'] : true;

        // Allow methods override attribute value
        foreach ($attributes as $attribute => $value) {
            $method = 'parse' . ucfirst(strtolower($attribute));
            if (method_exists($this, $method)) {
                $attributes[$attribute] = $this->$method($value);
            }
        }

        parent::__construct($attributes);
    }

    /**
     * Parse render attribute.
     *
     * @param mixed $value
     * @return string|null
     */
    public function parseRender($value)
    {
        /** @var \Illuminate\Contracts\View\Factory $view */
        $view = app('view');

        if (is_callable($value)) {
            return value($value);
        } elseif ($view->exists($value)) {
            return $view->make($value)->render();
        }

        return $value ? $this->parseRenderAsString($value) : null;
    }

    /**
     * Display render value as is.
     *
     * @param mixed $value
     * @return string
     */
    private function parseRenderAsString($value)
    {
        return "function(data,type,full,meta){return $value;}";
    }
}
