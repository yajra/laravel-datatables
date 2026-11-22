<?php

namespace Yajra\DataTables\Cache;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use ReflectionMethod;
use stdClass;

/**
 * Converts the results of the reads to and from the values stored in the cache.
 *
 * Only scalar values are stored, never objects: the rows are restored as
 * plain objects or arrays, and the models are hydrated from them.
 */
final class Payload
{
    /**
     * Check if a custom Eloquent builder changes how the records are retrieved.
     *
     * Such a builder may not build its records from the rows of its query
     * alone, so they could not be restored from the cached rows.
     *
     * @param  EloquentBuilder<Model>  $builder
     * @param  list<string>  $methods
     */
    public static function overridesRetrieval(EloquentBuilder $builder, array $methods): bool
    {
        foreach ($methods as $method) {
            if (method_exists($builder, $method)
                && (new ReflectionMethod($builder, $method))->getDeclaringClass()->getName() !== EloquentBuilder::class
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert the rows of a query into a cacheable payload.
     *
     * Returns null when a row holds anything else than scalar values, e.g. a
     * stream or an object built by an "after query" callback.
     *
     * @param  iterable<array-key, mixed>  $rows
     * @return array{objects: bool, rows: list<array<array-key, scalar|null>>}|null
     */
    public static function exportRows(iterable $rows): ?array
    {
        $objects = null;
        $export = [];

        foreach ($rows as $row) {
            $isObject = is_object($row) && $row::class === stdClass::class;
            $values = $isObject || is_array($row) ? (array) $row : null;

            // The rows must all be plain objects or all be arrays, holding scalar values only.
            if (is_null($values) || ($objects ?? $isObject) !== $isObject || ! self::isScalar($values)) {
                return null;
            }

            $objects = $isObject;
            $export[] = $values;
        }

        return ['objects' => $objects ?? true, 'rows' => $export];
    }

    /**
     * Restore the rows of a cached payload, or null if the payload is invalid.
     *
     * @return list<stdClass|array<array-key, scalar|null>>|null
     */
    public static function importRows(mixed $data): ?array
    {
        if (! is_array($data)
            || ! is_bool($data['objects'] ?? null)
            || ! is_array($data['rows'] ?? null)
            || ! array_is_list($data['rows'])
        ) {
            return null;
        }

        $rows = [];

        foreach ($data['rows'] as $row) {
            if (! is_array($row) || ! self::isScalar($row)) {
                return null;
            }

            $rows[] = $data['objects'] ? (object) $row : $row;
        }

        return $rows;
    }

    /**
     * Restore a cached count, or null if the payload is invalid.
     */
    public static function importCount(mixed $data): ?int
    {
        return is_int($data) && $data >= 0 ? $data : null;
    }

    /**
     * Count the rows of a cacheable payload.
     */
    public static function rowCount(mixed $data): int
    {
        return is_array($data) && is_array($data['rows'] ?? null) ? count($data['rows']) : 0;
    }

    /**
     * Check if the given values are all scalar values or null.
     *
     * @param  array<array-key, mixed>  $values
     */
    public static function isScalar(array $values): bool
    {
        foreach ($values as $value) {
            if (! is_null($value) && ! is_scalar($value)) {
                return false;
            }
        }

        return true;
    }
}
