<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Query;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Core\Schema\Definition\FieldDefinition;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Scope\ScopeManager;
use PTAdmin\Easy\Easy;

/**
 * 查询执行器.
 *
 * 当前负责 detail/list 的基础查询能力，并在执行前
 * 使用字段能力信息约束过滤和排序行为。
 */
class QueryEngine
{
    /** @var string[] */
    private const AGGREGATE_TYPES = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * 查询单条详情数据.
     */
    public function detail(SchemaDefinition $definition, $id, ?ExecutionContext $context = null)
    {
        $builder = $definition->document()->query();
        $this->applyScope($builder, $definition, 'detail', $context);

        $record = $builder->where($definition->primaryKey(), $id)->first();
        if (null !== $record) {
            $this->loadRequestedRelations($definition, [$record], [], $context);
        }

        return $record;
    }

    /**
     * 查询列表数据.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>|array<int, mixed>
     */
    public function lists(SchemaDefinition $definition, array $query = [], ?ExecutionContext $context = null): array
    {
        $criteria = QueryCriteria::fromArray($query);
        $document = $definition->document();
        $builder = $document->query();

        $this->applyScope($builder, $definition, 'list', $context);

        foreach ($criteria->filters() as $filter) {
            if ($this->applyRelationFieldFilter($builder, $definition, $filter)) {
                continue;
            }

            if ($this->applyLocalAppendFilter($builder, $definition, $filter)) {
                continue;
            }

            if ($this->applyRelationAppendFilter($builder, $definition, $filter)) {
                continue;
            }

            $capabilities = $this->capabilitiesFor($definition, $filter->field());
            if (null === $capabilities) {
                continue;
            }
            if (false === (bool) ($capabilities['filterable'] ?? false)) {
                continue;
            }
            if (!$this->supportsOperator($capabilities['operators'] ?? [], $filter->operator())) {
                continue;
            }

            $this->applyFilter($builder, $filter);
        }

        $this->applyKeyword($builder, $definition, $criteria);

        foreach ($criteria->sorts() as $sort) {
            if ($this->applyRelationFieldSort($builder, $definition, $sort->field(), $sort->direction())) {
                continue;
            }

            if ($this->applyLocalAppendSort($builder, $definition, $sort->field(), $sort->direction())) {
                continue;
            }

            if ($this->applyRelationAppendSort($builder, $definition, $sort->field(), $sort->direction())) {
                continue;
            }

            $capabilities = $this->capabilitiesFor($definition, $sort->field());
            if (null === $capabilities) {
                continue;
            }
            if (false === (bool) ($capabilities['sortable'] ?? false)) {
                continue;
            }
            $builder->orderBy($sort->field(), $sort->direction());
        }

        $this->applyDefaultOrder($builder, $definition, $criteria);

        if ($criteria->paginate()) {
            $paginator = $builder->paginate($criteria->limit(), ['*'], 'page', $criteria->page());
            $this->loadRequestedRelations($definition, $paginator->getCollection()->all(), $query, $context);

            return $paginator->toArray();
        }

        if (null !== $criteria->limit() && $criteria->limit() > 0) {
            if ($criteria->page() > 1) {
                $builder->forPage($criteria->page(), $criteria->limit());
            } else {
                $builder->limit($criteria->limit());
            }
        }

        $records = $builder->get();
        $this->loadRequestedRelations($definition, $records->all(), $query, $context);

        return $records->toArray();
    }

    /**
     * 执行聚合查询.
     *
     * @param array<string, mixed> $query
     *
     * @return array<int, array<string, mixed>>
     */
    public function aggregate(SchemaDefinition $definition, array $query = [], ?ExecutionContext $context = null): array
    {
        $criteria = QueryCriteria::fromArray($query);
        $builder = $definition->document()->query();

        $this->applyScope($builder, $definition, 'aggregate', $context);

        foreach ($criteria->filters() as $filter) {
            if ($this->applyRelationFieldFilter($builder, $definition, $filter)) {
                continue;
            }

            if ($this->applyLocalAppendFilter($builder, $definition, $filter)) {
                continue;
            }

            if ($this->applyRelationAppendFilter($builder, $definition, $filter)) {
                continue;
            }

            $capabilities = $this->capabilitiesFor($definition, $filter->field());
            if (null === $capabilities) {
                continue;
            }
            if (false === (bool) ($capabilities['filterable'] ?? false)) {
                continue;
            }
            if (!$this->supportsOperator($capabilities['operators'] ?? [], $filter->operator())) {
                continue;
            }

            $this->applyFilter($builder, $filter);
        }

        $this->applyKeyword($builder, $definition, $criteria);

        $groups = $this->resolveGroupFields($definition, $criteria);
        $aggregates = $this->resolveAggregates($definition, $criteria);
        $aggregateAliases = [];

        if (0 !== \count($groups)) {
            $builder->select($groups);
            $builder->groupBy($groups);
        }

        foreach ($aggregates as $aggregate) {
            $alias = $this->aliasForAggregate($aggregate);
            $aggregateAliases[] = $alias;
            $builder->selectRaw($this->aggregateExpression($builder, $definition, $aggregate, $alias));
        }

        foreach ($criteria->sorts() as $sort) {
            if (\in_array($sort->field(), $groups, true) || \in_array($sort->field(), $aggregateAliases, true)) {
                $builder->orderBy($sort->field(), $sort->direction());
            }
        }

        if (null !== $criteria->limit() && $criteria->limit() > 0) {
            if ($criteria->page() > 1) {
                $builder->forPage($criteria->page(), $criteria->limit());
            } else {
                $builder->limit($criteria->limit());
            }
        }

        return $builder->toBase()->get()->map(function ($row) use ($aggregateAliases): array {
            return $this->normalizeAggregateRow((array) $row, $aggregateAliases);
        })->all();
    }

    /**
     * 判断字段是否支持指定操作符.
     *
     * @param string[] $operators
     */
    private function supportsOperator(array $operators, string $operator): bool
    {
        return \in_array(strtolower($operator), array_map('strtolower', $operators), true);
    }

    /**
     * 根据 query/context 配置按需加载关系数据。
     *
     * 默认只返回主记录本身，只有显式声明 `with(...)` /
     * `with_relations` 时，才批量加载完整关联对象。
     *
     * @param array<int, mixed> $records
     * @param array<string, mixed> $query
     */
    private function loadRequestedRelations(SchemaDefinition $definition, array $records, array $query = [], ?ExecutionContext $context = null): void
    {
        $relations = $this->requestedRelations($definition, $query, $context);
        if (0 === \count($relations)) {
            return;
        }

        $hasOne = [];
        $hasMany = [];
        $belongsTo = [];
        foreach ($relations as $name => $options) {
            $kind = (string) ($options['kind'] ?? '');
            if ('hasOne' === $kind) {
                $hasOne[$name] = $options;

                continue;
            }

            if ('hasMany' === $kind) {
                $hasMany[$name] = $options;

                continue;
            }

            if ('belongsTo' === $kind) {
                $belongsTo[$name] = $options;
            }
        }

        if (0 !== \count($hasOne)) {
            $definition->document()->preloadHasOneRelations($records, $hasOne);
        }
        if (0 !== \count($hasMany)) {
            $definition->document()->preloadHasManyRelations($records, $hasMany);
        }
        if (0 !== \count($belongsTo)) {
            $definition->document()->preloadBelongsToRelations($records, $belongsTo);
        }
    }

    /**
     * 处理基于关系展示字段的过滤条件。
     *
     * 例如前端对 `__category_id_text` 发起过滤时，这里会先查询
     * 关联资源拿到匹配主键，再回写到真实存储字段 `category_id` 上。
     *
     * @param mixed $builder
     */
    private function applyRelationAppendFilter($builder, SchemaDefinition $definition, QueryFilter $filter): bool
    {
        $field = $this->resolveRelationAppendField($definition, $filter->field());
        if (null === $field) {
            return false;
        }

        $matchedValues = $this->resolveRelationFilterValues($definition, $field, $filter);
        $operator = $filter->operator();
        if ('!=' === $operator) {
            if (0 === \count($matchedValues)) {
                return true;
            }

            $this->applyRelationStoredValueFilter($builder, $field, $matchedValues, true);

            return true;
        }

        if (0 === \count($matchedValues)) {
            $builder->whereRaw('1 = 0');

            return true;
        }

        $this->applyRelationStoredValueFilter($builder, $field, $matchedValues, false);

        return true;
    }

    /**
     * 处理基于本地 options 展示字段的过滤条件。
     *
     * 当前仅支持单值字段：
     * - `radio`
     * - `switch`
     * - 单选 `select`
     *
     * @param mixed $builder
     */
    private function applyLocalAppendFilter($builder, SchemaDefinition $definition, QueryFilter $filter): bool
    {
        $field = $this->resolveLocalAppendField($definition, $filter->field());
        if (null === $field || $this->isMultiValueOptionField($field)) {
            return false;
        }

        $operator = $filter->operator();
        if (!\in_array($operator, ['=', '!=', 'like', 'in'], true)) {
            return false;
        }

        $matchedValues = $this->resolveLocalOptionFilterValues($field, $filter);
        if ('!=' === $operator) {
            if (0 === \count($matchedValues)) {
                return true;
            }

            $builder->whereNotIn($field->name(), $matchedValues);

            return true;
        }

        if (0 === \count($matchedValues)) {
            $builder->whereRaw('1 = 0');

            return true;
        }

        $builder->whereIn($field->name(), $matchedValues);

        return true;
    }

    /**
     * 处理跨关系字段过滤。
     *
     * 支持如下写法：
     * - `category.status = 1`
     * - `seo.summary like %foo%`
     * - `comments.content like %bar%`
     *
     * 关系名既支持运行时别名，也支持原始字段名。
     *
     * @param mixed $builder
     */
    private function applyRelationFieldFilter($builder, SchemaDefinition $definition, QueryFilter $filter): bool
    {
        $resolved = $this->resolveRelationFieldPath($definition, $filter->field());
        if (null === $resolved) {
            return false;
        }

        $relation = $resolved['relation'];
        $kind = (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '');
        $resource = $resolved['resource'];
        $alias = 'easy_rel_filter_'.substr(md5($resolved['field_name'].$resolved['related_field'].$resource->getRawTable()), 0, 8);
        $qualifiedField = $this->qualifyRelationColumn($resolved['related_field'], $alias);
        $relationFilter = new QueryFilter($qualifiedField, $filter->operator(), $filter->value());

        $callback = function ($query) use ($definition, $relation, $kind, $alias, $resource, $resolved, $relationFilter): void {
            $query->selectRaw('1')
                ->from($resource->getRawTable().' as '.$alias)
            ;

            if ('belongsTo' === $kind) {
                $valueColumn = (string) ($relation['value'] ?? 'id');
                $query->whereColumn(
                    $this->qualifyRelationColumn($valueColumn, $alias),
                    $definition->name().'.'.$resolved['field_name']
                );
            } else {
                $foreignKey = (string) ($relation['foreign_key'] ?? '');
                $localKey = (string) ($relation['local_key'] ?? $definition->primaryKey());
                if ('' === $foreignKey || '' === $localKey) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereColumn(
                    $this->qualifyRelationColumn($foreignKey, $alias),
                    $definition->name().'.'.$localKey
                );
            }

            if ($resource->allowRecycle()) {
                $query->whereNull($this->qualifyRelationColumn('deleted_at', $alias));
            }

            $this->applyRelationResourceFilters($query, (array) ($relation['filter'] ?? []), $alias);
            $this->applyFilter($query, $relationFilter);
        };

        $builder->whereExists($callback);

        return true;
    }

    /**
     * 解析当前请求显式声明需要加载的关系。
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, array<string, mixed>>
     */
    private function requestedRelations(SchemaDefinition $definition, array $query = [], ?ExecutionContext $context = null): array
    {
        $available = $this->availableLoadableRelations($definition);
        if (0 === \count($available)) {
            return [];
        }

        $with = [];
        if (null !== $context) {
            $with = array_merge($with, $this->parseWithRelations($context->get('runtime.with', [])));
        }
        $with = array_merge($with, $this->parseWithRelations($query['with'] ?? []));

        $loadAll = true === (bool) ($query['with_relations'] ?? false);
        $requested = $loadAll ? $available : [];
        foreach ($with as $name => $options) {
            $resolved = $this->resolveRequestedRelationName($name, $available);
            if (null === $resolved) {
                continue;
            }

            $requested[$resolved] = array_replace($available[$resolved], $options);
        }

        foreach (array_keys($this->parseWithRelations($query['without'] ?? [])) as $name) {
            $resolved = $this->resolveRequestedRelationName($name, $available);
            if (null === $resolved) {
                continue;
            }

            unset($requested[$resolved]);
        }

        return $requested;
    }

    /**
     * 返回当前 schema 下可按需加载的关系。
     *
     * @return array<string, array<string, mixed>>
     */
    private function availableLoadableRelations(SchemaDefinition $definition): array
    {
        $relations = [];
        $resource = $definition->raw();
        $reservedNames = array_values(array_map(static function ($field): string {
            return $field->getName();
        }, $resource->getFields()));

        foreach ($resource->getRelations() as $fieldName => $relation) {
            $kind = (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '');
            if (!\in_array($kind, ['hasOne', 'hasMany', 'belongsTo'], true)) {
                continue;
            }

            $field = $resource->getField((string) $fieldName);
            if (null === $field) {
                continue;
            }

            if ('belongsTo' === $kind && $this->isMultiValueRawRelationField($field)) {
                continue;
            }

            $outputName = $this->relationOutputName($field, $reservedNames, array_keys($relations));
            $relations[$outputName] = [
                'field' => $field->getName(),
                'kind' => $kind,
                'columns' => [],
                'request_names' => $this->relationRequestNames($field, $outputName),
            ];
        }

        return $relations;
    }

    /**
     * 解析 `with('comments:id,content')` 风格关系声明。
     *
     * @param mixed $relations
     *
     * @return array<string, array<string, mixed>>
     */
    private function parseWithRelations($relations): array
    {
        $items = \is_array($relations) ? $relations : [$relations];
        $parsed = [];

        foreach ($items as $item) {
            if (!\is_string($item) || '' === trim($item)) {
                continue;
            }

            $segments = explode(':', trim($item), 2);
            $name = trim($segments[0]);
            if ('' === $name) {
                continue;
            }

            $columns = [];
            if (isset($segments[1])) {
                $columns = array_values(array_filter(array_map('trim', explode(',', $segments[1])), static function (string $column): bool {
                    return '' !== $column;
                }));
            }

            $parsed[$name] = [
                'columns' => $columns,
            ];
        }

        return $parsed;
    }

    /**
     * 将请求中的关系名映射到运行时内部的标准输出名。
     *
     * @param array<string, array<string, mixed>> $available
     */
    private function resolveRequestedRelationName(string $name, array $available): ?string
    {
        if (isset($available[$name])) {
            return $name;
        }

        foreach ($available as $outputName => $options) {
            if (\in_array($name, (array) ($options['request_names'] ?? []), true)) {
                return (string) $outputName;
            }
        }

        return null;
    }

    /**
     * 解析 `relation.field` 形式的跨关系字段路径。
     *
     * @return array<string, mixed>|null
     */
    private function resolveRelationFieldPath(SchemaDefinition $definition, string $path): ?array
    {
        $segments = explode('.', $path, 2);
        if (2 !== \count($segments)) {
            return null;
        }

        [$relationName, $relatedField] = $segments;
        $relationName = trim($relationName);
        $relatedField = trim($relatedField);
        if ('' === $relationName || '' === $relatedField) {
            return null;
        }

        $available = $this->availableLoadableRelations($definition);
        $resolvedName = $this->resolveRequestedRelationName($relationName, $available);
        if (null === $resolvedName) {
            return null;
        }

        $relationOptions = $available[$resolvedName] ?? null;
        $fieldName = \is_array($relationOptions) ? (string) ($relationOptions['field'] ?? '') : '';
        if ('' === $fieldName) {
            return null;
        }

        $relation = (array) ($definition->relations()[$fieldName] ?? []);
        if (0 === \count($relation)) {
            return null;
        }

        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        if ('' === $table) {
            return null;
        }

        $resource = $table === $definition->name()
            ? $definition->raw()
            : Easy::schema($table)->raw();
        if (!$this->relationResourceHasField($resource, $relatedField)) {
            return null;
        }

        return [
            'resolved_name' => $resolvedName,
            'field_name' => $fieldName,
            'related_field' => $relatedField,
            'relation' => $relation,
            'resource' => $resource,
        ];
    }

    /**
     * 返回关系对象在运行时结果中的输出字段名。
     *
     * `belongsTo` 默认尝试将 `category_id` 映射为 `category`，
     * 若与已有字段冲突，则回退到 `__relation_xxx` 私有运行时字段。
     *
     * @param string[] $reservedNames
     * @param string[] $usedNames
     */
    private function relationOutputName($field, array $reservedNames = [], array $usedNames = []): string
    {
        $relation = $this->relationFieldRelation($field);
        $kind = (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '');
        if (\in_array($kind, ['hasOne', 'hasMany'], true)) {
            return $this->relationFieldName($field);
        }

        $candidate = $this->relationAliasCandidate($field);

        if (!\in_array($candidate, array_merge($reservedNames, $usedNames), true)) {
            return $candidate;
        }

        $privateName = '__relation_'.$candidate;
        if (!\in_array($privateName, array_merge($reservedNames, $usedNames), true)) {
            return $privateName;
        }

        return '__relation_'.$this->relationFieldName($field);
    }

    /**
     * 返回关系声明可识别的请求名称集合。
     *
     * @return string[]
     */
    private function relationRequestNames($field, string $outputName): array
    {
        $names = [
            $outputName,
            $this->relationFieldName($field),
            $this->relationAliasCandidate($field),
        ];

        return array_values(array_unique(array_filter($names, static function ($name): bool {
            return \is_string($name) && '' !== trim($name);
        })));
    }

    /**
     * 推导关系对象默认别名。
     */
    private function relationAliasCandidate($field): string
    {
        $relation = $this->relationFieldRelation($field);
        foreach (['alias', 'as'] as $key) {
            $value = $relation[$key] ?? null;
            if (\is_string($value) && '' !== trim($value)) {
                return trim($value);
            }
        }

        $name = $this->relationFieldName($field);
        $kind = (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '');
        if ('belongsTo' === $kind && Str::endsWith($name, '_id')) {
            $alias = substr($name, 0, -3);
            if (\is_string($alias) && '' !== $alias) {
                return $alias;
            }
        }

        return $name;
    }

    /**
     * 判断原始资源字段是否为多值关系字段。
     *
     * @param mixed $field
     */
    private function isMultiValueRawRelationField($field): bool
    {
        $type = $this->relationFieldType($field);
        if ('checkbox' === $type) {
            return true;
        }

        return 'select' === $type
            && true === (bool) data_get($this->relationFieldMetadata($field), 'extends.multiple', false);
    }

    /**
     * 读取关系字段名，兼容 FieldDefinition 与原始资源字段对象。
     *
     * @param mixed $field
     */
    private function relationFieldName($field): string
    {
        if (method_exists($field, 'name')) {
            return (string) $field->name();
        }

        return (string) $field->getName();
    }

    /**
     * 读取关系字段类型，兼容 FieldDefinition 与原始资源字段对象。
     *
     * @param mixed $field
     */
    private function relationFieldType($field): string
    {
        if (method_exists($field, 'type')) {
            return (string) $field->type();
        }

        return (string) $field->getType();
    }

    /**
     * 读取关系字段元数据，兼容 FieldDefinition 与原始资源字段对象。
     *
     * @param mixed $field
     *
     * @return array<string, mixed>
     */
    private function relationFieldMetadata($field): array
    {
        if (method_exists($field, 'metadata')) {
            return (array) $field->metadata();
        }

        return (array) $field->getMetadata();
    }

    /**
     * 读取关系字段配置，兼容 FieldDefinition 与原始资源字段对象。
     *
     * @param mixed $field
     *
     * @return array<string, mixed>
     */
    private function relationFieldRelation($field): array
    {
        if (method_exists($field, 'relation')) {
            return (array) $field->relation();
        }

        return (array) $field->getRelation();
    }

    /**
     * 判断关联资源中是否存在可过滤字段。
     */
    private function relationResourceHasField($resource, string $field): bool
    {
        if (null !== $resource->getField($field)) {
            return true;
        }

        return \in_array($field, [$resource->getPrimaryKey(), 'created_at', 'updated_at', 'deleted_at'], true);
    }

    /**
     * 将单个过滤条件应用到查询构建器.
     */
    private function applyFilter($builder, QueryFilter $filter): void
    {
        $field = $filter->field();
        $value = $filter->value();

        switch ($filter->operator()) {
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                $builder->where($field, $filter->operator(), $value);

                return;

            case 'like':
                $builder->where($field, 'like', (string) $value);

                return;

            case 'in':
                if (\is_array($value)) {
                    $builder->whereIn($field, $value);
                }

                return;

            case 'between':
                if (\is_array($value) && \count($value) === 2) {
                    $builder->whereBetween($field, array_values($value));
                }

                return;

            case 'contains':
                $items = \is_array($value) ? array_values($value) : [$value];
                $items = array_values(array_filter($items, static function ($item): bool {
                    return \is_scalar($item) && '' !== (string) $item;
                }));
                if (0 === \count($items)) {
                    return;
                }

                $builder->where(function ($query) use ($field, $items): void {
                    foreach ($items as $index => $item) {
                        $method = 0 === $index ? 'where' : 'orWhere';
                        $query->{$method}($field, 'like', '%,'.(string) $item.',%');
                    }
                });

                return;

            case 'null':
                $builder->whereNull($field);

                return;

            case 'not_null':
                $builder->whereNotNull($field);

                return;
        }
    }

    /**
     * 将关系标签过滤命中的真实存储值应用到主查询。
     *
     * @param mixed    $builder
     * @param array<int, mixed> $values
     */
    private function applyRelationStoredValueFilter($builder, FieldDefinition $field, array $values, bool $negate): void
    {
        $column = $field->name();
        $values = array_values(array_unique(array_map(static function ($value) {
            return \is_scalar($value) ? (string) $value : $value;
        }, $values)));

        if ($this->isMultiValueRelationField($field)) {
            $builder->where(function ($query) use ($column, $values, $negate): void {
                foreach ($values as $index => $value) {
                    $method = $negate || 0 === $index ? 'where' : 'orWhere';
                    $operator = $negate ? 'not like' : 'like';
                    $query->{$method}($column, $operator, '%,'.(string) $value.',%');
                }
            });

            return;
        }

        if ($negate) {
            $builder->whereNotIn($column, $values);

            return;
        }

        $builder->whereIn($column, $values);
    }

    /**
     * 将当前操作对应的数据范围规则应用到查询构建器.
     *
     * @param mixed $builder
     */
    private function applyScope($builder, SchemaDefinition $definition, string $operation, ?ExecutionContext $context = null): void
    {
        if (null === $context) {
            return;
        }

        $scopeManager = $context->get('scope.manager');
        if (!$scopeManager instanceof ScopeManager) {
            return;
        }

        $scopeManager->apply($builder, $definition, $operation, $context);
    }

    /**
     * 应用关键字搜索.
     *
     * 关键字会在 schema 配置的 search_fields 中执行模糊匹配，
     * 如果未配置则回退到 title_field。
     *
     * @param mixed $builder
     */
    private function applyKeyword($builder, SchemaDefinition $definition, QueryCriteria $criteria): void
    {
        $keyword = $criteria->keyword();
        if (null === $keyword || '' === $keyword) {
            return;
        }

        $fields = $criteria->keywordFields();
        if (0 === \count($fields)) {
            $raw = $definition->raw();
            if (method_exists($raw, 'getSearchFields')) {
                $fields = array_values(array_filter((array) $raw->getSearchFields(), 'is_string'));
            }
            if (0 === \count($fields) && method_exists($raw, 'getTitleField')) {
                $titleField = $raw->getTitleField();
                if (\is_string($titleField) && '' !== $titleField) {
                    $fields = [$titleField];
                }
            }
        }

        $fields = array_values(array_filter($fields, function (string $field) use ($definition): bool {
            return null !== $this->capabilitiesFor($definition, $field);
        }));
        if (0 === \count($fields)) {
            return;
        }

        $builder->where(function ($query) use ($fields, $keyword): void {
            foreach ($fields as $index => $field) {
                $method = 0 === $index ? 'where' : 'orWhere';
                $query->{$method}($field, 'like', '%'.$keyword.'%');
            }
        });
    }

    /**
     * 当调用方未显式传入排序规则时，应用 schema 默认排序.
     *
     * @param mixed $builder
     */
    private function applyDefaultOrder($builder, SchemaDefinition $definition, QueryCriteria $criteria): void
    {
        if (0 !== \count($criteria->sorts())) {
            return;
        }

        $raw = $definition->raw();
        if (!method_exists($raw, 'getOrderFields')) {
            return;
        }

        foreach ((array) $raw->getOrderFields() as $field => $direction) {
            if ($this->applyRelationFieldSort($builder, $definition, (string) $field, (string) $direction)) {
                continue;
            }

            if ($this->applyRelationAppendSort($builder, $definition, (string) $field, (string) $direction)) {
                continue;
            }

            $capabilities = $this->capabilitiesFor($definition, (string) $field);
            if (null === $capabilities || false === (bool) ($capabilities['sortable'] ?? false)) {
                continue;
            }

            $builder->orderBy((string) $field, 'desc' === strtolower((string) $direction) ? 'desc' : 'asc');
        }
    }

    /**
     * 返回字段可用的查询能力配置.
     *
     * 对于 schema 中未声明、但属于主键的系统字段，这里提供
     * 一组内建能力，保证常见的 `id` 过滤和排序可直接使用。
     *
     * @return array<string, mixed>|null
     */
    private function capabilitiesFor(SchemaDefinition $definition, string $field): ?array
    {
        $definitionField = $definition->field($field);
        if (null !== $definitionField) {
            return $definitionField->capabilities();
        }

        if ($field === $definition->primaryKey()) {
            return [
                'operators' => ['=', '!=', '>', '>=', '<', '<=', 'in', 'between'],
                'filterable' => true,
                'sortable' => true,
            ];
        }

        return null;
    }

    /**
     * 返回 append 字段名所对应的真实关系字段定义。
     */
    private function resolveRelationAppendField(SchemaDefinition $definition, string $field): ?FieldDefinition
    {
        foreach ($definition->fields() as $definitionField) {
            $raw = $definitionField->raw();
            if (!$definitionField->isAppend()) {
                continue;
            }
            if ($raw->getAppendName() !== $field) {
                continue;
            }
            if (0 === \count($definitionField->relation())) {
                continue;
            }

            return $definitionField;
        }

        return null;
    }

    /**
     * 返回 append 字段名所对应的本地 options 字段定义。
     */
    private function resolveLocalAppendField(SchemaDefinition $definition, string $field): ?FieldDefinition
    {
        foreach ($definition->fields() as $definitionField) {
            $raw = $definitionField->raw();
            if (!$definitionField->isAppend()) {
                continue;
            }
            if ($raw->getAppendName() !== $field) {
                continue;
            }
            if (0 !== \count($definitionField->relation())) {
                continue;
            }
            if (!$this->supportsLocalOptionAppend($definitionField)) {
                continue;
            }

            return $definitionField;
        }

        return null;
    }

    /**
     * 将关系展示字段排序翻译为关联资源标签子查询排序。
     *
     * @param mixed $builder
     */
    private function applyRelationAppendSort($builder, SchemaDefinition $definition, string $field, string $direction): bool
    {
        $definitionField = $this->resolveRelationAppendField($definition, $field);
        if (null === $definitionField || $this->isMultiValueRelationField($definitionField)) {
            return false;
        }

        $subQuery = $this->buildRelationSortSubQuery($definition, $definitionField);
        if (null === $subQuery) {
            return false;
        }

        $builder->orderBy($subQuery, 'desc' === strtolower($direction) ? 'desc' : 'asc');

        return true;
    }

    /**
     * 将本地 options 展示字段排序翻译为 CASE 排序表达式。
     *
     * @param mixed $builder
     */
    private function applyLocalAppendSort($builder, SchemaDefinition $definition, string $field, string $direction): bool
    {
        $definitionField = $this->resolveLocalAppendField($definition, $field);
        if (null === $definitionField || $this->isMultiValueOptionField($definitionField)) {
            return false;
        }

        $options = $this->normalizedOptionPairs($definitionField);
        if (0 === \count($options)) {
            return false;
        }

        $wrappedColumn = DB::connection()->getQueryGrammar()->wrap($definitionField->name());
        $sql = 'case';
        $bindings = [];
        foreach ($options as $option) {
            $sql .= " when {$wrappedColumn} = ? then ?";
            $bindings[] = $option['value'];
            $bindings[] = $option['label'];
        }
        $sql .= ' else null end '.('desc' === strtolower($direction) ? 'desc' : 'asc');

        $builder->orderByRaw($sql, $bindings);

        return true;
    }

    /**
     * 将单值关系字段排序翻译为关联资源子查询排序。
     *
     * 当前支持：
     * 1. `belongsTo`
     * 2. `hasOne`
     *
     * `hasMany` 的排序语义不稳定，暂不隐式支持。
     *
     * @param mixed $builder
     */
    private function applyRelationFieldSort($builder, SchemaDefinition $definition, string $field, string $direction): bool
    {
        $resolved = $this->resolveRelationFieldPath($definition, $field);
        if (null === $resolved) {
            return false;
        }

        $kind = (string) (($resolved['relation'] ?? [])['kind'] ?? ($resolved['relation'] ?? [])['relation_kind'] ?? '');
        if (!\in_array($kind, ['belongsTo', 'hasOne'], true)) {
            return false;
        }

        $subQuery = $this->buildRelationFieldSortSubQuery($definition, $resolved);
        if (null === $subQuery) {
            return false;
        }

        $builder->orderBy($subQuery, 'desc' === strtolower($direction) ? 'desc' : 'asc');

        return true;
    }

    /**
     * 根据展示标签过滤条件，解析出关联字段真实存储值集合。
     *
     * @return array<int, mixed>
     */
    private function resolveRelationFilterValues(SchemaDefinition $definition, FieldDefinition $field, QueryFilter $filter): array
    {
        $relation = $field->relation();
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $valueColumn = (string) ($relation['value'] ?? 'id');
        $labelColumn = (string) ($relation['label'] ?? 'title');
        if ('' === $table || '' === $valueColumn || '' === $labelColumn) {
            return [];
        }

        $resource = $table === $definition->name()
            ? $definition->raw()
            : Easy::schema($table)->raw();

        $query = DB::table($resource->getRawTable())->select($valueColumn);
        if ($resource->allowRecycle()) {
            $query->whereNull('deleted_at');
        }

        $this->applyRelationResourceFilters($query, (array) ($relation['filter'] ?? []));
        $this->applyRelationLabelConstraint($query, $labelColumn, $filter);

        return $query->pluck($valueColumn)->all();
    }

    /**
     * 构建基于关联标签的排序子查询。
     *
     * @return \Illuminate\Database\Query\Builder|null
     */
    private function buildRelationSortSubQuery(SchemaDefinition $definition, FieldDefinition $field): ?\Illuminate\Database\Query\Builder
    {
        $relation = $field->relation();
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $valueColumn = (string) ($relation['value'] ?? 'id');
        $labelColumn = (string) ($relation['label'] ?? 'title');
        if ('' === $table || '' === $valueColumn || '' === $labelColumn) {
            return null;
        }

        $resource = $table === $definition->name()
            ? $definition->raw()
            : Easy::schema($table)->raw();
        $alias = 'easy_rel_sort_'.substr(md5($field->name().$table.$labelColumn), 0, 8);
        $query = DB::table($resource->getRawTable().' as '.$alias)
            ->select($alias.'.'.$labelColumn)
            ->whereColumn($alias.'.'.$valueColumn, $definition->name().'.'.$field->name())
            ->limit(1);

        if ($resource->allowRecycle()) {
            $query->whereNull($alias.'.deleted_at');
        }

        $this->applyRelationResourceFilters($query, (array) ($relation['filter'] ?? []), $alias);

        return $query;
    }

    /**
     * 根据本地选项标签过滤条件，解析出真实存储值集合。
     *
     * @return array<int, mixed>
     */
    private function resolveLocalOptionFilterValues(FieldDefinition $field, QueryFilter $filter): array
    {
        $matched = [];

        foreach ($this->normalizedOptionPairs($field) as $option) {
            if ($this->matchesLocalOptionLabel($option['label'], $filter->operator(), $filter->value())) {
                $matched[] = $option['value'];
            }
        }

        return array_values(array_unique($matched, SORT_REGULAR));
    }

    /**
     * 构建跨关系字段排序子查询。
     *
     * @param array<string, mixed> $resolved
     *
     * @return \Illuminate\Database\Query\Builder|null
     */
    private function buildRelationFieldSortSubQuery(SchemaDefinition $definition, array $resolved): ?\Illuminate\Database\Query\Builder
    {
        $relation = (array) ($resolved['relation'] ?? []);
        $resource = $resolved['resource'] ?? null;
        $relatedField = (string) ($resolved['related_field'] ?? '');
        $fieldName = (string) ($resolved['field_name'] ?? '');
        $kind = (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '');
        if ('' === $relatedField || '' === $fieldName || !\is_object($resource)) {
            return null;
        }

        $alias = 'easy_rel_sort_'.substr(md5($fieldName.$relatedField.$resource->getRawTable()), 0, 8);
        $query = DB::table($resource->getRawTable().' as '.$alias)
            ->select($this->qualifyRelationColumn($relatedField, $alias))
            ->limit(1)
        ;

        if ('belongsTo' === $kind) {
            $valueColumn = (string) ($relation['value'] ?? 'id');
            if ('' === $valueColumn) {
                return null;
            }

            $query->whereColumn(
                $this->qualifyRelationColumn($valueColumn, $alias),
                $definition->name().'.'.$fieldName
            );
        } elseif ('hasOne' === $kind) {
            $foreignKey = (string) ($relation['foreign_key'] ?? '');
            $localKey = (string) ($relation['local_key'] ?? $definition->primaryKey());
            if ('' === $foreignKey || '' === $localKey) {
                return null;
            }

            $query->whereColumn(
                $this->qualifyRelationColumn($foreignKey, $alias),
                $definition->name().'.'.$localKey
            );
        } else {
            return null;
        }

        if ($resource->allowRecycle()) {
            $query->whereNull($this->qualifyRelationColumn('deleted_at', $alias));
        }

        $this->applyRelationResourceFilters($query, (array) ($relation['filter'] ?? []), $alias);

        return $query;
    }

    /**
     * 将标签过滤条件应用到关联资源查询。
     *
     * @param mixed $query
     */
    private function applyRelationLabelConstraint($query, string $labelColumn, QueryFilter $filter): void
    {
        $value = $filter->value();

        switch ($filter->operator()) {
            case '=':
            case '!=':
                $query->where($labelColumn, '=', $value);

                return;

            case 'like':
                $query->where($labelColumn, 'like', (string) $value);

                return;

            case 'in':
                if (\is_array($value)) {
                    $query->whereIn($labelColumn, $value);
                }

                return;
        }
    }

    /**
     * 应用关联资源自己的固定过滤条件。
     *
     * @param mixed $query
     * @param array<int|string, mixed> $filters
     */
    private function applyRelationResourceFilters($query, array $filters, ?string $tableAlias = null): void
    {
        foreach ($filters as $key => $filter) {
            if (\is_string($key) && !\is_array($filter)) {
                $query->where($this->qualifyRelationColumn($key, $tableAlias), $filter);

                continue;
            }

            if (!\is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? $filter['name'] ?? null;
            if (!\is_string($field) || '' === $field) {
                continue;
            }
            $qualifiedField = $this->qualifyRelationColumn($field, $tableAlias);

            $operator = strtolower((string) ($filter['operator'] ?? '='));
            $value = $filter['value'] ?? null;
            if ('in' === $operator && \is_array($value)) {
                $query->whereIn($qualifiedField, $value);

                continue;
            }

            $query->where($qualifiedField, $operator, $value);
        }
    }

    /**
     * 为关联资源列名按需补充表别名。
     */
    private function qualifyRelationColumn(string $field, ?string $tableAlias = null): string
    {
        if (null === $tableAlias || Str::contains($field, '.')) {
            return $field;
        }

        return $tableAlias.'.'.$field;
    }

    /**
     * 判断关系字段是否按多值逗号格式存储。
     */
    private function isMultiValueRelationField(FieldDefinition $field): bool
    {
        if ('checkbox' === $field->type()) {
            return true;
        }

        return 'select' === $field->type()
            && true === (bool) data_get($field->metadata(), 'extends.multiple', false);
    }

    /**
     * 判断字段是否为多值 options 字段。
     */
    private function isMultiValueOptionField(FieldDefinition $field): bool
    {
        return $this->isMultiValueRelationField($field);
    }

    /**
     * 判断字段是否支持本地 options 展示值映射。
     */
    private function supportsLocalOptionAppend(FieldDefinition $field): bool
    {
        if (!\in_array($field->type(), ['radio', 'select', 'switch'], true)) {
            return false;
        }

        return 0 !== \count($this->normalizedOptionPairs($field));
    }

    /**
     * 返回字段可用的选项对集合。
     *
     * @return array<int, array{label: string, value: mixed}>
     */
    private function normalizedOptionPairs(FieldDefinition $field): array
    {
        return array_values(array_filter(array_map(static function (array $option): ?array {
            if (!array_key_exists('label', $option) || !array_key_exists('value', $option)) {
                return null;
            }

            return [
                'label' => (string) $option['label'],
                'value' => $option['value'],
            ];
        }, $field->options())));
    }

    /**
     * 判断本地 options 标签是否命中过滤条件。
     *
     * @param mixed $value
     */
    private function matchesLocalOptionLabel(string $label, string $operator, $value): bool
    {
        switch ($operator) {
            case '=':
            case '!=':
                return $label === (string) $value;

            case 'like':
                return $this->matchesSqlLike($label, (string) $value);

            case 'in':
                if (!\is_array($value)) {
                    return false;
                }

                return \in_array($label, array_map('strval', $value), true);
        }

        return false;
    }

    /**
     * 使用 SQL LIKE 语义在 PHP 侧匹配本地选项标签。
     */
    private function matchesSqlLike(string $subject, string $pattern): bool
    {
        $regex = preg_quote($pattern, '/');
        $regex = str_replace(['%', '_'], ['.*', '.'], $regex);

        return 1 === preg_match('/^'.$regex.'$/u', $subject);
    }

    /**
     * @return string[]
     */
    private function resolveGroupFields(SchemaDefinition $definition, QueryCriteria $criteria): array
    {
        $groups = [];
        foreach ($criteria->groups() as $field) {
            if (null === $this->capabilitiesFor($definition, $field)) {
                continue;
            }
            $groups[] = $field;
        }

        return array_values(array_unique($groups));
    }

    /**
     * @return QueryAggregate[]
     */
    private function resolveAggregates(SchemaDefinition $definition, QueryCriteria $criteria): array
    {
        $aggregates = [];
        foreach ($criteria->aggregates() as $aggregate) {
            if (!\in_array($aggregate->type(), self::AGGREGATE_TYPES, true)) {
                continue;
            }

            $field = $aggregate->field();
            if ('count' !== $aggregate->type()) {
                if (null === $field || null === $this->capabilitiesFor($definition, $field)) {
                    continue;
                }
            } elseif (null !== $field && '*' !== $field && null === $this->capabilitiesFor($definition, $field)) {
                continue;
            }

            $aggregates[] = $aggregate;
        }

        if (0 !== \count($aggregates)) {
            return $aggregates;
        }

        return [new QueryAggregate('count', null, 'total')];
    }

    /**
     * 生成聚合表达式 SQL.
     *
     * @param mixed $builder
     */
    private function aggregateExpression($builder, SchemaDefinition $definition, QueryAggregate $aggregate, string $alias): string
    {
        $field = $aggregate->field();
        if (null === $field || '*' === $field) {
            $operand = '*';
        } else {
            $operand = $this->wrapIdentifier($builder, $field);
        }

        return strtoupper($aggregate->type()).'('.$operand.') as '.$this->wrapIdentifier($builder, $alias);
    }

    /**
     * 返回聚合别名.
     */
    private function aliasForAggregate(QueryAggregate $aggregate): string
    {
        $alias = $aggregate->alias();
        if (\is_string($alias) && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            return $alias;
        }

        $field = $aggregate->field();
        if (null === $field || '*' === $field) {
            return $aggregate->type().'_all';
        }

        return $aggregate->type().'_'.$field;
    }

    /**
     * 对 SQL 标识符做 grammar 级包装.
     *
     * @param mixed $builder
     */
    private function wrapIdentifier($builder, string $identifier): string
    {
        return $builder->getQuery()->getGrammar()->wrap($identifier);
    }

    /**
     * 标准化单行聚合结果.
     *
     * 聚合查询直接返回基础查询结果，避免混入模型 append 字段。
     * 同时把 count/sum 等返回的数字字符串还原为数值类型。
     *
     * @param array<string, mixed> $row
     * @param string[]             $aggregateAliases
     *
     * @return array<string, mixed>
     */
    private function normalizeAggregateRow(array $row, array $aggregateAliases): array
    {
        foreach ($aggregateAliases as $alias) {
            if (!isset($row[$alias]) || !\is_string($row[$alias]) || !is_numeric($row[$alias])) {
                continue;
            }

            $row[$alias] = false !== strpos($row[$alias], '.') ? (float) $row[$alias] : (int) $row[$alias];
        }

        return $row;
    }
}
