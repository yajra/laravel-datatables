<?php

namespace Yajra\DataTables\Utilities;

use Illuminate\Contracts\Config\Repository;

class Config
{
    /**
     * @var \Illuminate\Contracts\Config\Repository
     */
    private $repository;

    /**
     * Config constructor.
     *
     * @param \Illuminate\Contracts\Config\Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Check if config uses wild card search.
     *
     * @return bool
     */
    public function isWildcard()
    {
        return $this->repository->get('datatables.search.use_wildcards', false);
    }

    /**
     * Check if config uses smart search.
     *
     * @return bool
     */
    public function isSmartSearch()
    {
        return $this->repository->get('datatables.search.smart', true);
    }

    /**
     * Check if config uses case insensitive search.
     *
     * @return bool
     */
    public function isCaseInsensitive()
    {
        return $this->repository->get('datatables.search.case_insensitive', false);
    }

    /**
     * Check if app is in debug mode.
     *
     * @return bool
     */
    public function isDebugging()
    {
        return $this->repository->get('app.debug', false);
    }

    /**
     * Get the specified configuration value.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return $this->repository->get($key, $default);
    }

    /**
     * Set a given configuration value.
     *
     * @param  array|string $key
     * @param  mixed        $value
     * @return void
     */
    public function set($key, $value = null)
    {
        $this->repository->set($key, $value);
    }

    /**
     * Check if dataTable config uses multi-term searching.
     *
     * @return bool
     */
    public function isMultiTerm()
    {
        return $this->repository->get('datatables.search.multi_term', true);
    }

    /**
     * Check if dataTable config uses starts_with searching.
     *
     * @return bool
     */
    public function isStartsWithSearch()
    {
        return $this->repository->get('datatables.search.starts_with', false);
    }
}
