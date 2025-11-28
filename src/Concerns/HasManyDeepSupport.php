<?php

namespace Yajra\DataTables\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Trait to support HasManyDeep relationships in EloquentDataTable.
 * This trait encapsulates all HasManyDeep-related methods to keep the main class smaller.
 */
trait HasManyDeepSupport
{
    /**
     * Check if a relation is a HasManyDeep relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model, mixed>  $model
     */
    protected function isHasManyDeep($model): bool
    {
        return class_exists(\Staudenmeir\EloquentHasManyDeep\HasManyDeep::class)
            && $model instanceof \Staudenmeir\EloquentHasManyDeep\HasManyDeep;
    }

    /**
     * Get the foreign key name for a HasManyDeep relationship.
     * This is the foreign key on the final related table that points to the intermediate table.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    protected function getHasManyDeepForeignKey($model): string
    {
        // Try to get from relationship definition using reflection
        $foreignKeys = $this->getForeignKeys($model);
        if (! empty($foreignKeys)) {
            return $this->extractColumnFromQualified(end($foreignKeys));
        }

        // Try to get the foreign key using common HasManyDeep methods
        if (method_exists($model, 'getForeignKeyName')) {
            return $model->getForeignKeyName();
        }

        // Fallback: try to infer from intermediate model or use related model's key
        $intermediateTable = $this->getHasManyDeepIntermediateTable($model);

        return $intermediateTable
            ? \Illuminate\Support\Str::singular($intermediateTable).'_id'
            : $model->getRelated()->getKeyName();
    }

    /**
     * Get the local key name for a HasManyDeep relationship.
     * This is the local key on the intermediate table (or parent if no intermediate).
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    protected function getHasManyDeepLocalKey($model): string
    {
        // Try to get from relationship definition using reflection
        $localKeys = $this->getLocalKeys($model);
        if (! empty($localKeys)) {
            return $this->extractColumnFromQualified(end($localKeys));
        }

        // Try to get the local key using common HasManyDeep methods
        if (method_exists($model, 'getLocalKeyName')) {
            return $model->getLocalKeyName();
        }

        // Fallback: use the intermediate model's key name, or parent if no intermediate
        $intermediateTable = $this->getHasManyDeepIntermediateTable($model);
        $through = $this->getThroughModels($model);
        $fallbackKey = $model->getParent()->getKeyName();
        if ($intermediateTable && ! empty($through)) {
            $firstThrough = is_string($through[0]) ? $through[0] : get_class($through[0]);
            if (class_exists($firstThrough)) {
                $fallbackKey = app($firstThrough)->getKeyName();
            }
        }

        return $fallbackKey;
    }

    /**
     * Get the intermediate table name for a HasManyDeep relationship.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    protected function getHasManyDeepIntermediateTable($model): ?string
    {
        // Try to get intermediate models from the relationship
        // HasManyDeep stores intermediate models in a protected property
        $through = $this->getThroughModels($model);
        if (! empty($through)) {
            // Get the first intermediate model
            $firstThrough = is_string($through[0]) ? $through[0] : get_class($through[0]);
            if (class_exists($firstThrough)) {
                $throughModel = app($firstThrough);

                return $throughModel->getTable();
            }
        }

        return null;
    }

    /**
     * Get the foreign key for joining to the intermediate table.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    protected function getHasManyDeepIntermediateForeignKey($model): string
    {
        // The foreign key on the intermediate table that points to the parent
        // For User -> Posts -> Comments, this would be posts.user_id
        $parent = $model->getParent();

        // Try to get from relationship definition
        $foreignKeys = $this->getForeignKeys($model);
        if (! empty($foreignKeys)) {
            $firstFK = $foreignKeys[0];

            return $this->extractColumnFromQualified($firstFK);
        }

        // Default: assume intermediate table has a foreign key named {parent_table}_id
        return \Illuminate\Support\Str::singular($parent->getTable()).'_id';
    }

    /**
     * Get the local key for joining from the parent to the intermediate table.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    protected function getHasManyDeepIntermediateLocalKey($model): string
    {
        // The local key on the parent table
        return $model->getParent()->getKeyName();
    }

    /**
     * Extract the array of foreign keys from a HasManyDeep relationship using reflection.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    private function getForeignKeys($model): array
    {
        try {
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('foreignKeys')) {
                $property = $reflection->getProperty('foreignKeys');
                // Safe: Accessing protected property from third-party package (staudenmeir/eloquent-has-many-deep)
                // The property exists and is part of the package's internal API
                $property->setAccessible(true); // NOSONAR
                $foreignKeys = $property->getValue($model); // NOSONAR
                if (is_array($foreignKeys) && ! empty($foreignKeys)) {
                    return $foreignKeys;
                }
            }
        } catch (\Exception) {
            // Reflection failed - fall back to empty array
            // This is safe because callers handle empty arrays appropriately
        }

        return [];
    }

    /**
     * Extract the array of local keys from a HasManyDeep relationship using reflection.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    private function getLocalKeys($model): array
    {
        try {
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('localKeys')) {
                $property = $reflection->getProperty('localKeys');
                // Safe: Accessing protected property from third-party package (staudenmeir/eloquent-has-many-deep)
                // The property exists and is part of the package's internal API
                $property->setAccessible(true); // NOSONAR
                $localKeys = $property->getValue($model); // NOSONAR
                if (is_array($localKeys) && ! empty($localKeys)) {
                    return $localKeys;
                }
            }
        } catch (\Exception) {
            // Reflection failed - fall back to empty array
            // This is safe because callers handle empty arrays appropriately
        }

        return [];
    }

    /**
     * Extract the array of through models from a HasManyDeep relationship using reflection.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep<\Illuminate\Database\Eloquent\Model, \Illuminate\Database\Eloquent\Model>  $model
     */
    private function getThroughModels($model): array
    {
        try {
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('through')) {
                $property = $reflection->getProperty('through');
                // Safe: Accessing protected property from third-party package (staudenmeir/eloquent-has-many-deep)
                // The property exists and is part of the package's internal API
                $property->setAccessible(true); // NOSONAR
                $through = $property->getValue($model); // NOSONAR
                if (is_array($through) && ! empty($through)) {
                    return $through;
                }
            }
        } catch (\Exception) {
            // Reflection failed - fall back to empty array
            // This is safe because callers handle empty arrays appropriately
        }

        return [];
    }

    /**
     * Extract the column name from a qualified column name (e.g., 'table.column' -> 'column').
     */
    private function extractColumnFromQualified(string $qualified): string
    {
        if (str_contains($qualified, '.')) {
            $parts = explode('.', $qualified);

            return end($parts);
        }

        return $qualified;
    }
}
