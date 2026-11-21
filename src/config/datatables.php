<?php

use Yajra\DataTables\ApiResourceDataTable;
use Yajra\DataTables\CollectionDataTable;
use Yajra\DataTables\EloquentDataTable;
use Yajra\DataTables\PaginatorDataTable;
use Yajra\DataTables\QueryDataTable;

return [
    /*
     * DataTables search options.
     */
    'search' => [
        /*
         * Smart search will enclose search keyword with wildcard string "%keyword%".
         * SQL: column LIKE "%keyword%"
         */
        'smart' => true,

        /*
         * Multi-term search will explode search keyword using spaces resulting into multiple term search.
         */
        'multi_term' => true,

        /*
         * Case insensitive will search the keyword in lower case format.
         * SQL: LOWER(column) LIKE LOWER(keyword)
         */
        'case_insensitive' => true,

        /*
         * Wild card will add "%" in between every characters of the keyword.
         * SQL: column LIKE "%k%e%y%w%o%r%d%"
         */
        'use_wildcards' => false,

        /*
         * Perform a search which starts with the given keyword.
         * SQL: column LIKE "keyword%"
         */
        'starts_with' => false,
    ],

    /*
     * DataTables internal index id response column name.
     */
    'index_column' => 'DT_RowIndex',

    /*
     * Maximum number of records that can be requested per page.
     * A request asking for more, or for all the records using a length of -1,
     * is capped to this value. Set to null to allow any length.
     */
    'max_length' => null,

    /*
     * List of available builders for DataTables.
     * This is where you can register your custom DataTables builder.
     */
    'engines' => [
        'eloquent' => EloquentDataTable::class,
        'query' => QueryDataTable::class,
        'collection' => CollectionDataTable::class,
        'paginator' => PaginatorDataTable::class,
        'resource' => ApiResourceDataTable::class,
    ],

    /*
     * DataTables accepted builder to engine mapping.
     * This is where you can override which engine a builder should use
     * Note, only change this if you know what you are doing!
     */
    'builders' => [
        // Illuminate\Database\Eloquent\Relations\Relation::class => 'eloquent',
        // Illuminate\Database\Eloquent\Builder::class            => 'eloquent',
        // Illuminate\Database\Query\Builder::class               => 'query',
        // Illuminate\Support\Collection::class                   => 'collection',
        // Illuminate\Pagination\LengthAwarePaginator::class      => 'paginator',
    ],

    /*
     * Nulls last sql pattern for PostgreSQL & Oracle.
     * For MySQL, use 'CASE WHEN :column IS NULL THEN 1 ELSE 0 END, :column :direction'
     */
    'nulls_last_sql' => ':column :direction NULLS LAST',

    /*
     * User friendly message to be displayed on user if error occurs.
     * Possible values:
     * null             - The exception message will be used on error response.
     * 'throw'          - Throws a \Yajra\DataTables\Exceptions\Exception. Use your custom error handler if needed.
     * 'custom message' - Any friendly message to be displayed to the user. You can also use translation key.
     */
    'error' => env('DATATABLES_ERROR', null),

    /*
     * Default columns definition of DataTable utility functions.
     */
    'columns' => [
        /*
         * List of columns hidden/removed on json response.
         */
        'excess' => ['rn', 'row_num'],

        /*
         * List of columns to be escaped. If set to *, all columns are escape.
         * Note: You can set the value to empty array to disable XSS protection.
         */
        'escape' => '*',

        /*
         * List of columns that are allowed to display html content.
         * Note: Adding columns to list will make us available to XSS attacks.
         */
        'raw' => ['action'],

        /*
         * List of columns are forbidden from being searched/sorted.
         */
        'blacklist' => ['password', 'remember_token'],

        /*
         * List of columns that are only allowed for search/sort.
         * If set to *, all columns are allowed.
         */
        'whitelist' => '*',
    ],

    /*
     * JsonResponse header and options config.
     */
    'json' => [
        'header' => [],
        'options' => 0,
    ],

    /*
     * Cache of the counts and rows read by the DataTables that call cache().
     * Nothing is cached unless cache() is called on a DataTable.
     */
    'cache' => [
        /*
         * Set to false to bypass the cache of every DataTable, e.g. during an incident.
         * Flushes keep running so that no stale entry is served once it is enabled again.
         */
        'enabled' => env('DATATABLES_CACHE_ENABLED', true),

        /*
         * Cache store from config/cache.php, null to use the default store.
         */
        'store' => env('DATATABLES_CACHE_STORE'),

        /*
         * Default time to live of the cached reads, in seconds.
         */
        'ttl' => 60,

        /*
         * Maximum number of entries a DataTable may write per minute, null for no limit.
         * Searches, orders and pages come from the request: the limit prevents a client
         * sending random values from filling the cache store. Use a dedicated store
         * so that the entries never evict the sessions or other data of the application.
         */
        'max_writes_per_minute' => 1000,

        /*
         * Maximum number of rows a cached read may store, null for no limit. Reads
         * without paging, e.g. exports, may return the whole table: beyond the
         * limit, the rows are read from the database and not stored.
         */
        'max_rows' => 1000,

        /*
         * Prefix of the cache keys. Changing it discards every cached read at once.
         */
        'prefix' => env('DATATABLES_CACHE_PREFIX', 'datatables'),
    ],

    /*
     * Default condition to determine if a parameter is a callback or not.
     * Callbacks needs to start by those terms, or they will be cast to string.
     */
    'callback' => ['$', '$.', 'function'],
];
