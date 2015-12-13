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

        // Allow methods override attribute value
        foreach($attributes as $attribute => $value) {
            $method = 'parse' . ucfirst(strtolower($attribute));
            if(method_exists($this, $method)) {
                $attributes[$attribute] = $this->$method($value);
            }
        }

        parent::__construct($attributes);
    }

    /**
     * Parse Render
     *
     * @param $value
     *
     * @return string
     */
    public function parseRender($value)
    {
        $value = $value ?: 'datatables::action';
        $value = $value ?: $this->config->get('datatables.render_template', 'datatables::action');

        if(is_callable($value)) {
            $value = value($value);
        } else {
            $value = view($value)->render();
        }

        $value = preg_replace("/\r|\n/", '', $value);

        return $value ?: null;
    }
}
