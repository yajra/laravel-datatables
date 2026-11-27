<?php

namespace Yajra\DataTables\Exceptions;

/**
 * Thrown when DataTables::flushCache() cannot prove that the tags were flushed,
 * so the results cached before the flush may still be served.
 */
class CacheFlushException extends Exception {}
