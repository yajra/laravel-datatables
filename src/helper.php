<?php

use Yajra\DataTables\DataTables;

if (!function_exists('config_path')) {
    /**
     * Get the configuration path.
     *
     * @param  string $path
     * @return string
     */
    function config_path($path = '')
    {
        return app()->basePath() . '/config' . ($path ? '/' . $path : $path);
    }
}

if (!function_exists('public_path')) {
    /**
     * Return the path to public dir
     *
     * @param null $path
     * @return string
     */
    function public_path($path = null)
    {
        return rtrim(app()->basePath('public/' . $path), '/');
    }
}

if (!function_exists('datatables')) {
    /**
     * Helper to make a new DataTable instance from source.
     * Or return a new factory is source is not set.
     *
     * @param mixed $source
     * @return \Yajra\DataTables\DataTableAbstract|\Yajra\DataTables\DataTables
     */
    function datatables($source = null)
    {
        if ($source) {
            return DataTables::make($source);
        }

        return new DataTables;
    }
}
