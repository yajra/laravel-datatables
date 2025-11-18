<?php

namespace Yajra\DataTables;

use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Yajra\DataTables\Exceptions\Exception;

/**
 * @property EloquentBuilder $query
 */
class EloquentDataTable extends QueryDataTable
{
    /**
     * Flag to enable the generation of unique table aliases on eagerly loaded join columns.
     * You may want to enable it if you encounter a "Not unique table/alias" error when performing a search or applying ordering.
     */
    protected bool $enableEagerJoinAliases = false;

    /**
     * EloquentEngine constructor.
     */
    public function __construct(Model|EloquentBuilder $model)
    {
        $builder = match (true) {
            $model instanceof Model => $model->newQuery(),
            $model instanceof Relation => $model->getQuery(),
            $model instanceof EloquentBuilder => $model,
        };

        parent::__construct($builder->getQuery());

        $this->query = $builder;
    }

    /**
     * Can the DataTable engine be created with these parameters.
     *
     * @param  mixed  $source
     */
    public static function canCreate($source): bool
    {
        return $source instanceof EloquentBuilder;
    }

    /**
     * Add columns in collection.
     *
     * @param  bool|int  $order
     * @return $this
     */
    public function addColumns(array $names, $order = false)
    {
        foreach ($names as $name => $attribute) {
            if (is_int($name)) {
                $name = $attribute;
            }

            $this->addColumn($name, fn ($model) => $model->getAttribute($attribute), is_int($order) ? $order++ : $order);
        }

        return $this;
    }

    /**
     * If column name could not be resolved then use primary key.
     */
    protected function getPrimaryKeyName(): string
    {
        return $this->query->getModel()->getKeyName();
    }

    /**
     * {@inheritDoc}
     */
    protected function compileQuerySearch($query, string $column, string $keyword, string $boolean = 'or', bool $nested = false): void
    {
        if (substr_count($column, '.') > 1) {
            $parts = explode('.', $column);
            $firstRelation = array_shift($parts);
            $column = implode('.', $parts);

            if ($this->isMorphRelation($firstRelation)) {
                $query->{$boolean.'WhereHasMorph'}(
                    $firstRelation,
                    '*',
                    function (EloquentBuilder $query) use ($column, $keyword) {
                        parent::compileQuerySearch($query, $column, $keyword, '');
                    }
                );
            } else {
                $query->{$boolean.'WhereHas'}($firstRelation, function (EloquentBuilder $query) use ($column, $keyword) {
                    self::compileQuerySearch($query, $column, $keyword, '', true);
                });
            }

            return;
        }

        $parts = explode('.', $column);
        $newColumn = array_pop($parts);
        $relation = implode('.', $parts);

        if (! $nested && $this->isNotEagerLoaded($relation)) {
            parent::compileQuerySearch($query, $column, $keyword, $boolean);

            return;
        }

        if ($this->isMorphRelation($relation)) {
            $query->{$boolean.'WhereHasMorph'}(
                $relation,
                '*',
                function (EloquentBuilder $query) use ($newColumn, $keyword) {
                    parent::compileQuerySearch($query, $newColumn, $keyword, '');
                }
            );
        } else {
            $query->{$boolean.'WhereHas'}($relation, function (EloquentBuilder $query) use ($newColumn, $keyword) {
                parent::compileQuerySearch($query, $newColumn, $keyword, '');
            });
        }
    }

    /**
     * Check if a relation was not used on eager loading.
     *
     * @param  string  $relation
     * @return bool
     */
    protected function isNotEagerLoaded($relation)
    {
        return ! $relation
            || ! array_key_exists($relation, $this->query->getEagerLoads())
            || $relation === $this->query->getModel()->getTable();
    }

    /**
     * Check if a relation is a morphed one or not.
     *
     * @param  string  $relation
     * @return bool
     */
    protected function isMorphRelation($relation)
    {
        $isMorph = false;
        if ($relation !== null && $relation !== '') {
            $relationParts = explode('.', $relation);
            $firstRelation = array_shift($relationParts);
            $model = $this->query->getModel();
            $isMorph = method_exists($model, $firstRelation) && $model->$firstRelation() instanceof MorphTo;
        }

        return $isMorph;
    }

    /**
     * Check if a relation is a HasManyDeep relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $model
     */
    protected function isHasManyDeep($model): bool
    {
        return class_exists('Staudenmeir\EloquentHasManyDeep\HasManyDeep')
            && $model instanceof \Staudenmeir\EloquentHasManyDeep\HasManyDeep;
    }

    /**
     * Get the foreign key name for a HasManyDeep relationship.
     * This is the foreign key on the final related table that points to the intermediate table.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
     */
    protected function getHasManyDeepForeignKey($model): string
    {
        // Try to get from relationship definition using reflection
        $foreignKeys = $this->getForeignKeys($model);
        if (! empty($foreignKeys)) {
            // Get the last foreign key (for the final join)
            $lastFK = end($foreignKeys);

            return $this->extractColumnFromQualified($lastFK);
        }

        // Try to get the foreign key using common HasManyDeep methods
        if (method_exists($model, 'getForeignKeyName')) {
            return $model->getForeignKeyName();
        }

        // HasManyDeep may use getQualifiedForeignKeyName() and extract the column
        if (method_exists($model, 'getQualifiedForeignKeyName')) {
            $qualified = $model->getQualifiedForeignKeyName();

            return $this->extractColumnFromQualified($qualified);
        }

        // Fallback: try to infer from intermediate model
        $intermediateTable = $this->getHasManyDeepIntermediateTable($model, '');
        if ($intermediateTable) {
            // Assume the related table has a foreign key named {intermediate_table}_id
            return \Illuminate\Support\Str::singular($intermediateTable).'_id';
        }

        // Final fallback: use the related model's key name
        return $model->getRelated()->getKeyName();
    }

    /**
     * Get the local key name for a HasManyDeep relationship.
     * This is the local key on the intermediate table (or parent if no intermediate).
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
     */
    protected function getHasManyDeepLocalKey($model): string
    {
        // Try to get from relationship definition using reflection
        $localKeys = [];
        try {
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('localKeys')) {
                $property = $reflection->getProperty('localKeys');
                $property->setAccessible(true);
                $localKeys = $property->getValue($model);
            }
        } catch (\Exception $e) {
            // Reflection failed - proceed to other methods
            // This is safe because we have multiple fallback strategies
        }

        if (is_array($localKeys) && ! empty($localKeys)) {
            // Get the last local key (for the final join)
            $lastLK = end($localKeys);

            return $this->extractColumnFromQualified($lastLK);
        }

        // Try to get the local key using common HasManyDeep methods
        if (method_exists($model, 'getLocalKeyName')) {
            return $model->getLocalKeyName();
        }

        // HasManyDeep may use getQualifiedLocalKeyName() and extract the column
        if (method_exists($model, 'getQualifiedLocalKeyName')) {
            $qualified = $model->getQualifiedLocalKeyName();

            return $this->extractColumnFromQualified($qualified);
        }

        // Fallback: use the intermediate model's key name, or parent if no intermediate
        $intermediateTable = $this->getHasManyDeepIntermediateTable($model, '');
        if ($intermediateTable) {
            $through = $this->getThroughModels($model);
            if (! empty($through)) {
                $firstThrough = is_string($through[0]) ? $through[0] : get_class($through[0]);
                if (class_exists($firstThrough)) {
                    $throughModel = app($firstThrough);

                    return $throughModel->getKeyName();
                }
            }
        }

        // Final fallback: use the parent model's key name
        return $model->getParent()->getKeyName();
    }

    /**
     * Get the intermediate table name for a HasManyDeep relationship.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
     * @param  string  $lastAlias
     */
    protected function getHasManyDeepIntermediateTable($model, $lastAlias): ?string
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
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
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
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
     */
    protected function getHasManyDeepIntermediateLocalKey($model): string
    {
        // The local key on the parent table
        return $model->getParent()->getKeyName();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    protected function resolveRelationColumn(string $column): string
    {
        $parts = explode('.', $column);
        $columnName = array_pop($parts);
        $relation = preg_replace('/\[.*?\]/', '', implode('.', $parts));

        if ($this->isNotEagerLoaded($relation)) {
            return parent::resolveRelationColumn($column);
        }

        return $this->joinEagerLoadedColumn($relation, $columnName);
    }

    /**
     * Join eager loaded relation and get the related column name.
     *
     * @param  string  $relation
     * @param  string  $relationColumn
     * @return string
     *
     * @throws \Yajra\DataTables\Exceptions\Exception
     */
    protected function joinEagerLoadedColumn($relation, $relationColumn)
    {
        $tableAlias = $pivotAlias = '';
        $lastQuery = $this->query;
        foreach (explode('.', $relation) as $eachRelation) {
            $model = $lastQuery->getRelation($eachRelation);
            if ($this->enableEagerJoinAliases) {
                $lastAlias = $tableAlias ?: $this->getTablePrefix($lastQuery);
                $tableAlias = $tableAlias.'_'.$eachRelation;
                $pivotAlias = $tableAlias.'_pivot';
            } else {
                $lastAlias = $tableAlias ?: $lastQuery->getModel()->getTable();
            }
            switch (true) {
                case $model instanceof BelongsToMany:
                    if ($this->enableEagerJoinAliases) {
                        $pivot = $model->getTable().' as '.$pivotAlias;
                    } else {
                        $pivot = $pivotAlias = $model->getTable();
                    }
                    $pivotPK = $pivotAlias.'.'.$model->getForeignPivotKeyName();
                    $pivotFK = ltrim($lastAlias.'.'.$model->getParentKeyName(), '.');
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    if ($this->enableEagerJoinAliases) {
                        $table = $related->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $related->getTable();
                    }
                    $tablePK = $model->getRelatedPivotKeyName();
                    $foreign = $pivotAlias.'.'.$tablePK;
                    $other = $tableAlias.'.'.$related->getKeyName();

                    $lastQuery->addSelect($tableAlias.'.'.$relationColumn);

                    break;

                case $model instanceof HasOneThrough:
                    if ($this->enableEagerJoinAliases) {
                        $pivot = explode('.', $model->getQualifiedParentKeyName())[0].' as '.$pivotAlias;
                    } else {
                        $pivot = $pivotAlias = explode('.', $model->getQualifiedParentKeyName())[0];
                    }
                    $pivotPK = $pivotAlias.'.'.$model->getFirstKeyName();
                    $pivotFK = ltrim($lastAlias.'.'.$model->getLocalKeyName(), '.');
                    $this->performJoin($pivot, $pivotPK, $pivotFK);

                    $related = $model->getRelated();
                    if ($this->enableEagerJoinAliases) {
                        $table = $related->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $related->getTable();
                    }
                    $tablePK = $model->getSecondLocalKeyName();
                    $foreign = $pivotAlias.'.'.$tablePK;
                    $other = $tableAlias.'.'.$related->getKeyName();

                    $lastQuery->addSelect($lastQuery->getModel()->getTable().'.*');

                    break;

                case $model instanceof HasOneOrMany:
                    if ($this->enableEagerJoinAliases) {
                        $table = $model->getRelated()->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $model->getRelated()->getTable();
                    }
                    $foreign = $tableAlias.'.'.$model->getForeignKeyName();
                    $other = ltrim($lastAlias.'.'.$model->getLocalKeyName(), '.');
                    break;

                case $model instanceof BelongsTo:
                    if ($this->enableEagerJoinAliases) {
                        $table = $model->getRelated()->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $model->getRelated()->getTable();
                    }
                    $foreign = ltrim($lastAlias.'.'.$model->getForeignKeyName(), '.');
                    $other = $tableAlias.'.'.$model->getOwnerKeyName();
                    break;

                case $this->isHasManyDeep($model):
                    // HasManyDeep relationships can traverse multiple intermediate models
                    // We need to join through all intermediate models to reach the final related table
                    $related = $model->getRelated();

                    // Get the qualified parent key to determine the first intermediate model
                    $qualifiedParentKey = $model->getQualifiedParentKeyName();
                    $parentTable = explode('.', $qualifiedParentKey)[0];

                    // For HasManyDeep, we need to join through intermediate models
                    // The relationship query already knows the structure, so we'll use it
                    // First, join to the first intermediate model (if not already joined)
                    $intermediateTable = $this->getHasManyDeepIntermediateTable($model, $lastAlias);

                    if ($intermediateTable && $intermediateTable !== $lastAlias) {
                        // Join to intermediate table first
                        if ($this->enableEagerJoinAliases) {
                            $intermediateAlias = $tableAlias.'_intermediate';
                            $intermediate = $intermediateTable.' as '.$intermediateAlias;
                        } else {
                            $intermediateAlias = $intermediateTable;
                            $intermediate = $intermediateTable;
                        }

                        $intermediateFK = $this->getHasManyDeepIntermediateForeignKey($model);
                        $intermediateLocal = $this->getHasManyDeepIntermediateLocalKey($model);
                        $this->performJoin($intermediate, $intermediateAlias.'.'.$intermediateFK, ltrim($lastAlias.'.'.$intermediateLocal, '.'));
                        $lastAlias = $intermediateAlias;
                    }

                    // Now join to the final related table
                    if ($this->enableEagerJoinAliases) {
                        $table = $related->getTable().' as '.$tableAlias;
                    } else {
                        $table = $tableAlias = $related->getTable();
                    }

                    // Get the foreign key on the related table (points to intermediate)
                    $foreignKey = $this->getHasManyDeepForeignKey($model);
                    $localKey = $this->getHasManyDeepLocalKey($model);

                    $foreign = $tableAlias.'.'.$foreignKey;
                    $other = ltrim($lastAlias.'.'.$localKey, '.');

                    $lastQuery->addSelect($tableAlias.'.'.$relationColumn);
                    break;

                default:
                    throw new Exception('Relation '.$model::class.' is not yet supported.');
            }
            $this->performJoin($table, $foreign, $other);
            $lastQuery = $model->getQuery();
        }

        return $tableAlias.'.'.$relationColumn;
    }

    /**
     * Enable the generation of unique table aliases on eagerly loaded join columns.
     * You may want to enable it if you encounter a "Not unique table/alias" error when performing a search or applying ordering.
     *
     * @return $this
     */
    public function enableEagerJoinAliases(): static
    {
        $this->enableEagerJoinAliases = true;

        return $this;
    }

    /**
     * Perform join query.
     *
     * @param  string  $table
     * @param  string  $foreign
     * @param  string  $other
     * @param  string  $type
     */
    protected function performJoin($table, $foreign, $other, $type = 'left'): void
    {
        $joins = [];
        $builder = $this->getBaseQueryBuilder();
        foreach ($builder->joins ?? [] as $join) {
            $joins[] = $join->table;
        }

        if (! in_array($table, $joins)) {
            $this->getBaseQueryBuilder()->join($table, $foreign, '=', $other, $type);
        }
    }

    /**
     * Extract the array of foreign keys from a HasManyDeep relationship using reflection.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
     * @return array
     */
    private function getForeignKeys($model): array
    {
        try {
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('foreignKeys')) {
                $property = $reflection->getProperty('foreignKeys');
                $property->setAccessible(true);
                $foreignKeys = $property->getValue($model);
                if (is_array($foreignKeys) && ! empty($foreignKeys)) {
                    return $foreignKeys;
                }
            }
        } catch (\Exception $e) {
            // Reflection failed - fall back to empty array
            // This is safe because callers handle empty arrays appropriately
        }

        return [];
    }

    /**
     * Extract the array of through models from a HasManyDeep relationship using reflection.
     *
     * @param  \Staudenmeir\EloquentHasManyDeep\HasManyDeep  $model
     * @return array
     */
    private function getThroughModels($model): array
    {
        try {
            $reflection = new \ReflectionClass($model);
            if ($reflection->hasProperty('through')) {
                $property = $reflection->getProperty('through');
                $property->setAccessible(true);
                $through = $property->getValue($model);
                if (is_array($through) && ! empty($through)) {
                    return $through;
                }
            }
        } catch (\Exception $e) {
            // Reflection failed - fall back to empty array
            // This is safe because callers handle empty arrays appropriately
        }

        return [];
    }

    /**
     * Extract the column name from a qualified column name (e.g., 'table.column' -> 'column').
     *
     * @param  string  $qualified
     * @return string
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
