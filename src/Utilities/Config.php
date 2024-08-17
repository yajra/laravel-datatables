<?php

namespace Yajra\DataTables\Utilities;

use Illuminate\Contracts\Config\Repository;

class Config
{
    /**
     * Config constructor.
     */
    public function __construct(private readonly Repository $repository)
    {
    }

    /**
     * Check if config uses wild card search.
     */
    public function isWildcard(): bool
    {
        return (bool) $this->repository->get('datatables.search.use_wildcards', false);
    }

    /**
     * Check if config uses smart search.
     */
    public function isSmartSearch(): bool
    {
        return (bool) $this->repository->get('datatables.search.smart', true);
    }

    /**
     * Check if config uses case-insensitive search.
     */
    public function isCaseInsensitive(): bool
    {
        return (bool) $this->repository->get('datatables.search.case_insensitive', false);
    }

    /**
     * Check if app is in debug mode.
     */
    public function isDebugging(): bool
    {
        return (bool) $this->repository->get('app.debug', false);
    }

    /**
     * Get the specified configuration value.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key, mixed $default = null)
    {
        return $this->repository->get($key, $default);
    }

    /**
     * Set a given configuration value.
     *
     * @param  array|string  $key
     * @return void
     */
    public function set($key, mixed $value = null)
    {
        $this->repository->set($key, $value);
    }

    /**
     * Check if dataTable config uses multi-term searching.
     */
    public function isMultiTerm(): bool
    {
        return (bool) $this->repository->get('datatables.search.multi_term', true);
    }

    /**
     * Check if dataTable config uses starts_with searching.
     */
    public function isStartsWithSearch(): bool
    {
        return (bool) $this->repository->get('datatables.search.starts_with', false);
    }

    public function jsonOptions(): int
    {
        /** @var int $options */
        $options = $this->repository->get('datatables.json.options', 0);

        return $options;
    }

    public function jsonHeaders(): array
    {
        return (array) $this->repository->get('datatables.json.header', []);
    }
}
