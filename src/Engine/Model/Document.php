<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Engine\Model;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Engine\Model\Traits\AttributesTrait;
use PTAdmin\Easy\Engine\Model\Traits\BaseTrait;
use PTAdmin\Easy\Engine\Model\Traits\CreateTrait;
use PTAdmin\Easy\Engine\Resource\ResourceField;
use PTAdmin\Easy\Exceptions\EasyException;

/**
 * @method self where($column, $operator = null, $value = null, $boolean = 'and')
 * @method self orWhere($column, $operator = null, $value = null)
 * @method self whereNot($column, $operator = null, $value = null, $boolean = 'and')
 * @method self orWhereNot($column, $operator = null, $value = null)
 * @method self whereColumn($first, $operator = null, $second = null, $boolean = 'and')
 * @method self orWhereColumn($first, $operator = null, $second = null)
 * @method self whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method self orWhereRaw($sql, $bindings = [])
 * @method self whereIn($column, $values, $boolean = 'and', $not = false)
 * @method self orWhereIn($column, $values)
 * @method self whereNotIn($column, $values, $boolean = 'and')
 * @method self orWhereNotIn($column, $values)
 * @method self whereNull($columns, $boolean = 'and', $not = false)
 * @method self orWhereNull($column)
 * @method self whereNotNull($columns, $boolean = 'and')
 * @method self whereBetween($column, iterable $values, $boolean = 'and', $not = false)
 * @method self whereBetweenColumns($column, array $values, $boolean = 'and', $not = false)
 * @method self orWhereBetween($column, iterable $values)
 * @method self orWhereBetweenColumns($column, array $values)
 * @method self whereNotBetween($column, iterable $values, $boolean = 'and')
 * @method self whereNotBetweenColumns($column, array $values, $boolean = 'and')
 * @method self orWhereNotBetween($column, iterable $values)
 * @method self orWhereNotNull($column)
 * @method self whereDate($column, $operator, $value = null, $boolean = 'and')
 * @method self orWhereDate($column, $operator, $value = null)
 * @method self whereTime($column, $operator, $value = null, $boolean = 'and')
 * @method self orWhereTime($column, $operator, $value = null)
 * @method self mergeWheres($wheres, $bindings)
 * @method self groupBy(...$groups)
 * @method self groupByRaw($sql, array $bindings = [])
 * @method self having($column, $operator = null, $value = null, $boolean = 'and')
 * @method self orHaving($column, $operator = null, $value = null)
 * @method self orderBy($column, $direction = 'asc')
 * @method self orderByDesc($column)
 * @method self select($columns = ['*'])
 * @method self selectRaw($expression, array $bindings = [])
 * @method self join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method self joinWhere($table, $first, $operator, $second, $type = 'inner')
 * @method self joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method self leftJoin($table, $first, $operator = null, $second = null)
 * @method self leftJoinWhere($table, $first, $operator, $second)
 * @method self leftJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method self rightJoin($table, $first, $operator = null, $second = null)
 * @method self rightJoinWhere($table, $first, $operator, $second)
 * @method self rightJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method self crossJoin($table, $first = null, $operator = null, $second = null)
 */
class Document
{
    use AttributesTrait;
    use BaseTrait;
    use CreateTrait;

    /** @var IResource 当前文档绑定的资源定义 */
    protected $resource;
    /** @var mixed 文档自定义控制器 */
    protected $control;
    /** @var null|array<int, ResourceRecord>|EasyModel|EasyModel[]|ResourceRecord */
    protected $model;

    /** @var null|ResourceQueryBuilder */
    protected $query;

    /** @var ExecutionContext|null */
    protected $context;

    public function __construct(IResource $resource)
    {
        $this->resource = $resource;
    }

    public function __call($name, $arguments)
    {
        if (!method_exists($this, $name)) {
            $result = \call_user_func_array([$this->query(), $name], $arguments);
            if ($result instanceof ResourceQueryBuilder) {
                $this->query = $result;

                return $this;
            }

            return $result;
        }

        return $this;
    }

    /**
     * 返回当前文档绑定的资源定义对象.
     */
    public function resource(): IResource
    {
        return $this->resource;
    }

    public function useContext(?ExecutionContext $context = null): self
    {
        $this->context = $context;

        return $this;
    }

    public function currentContext(): ?ExecutionContext
    {
        return $this->context;
    }

    /**
     * 获取模型对象.
     *
     * @return EasyModel
     */
    public function model(): EasyModel
    {
        if (null === $this->model) {
            $this->model = $this->newModel();
        }

        return $this->model;
    }

    /**
     * 获取查询对象.
     *
     * 运行时默认返回轻量 Query Builder 包装器.
     */
    public function query(): ResourceQueryBuilder
    {
        if (null === $this->query) {
            $this->newQuery();
        }

        return $this->query;
    }

    /**
     * 创建模型对象.
     *
     * @return EasyModel
     */
    public function newModel(): EasyModel
    {
        if ($this->resource->allowRecycle()) {
            return EasyDeleteModel::make($this->resource, $this);
        }

        return EasyModel::make($this->resource, $this);
    }

    /**
     * 创建查询对象.
     */
    public function newQuery(): self
    {
        $this->query = new ResourceQueryBuilder($this, $this->newBaseQuery());

        return $this;
    }

    /**
     * 构造底层 Query Builder.
     */
    public function newBaseQuery(): \Illuminate\Database\Query\Builder
    {
        $builder = DB::table($this->resource->getRawTable());
        if ($this->resource->allowRecycle()) {
            $builder->whereNull('deleted_at');
        }

        return $builder;
    }

    /**
     * 根据数据库原始行创建资源记录对象.
     *
     * @param array<string, mixed> $attributes
     */
    public function newRecordFromDatabase(array $attributes): ?ResourceRecord
    {
        if (0 === \count($attributes)) {
            return null;
        }

        return new ResourceRecord($attributes, $this);
    }

    /**
     * 创建一条仅用于运行时转换的轻量记录对象.
     *
     * @param array<string, mixed> $attributes
     */
    public function newRecord(array $attributes = []): ResourceRecord
    {
        return new ResourceRecord($attributes, $this);
    }

    /**
     * 为一批记录预加载 append 数据，减少列表读取时的重复查询。
     *
     * @param iterable<int, ResourceRecord> $records
     */
    public function preloadAppends(iterable $records): void
    {
        $buffer = [];
        foreach ($records as $record) {
            if ($record instanceof ResourceRecord) {
                $buffer[] = $record;
            }
        }
        if (0 === \count($buffer)) {
            return;
        }

        foreach ($this->resource->getAppends() as $appendName => $fieldName) {
            $field = $this->resource->getField($fieldName);
            if (null === $field || !method_exists($field, 'preloadAppendValues')) {
                continue;
            }

            $field->preloadAppendValues($buffer);
        }
    }

    /**
     * 为一批记录预加载 `hasMany` 关系数据。
     *
     * 第一阶段仅处理 schema 中显式声明为 `hasMany` 的关系字段，
     * 并将结果挂回记录运行时字段，便于 detail/list 直接读取。
     *
     * @param iterable<int, ResourceRecord> $records
     */
    public function preloadHasManyRelations(iterable $records, array $relations = []): void
    {
        $buffer = [];
        foreach ($records as $record) {
            if ($record instanceof ResourceRecord) {
                $buffer[] = $record;
            }
        }
        if (0 === \count($buffer)) {
            return;
        }

        foreach ($relations as $outputName => $options) {
            $fieldName = $this->resolveRuntimeRelationFieldName($outputName, $options);
            $relation = (array) ($this->resource->getRelations()[$fieldName] ?? []);
            if ('hasMany' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
                continue;
            }

            $this->preloadHasManyRelation($buffer, (string) $fieldName, (string) $outputName, $relation, \is_array($options) ? $options : []);
        }
    }

    /**
     * 为一批记录预加载 `belongsTo` 关系对象。
     *
     * 默认仅在调用方显式声明 `with(...)` / `with_relations` 时加载，
     * 避免列表与详情默认返回过多关联数据。
     *
     * @param iterable<int, ResourceRecord> $records
     */
    public function preloadBelongsToRelations(iterable $records, array $relations = []): void
    {
        $buffer = [];
        foreach ($records as $record) {
            if ($record instanceof ResourceRecord) {
                $buffer[] = $record;
            }
        }
        if (0 === \count($buffer)) {
            return;
        }

        foreach ($relations as $outputName => $options) {
            $fieldName = $this->resolveRuntimeRelationFieldName($outputName, $options);
            $relation = (array) ($this->resource->getRelations()[$fieldName] ?? []);
            if ('belongsTo' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
                continue;
            }

            $this->preloadBelongsToRelation($buffer, (string) $fieldName, (string) $outputName, $relation, \is_array($options) ? $options : []);
        }
    }

    /**
     * 为一批记录预加载 `hasOne` 关系对象。
     *
     * `hasOne` 与 `hasMany` 共用外键关联语义，但结果始终压缩为单条对象。
     *
     * @param iterable<int, ResourceRecord> $records
     */
    public function preloadHasOneRelations(iterable $records, array $relations = []): void
    {
        $buffer = [];
        foreach ($records as $record) {
            if ($record instanceof ResourceRecord) {
                $buffer[] = $record;
            }
        }
        if (0 === \count($buffer)) {
            return;
        }

        foreach ($relations as $outputName => $options) {
            $fieldName = $this->resolveRuntimeRelationFieldName($outputName, $options);
            $relation = (array) ($this->resource->getRelations()[$fieldName] ?? []);
            if ('hasOne' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
                continue;
            }

            $this->preloadHasOneRelation($buffer, (string) $fieldName, (string) $outputName, $relation, \is_array($options) ? $options : []);
        }
    }

    /**
     * 根据字段定义转换待写入数据.
     *
     * @param array<string, mixed> $data
     * @param null|ResourceRecord  $record
     *
     * @return array<string, mixed>
     */
    public function normalizeWriteData(array $data, ?ResourceRecord $record = null): array
    {
        $record = $record ?? $this->newRecord();
        $normalized = [];
        foreach ($data as $key => $value) {
            $field = $this->resource->getField((string) $key);
            if (null === $field) {
                if (\in_array((string) $key, [$this->resource->getPrimaryKey(), 'created_at', 'updated_at', 'deleted_at'], true)) {
                    $normalized[$key] = $this->normalizeSystemValue((string) $key, $value);
                }

                continue;
            }

            if ($field->isVirtual() || $field->getComponent()->isVirtual()) {
                continue;
            }
            $normalized[$key] = $field->setComponentAttributeValue($record, $value);
        }

        return $normalized;
    }

    /**
     * 读取指定主键对应的记录.
     *
     * @param mixed         $id
     * @param null|\Closure $scope
     */
    public function findRecord($id, ?\Closure $scope = null): ?ResourceRecord
    {
        $query = $this->query();
        if (null !== $scope) {
            $scope($query);
        }

        return $query->where($this->resource->getPrimaryKey(), $id)->first();
    }

    /**
     * 在数据库中插入一条记录并返回最新记录.
     *
     * @param array<string, mixed> $data
     */
    public function insertRecord(array $data): ?ResourceRecord
    {
        $attributes = $this->normalizeWriteData($data);
        $timestamp = time();
        $attributes['created_at'] = $attributes['created_at'] ?? $timestamp;
        $attributes['updated_at'] = $attributes['updated_at'] ?? $timestamp;

        $id = $this->newBaseQuery()->insertGetId($attributes);
        ResourceField::flushRelationLabelCache();

        return $this->findRecord($id);
    }

    /**
     * 更新单条记录并返回最新数据.
     *
     * @param ResourceRecord       $record
     * @param array<string, mixed> $data
     * @param null|\Closure        $scope
     */
    public function updateRecord(ResourceRecord $record, array $data, ?\Closure $scope = null): ?ResourceRecord
    {
        $attributes = $this->normalizeWriteData($data, $record);
        $attributes['updated_at'] = time();

        $query = $this->newBaseQuery()->where($this->resource->getPrimaryKey(), $record->getKey());
        if (null !== $scope) {
            $scope(new ResourceQueryBuilder($this, $query));
        }

        $updated = $query->update($attributes);
        if ($updated <= 0) {
            if (!$this->hasPendingRelationSync($data)) {
                return null;
            }

            $current = $this->findRecord($record->getKey());
            if (null === $current) {
                return null;
            }

            $this->syncManagedRelations($current, $data);

            return $current;
        }
        ResourceField::flushRelationLabelCache();

        $current = $this->findRecord($record->getKey());
        if (null === $current) {
            return null;
        }

        $this->syncManagedRelations($current, $data);

        return $current;
    }

    /**
     * 删除单条记录.
     *
     * @param ResourceRecord $record
     * @param null|\Closure  $scope
     */
    public function deleteRecord(ResourceRecord $record, ?\Closure $scope = null): bool
    {
        if (!$this->deleteManagedRelations($record)) {
            return false;
        }

        $query = $this->newBaseQuery()->where($this->resource->getPrimaryKey(), $record->getKey());
        if (null !== $scope) {
            $scope(new ResourceQueryBuilder($this, $query));
        }

        if ($this->resource->allowRecycle()) {
            $deleted = $query->update([
                'deleted_at' => time(),
                'updated_at' => time(),
            ]) > 0;
            if ($deleted) {
                ResourceField::flushRelationLabelCache();
            }

            return $deleted;
        }

        $deleted = $query->delete() > 0;
        if ($deleted) {
            ResourceField::flushRelationLabelCache();
        }

        return $deleted;
    }

    /**
     * 删除当前主记录下声明为 `hasOne/hasMany` 的子记录。
     *
     * 当前阶段采用保守规则：
     * 1. `belongsTo` 不处理
     * 2. `hasOne/hasMany` 默认级联删除或回收
     * 3. 子记录删除通过各自 Document 的 `deleteRecord()` 执行，
     *    因此会自动复用子资源自己的回收站策略
     */
    protected function deleteManagedRelations(ResourceRecord $record): bool
    {
        foreach ((array) $this->resource->getRelations() as $fieldName => $relation) {
            $kind = (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '');
            if (!\in_array($kind, ['hasOne', 'hasMany'], true)) {
                continue;
            }

            if (!$this->deleteManagedRelationChildren($record, (string) $fieldName, (array) $relation)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 删除单个关系字段对应的子记录集合。
     *
     * @param array<string, mixed> $relation
     */
    protected function deleteManagedRelationChildren(ResourceRecord $record, string $fieldName, array $relation): bool
    {
        $context = $this->resolveHasManyRelationContext($record, $fieldName, $relation);
        $childDocument = $context['document'];
        $foreignKey = $context['foreign_key'];
        $localValue = $context['local_value'];
        $policy = $this->relationDeletePolicy($relation);
        $children = $childDocument->query()->where($foreignKey, $localValue)->get()->all();

        if (0 === \count($children)) {
            return true;
        }

        if ('restrict' === $policy) {
            throw new EasyException(__('ptadmin-easy::messages.errors.relation_restrict', [
                'field' => $fieldName,
            ]));
        }

        if ('set_null' === $policy) {
            return $this->nullifyManagedRelationChildren($context, $fieldName);
        }

        foreach ($children as $childRecord) {
            if (!$childDocument->deleteRecord($childRecord)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 将关系子记录的外键置空。
     *
     * @param array<string, mixed> $context
     */
    protected function nullifyManagedRelationChildren(array $context, string $fieldName): bool
    {
        $childDocument = $context['document'];
        $foreignKey = $context['foreign_key'];
        $localValue = $context['local_value'];
        $field = $childDocument->resource()->getField($foreignKey);
        if (null === $field) {
            throw new EasyException(__('ptadmin-easy::messages.errors.child_missing_foreign_key', [
                'field' => $fieldName,
                'foreign_key' => $foreignKey,
            ]));
        }
        if (null !== $field->required()) {
            throw new EasyException(__('ptadmin-easy::messages.errors.set_null_requires_nullable', [
                'field' => $fieldName,
                'foreign_key' => $foreignKey,
            ]));
        }

        $payload = [
            $foreignKey => null,
            'updated_at' => time(),
        ];

        $updated = $childDocument->newBaseQuery()
            ->where($foreignKey, $localValue)
            ->update($payload)
        ;

        if ($updated > 0) {
            ResourceField::flushRelationLabelCache();
        }

        return true;
    }

    /**
     * 返回关系删除策略，默认 `cascade`。
     *
     * @param array<string, mixed> $relation
     */
    protected function relationDeletePolicy(array $relation): string
    {
        $policy = strtolower((string) ($relation['on_delete'] ?? 'cascade'));

        return \in_array($policy, ['cascade', 'restrict', 'set_null'], true) ? $policy : 'cascade';
    }

    /**
     * 设置自定义控制器.
     *
     * @param $control
     *
     * @return $this
     */
    public function setControl($control): self
    {
        $this->control = $control;

        return $this;
    }

    public function getControl()
    {
        if (null === $this->control) {
            $this->control = $this->resource->getControl();
        }
        if (\is_string($this->control) && '' !== $this->control) {
            $this->control = app($this->control, ['resource' => $this->resource, 'document' => $this]);
        }

        return $this->control;
    }

    public function trigger($event, $params = [])
    {
        $control = $this->getControl();
        if (null === $control) {
            return null;
        }
        if (method_exists($control, $event)) {
            return \call_user_func_array([$control, $event], $params);
        }

        return null;
    }

    /**
     * 同步当前主记录下声明为 `hasMany` 的子表数据。
     *
     * 规则：
     * 1. payload 中未出现关系字段时，不处理该关系；
     * 2. payload 中关系字段为 `[]/null` 时，清空当前主记录下已有子记录；
     * 3. payload 中子项带主键时，按主键更新；
     * 4. payload 中子项不带主键时，视为新增；
     * 5. 当前主记录下数据库里存在但 payload 中未出现的子记录，会被删除或回收。
     *
     * @param array<string, mixed> $data
     */
    public function syncHasManyRelations(ResourceRecord $record, array $data): void
    {
        foreach ((array) $this->resource->getRelations() as $fieldName => $relation) {
            $fieldName = (string) $fieldName;
            if (!\array_key_exists($fieldName, $data)) {
                continue;
            }

            if ('hasMany' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
                continue;
            }

            $this->syncHasManyRelation($record, $fieldName, (array) $relation, $data[$fieldName]);
        }
    }

    /**
     * 同步当前主记录下受运行时管理的关系数据。
     *
     * 当前包含：
     * 1. `hasOne`
     * 2. `hasMany`
     *
     * @param array<string, mixed> $data
     */
    public function syncManagedRelations(ResourceRecord $record, array $data): void
    {
        $this->syncHasOneRelations($record, $data);
        $this->syncHasManyRelations($record, $data);
    }

    /**
     * 同步当前主记录下声明为 `hasOne` 的子表数据。
     *
     * 规则：
     * 1. payload 中未出现关系字段时，不处理该关系；
     * 2. payload 中关系字段为 `null/[]` 时，清空当前主记录下已有子记录；
     * 3. payload 中带主键时，按主键更新；
     * 4. payload 中不带主键时，视为替换写入；
     * 5. 当前主记录下已有但未被保留的旧记录，会被删除或回收。
     *
     * @param array<string, mixed> $data
     */
    public function syncHasOneRelations(ResourceRecord $record, array $data): void
    {
        foreach ((array) $this->resource->getRelations() as $fieldName => $relation) {
            $fieldName = (string) $fieldName;
            if (!\array_key_exists($fieldName, $data)) {
                continue;
            }

            if ('hasOne' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
                continue;
            }

            $this->syncHasOneRelation($record, $fieldName, (array) $relation, $data[$fieldName]);
        }
    }

    /**
     * 兼容旧版 document 写入格式，统一规范系统字段值。
     *
     * @param mixed $value
     *
     * @return mixed
     */
    protected function normalizeSystemValue(string $key, $value)
    {
        if (!\in_array($key, ['created_at', 'updated_at', 'deleted_at'], true)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            $trimmed = trim($value);
            if ('' === $trimmed) {
                return null;
            }

            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }

            $timestamp = strtotime($trimmed);
            if (false !== $timestamp) {
                return $timestamp;
            }
        }

        return $value;
    }

    /**
     * 批量加载单个 `hasMany` 关系字段。
     *
     * @param array<int, ResourceRecord> $records
     * @param array<string, mixed>       $relation
     */
    protected function preloadHasManyRelation(array $records, string $fieldName, string $outputName, array $relation, array $options = []): void
    {
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $foreignKey = (string) ($relation['foreign_key'] ?? '');
        $localKey = (string) ($relation['local_key'] ?? $this->resource->getPrimaryKey());
        if ('' === $table || '' === $foreignKey || '' === $localKey) {
            return;
        }

        $parentKeys = [];
        foreach ($records as $record) {
            $key = $record->{$localKey};
            if (null !== $key && '' !== (string) $key) {
                $parentKeys[] = $key;
            }
        }
        $parentKeys = array_values(array_unique($parentKeys, SORT_REGULAR));
        if (0 === \count($parentKeys)) {
            foreach ($records as $record) {
                $record->setRuntimeValue($outputName, []);
            }

            return;
        }

        $childResource = $table === $this->resource->getRawTable()
            ? $this->resource
            : Easy::schema($table)->raw();
        $childDocument = $childResource->document()->useContext($this->context);
        $requestedColumns = array_values(array_filter((array) ($options['columns'] ?? []), 'is_string'));
        $requestedOutputColumns = $requestedColumns;
        $stripColumns = [];
        if (0 !== \count($requestedColumns)) {
            if (!\in_array($foreignKey, $requestedColumns, true)) {
                $requestedColumns[] = $foreignKey;
                $stripColumns[] = $foreignKey;
            }

            $children = $childDocument->query()->whereIn($foreignKey, $parentKeys)->get($requestedColumns);
        } else {
            $children = $childDocument->query()->whereIn($foreignKey, $parentKeys)->get();
        }
        $grouped = [];
        foreach ($children as $child) {
            if (0 !== \count($requestedOutputColumns)) {
                $payload = [];
                foreach ($requestedOutputColumns as $column) {
                    $payload[$column] = $child->{$column};
                }
            } else {
                $payload = $child->toArray();
                foreach ($stripColumns as $stripColumn) {
                    unset($payload[$stripColumn]);
                }
            }

            $grouped[(string) $child->{$foreignKey}][] = $payload;
        }

        foreach ($records as $record) {
            $record->setRuntimeValue($outputName, $grouped[(string) $record->{$localKey}] ?? []);
        }
    }

    /**
     * 批量加载单个 `hasOne` 关系字段。
     *
     * @param array<int, ResourceRecord> $records
     * @param array<string, mixed>       $relation
     */
    protected function preloadHasOneRelation(array $records, string $fieldName, string $outputName, array $relation, array $options = []): void
    {
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $foreignKey = (string) ($relation['foreign_key'] ?? '');
        $localKey = (string) ($relation['local_key'] ?? $this->resource->getPrimaryKey());
        if ('' === $table || '' === $foreignKey || '' === $localKey) {
            return;
        }

        $parentKeys = [];
        foreach ($records as $record) {
            $key = $record->{$localKey};
            if (null !== $key && '' !== (string) $key) {
                $parentKeys[] = $key;
            }
        }
        $parentKeys = array_values(array_unique($parentKeys, SORT_REGULAR));
        if (0 === \count($parentKeys)) {
            foreach ($records as $record) {
                $record->setRuntimeValue($outputName, null);
            }

            return;
        }

        $childResource = $table === $this->resource->getRawTable()
            ? $this->resource
            : Easy::schema($table)->raw();
        $childDocument = $childResource->document()->useContext($this->context);
        $requestedColumns = array_values(array_filter((array) ($options['columns'] ?? []), 'is_string'));
        $requestedOutputColumns = $requestedColumns;
        if (0 !== \count($requestedColumns) && !\in_array($foreignKey, $requestedColumns, true)) {
            $requestedColumns[] = $foreignKey;
        }

        $children = 0 !== \count($requestedColumns)
            ? $childDocument->query()->whereIn($foreignKey, $parentKeys)->get($requestedColumns)
            : $childDocument->query()->whereIn($foreignKey, $parentKeys)->get();

        $grouped = [];
        foreach ($children as $child) {
            $groupKey = (string) $child->{$foreignKey};
            if (array_key_exists($groupKey, $grouped)) {
                continue;
            }

            if (0 !== \count($requestedOutputColumns)) {
                $payload = [];
                foreach ($requestedOutputColumns as $column) {
                    $payload[$column] = $child->{$column};
                }
            } else {
                $payload = $child->toArray();
            }

            $grouped[$groupKey] = $payload;
        }

        foreach ($records as $record) {
            $record->setRuntimeValue($outputName, $grouped[(string) $record->{$localKey}] ?? null);
        }
    }

    /**
     * 批量加载单个 `belongsTo` 关系字段。
     *
     * @param array<int, ResourceRecord> $records
     * @param array<string, mixed>       $relation
     */
    protected function preloadBelongsToRelation(array $records, string $fieldName, string $outputName, array $relation, array $options = []): void
    {
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $valueColumn = (string) ($relation['value'] ?? 'id');
        if ('' === $table || '' === $valueColumn) {
            return;
        }

        $foreignValues = [];
        foreach ($records as $record) {
            $value = $record->{$fieldName};
            if (null !== $value && '' !== (string) $value) {
                $foreignValues[] = $value;
            }
        }
        $foreignValues = array_values(array_unique($foreignValues, SORT_REGULAR));
        if (0 === \count($foreignValues)) {
            foreach ($records as $record) {
                $record->setRuntimeValue($outputName, null);
            }

            return;
        }

        $childResource = $table === $this->resource->getRawTable()
            ? $this->resource
            : Easy::schema($table)->raw();
        $childDocument = $childResource->document()->useContext($this->context);
        $requestedColumns = array_values(array_filter((array) ($options['columns'] ?? []), 'is_string'));
        $requestedOutputColumns = $requestedColumns;
        if (0 !== \count($requestedColumns) && !\in_array($valueColumn, $requestedColumns, true)) {
            $requestedColumns[] = $valueColumn;
        }

        $children = 0 !== \count($requestedColumns)
            ? $childDocument->query()->whereIn($valueColumn, $foreignValues)->get($requestedColumns)
            : $childDocument->query()->whereIn($valueColumn, $foreignValues)->get();

        $mapped = [];
        foreach ($children as $child) {
            if (0 !== \count($requestedOutputColumns)) {
                $payload = [];
                foreach ($requestedOutputColumns as $column) {
                    $payload[$column] = $child->{$column};
                }
            } else {
                $payload = $child->toArray();
            }

            $mapped[(string) $child->{$valueColumn}] = $payload;
        }

        foreach ($records as $record) {
            $record->setRuntimeValue($outputName, $mapped[(string) $record->{$fieldName}] ?? null);
        }
    }

    /**
     * 同步单个 `hasOne` 关系字段的数据对象。
     *
     * @param array<string, mixed> $relation
     * @param mixed                $payload
     */
    protected function syncHasOneRelation(ResourceRecord $record, string $fieldName, array $relation, $payload): void
    {
        $context = $this->resolveHasManyRelationContext($record, $fieldName, $relation);
        $childDocument = $context['document'];
        $childPrimaryKey = $context['primary_key'];
        $foreignKey = $context['foreign_key'];
        $localValue = $context['local_value'];

        $existing = [];
        foreach ($childDocument->query()->where($foreignKey, $localValue)->get()->all() as $childRecord) {
            $existing[(string) $childRecord->{$childPrimaryKey}] = $childRecord;
        }

        $row = $this->normalizeOneSyncRow($fieldName, $payload);
        if (null === $row) {
            foreach ($existing as $orphan) {
                $childDocument->deleteRecord($orphan);
            }

            return;
        }

        $row[$foreignKey] = $localValue;
        $childId = $row[$childPrimaryKey] ?? null;
        $preservedId = null;
        if (null !== $childId && '' !== (string) $childId) {
            $existingRecord = $existing[(string) $childId] ?? null;
            if (null === $existingRecord) {
                throw new EasyException(__('ptadmin-easy::messages.errors.child_record_invalid', [
                    'field' => $fieldName,
                    'id' => $childId,
                ]));
            }

            unset($row[$childPrimaryKey]);
            $childDocument->updateRecord($existingRecord, $row);
            $preservedId = (string) $childId;
        } else {
            $created = $childDocument->insertRecord($row);
            $preservedId = null !== $created ? (string) $created->getKey() : null;
        }

        foreach ($existing as $existingId => $orphan) {
            if (null !== $preservedId && (string) $existingId === $preservedId) {
                continue;
            }

            $childDocument->deleteRecord($orphan);
        }
    }

    /**
     * 同步单个 `hasMany` 关系字段的数据集合。
     *
     * @param array<string, mixed> $relation
     * @param mixed                $payload
     */
    protected function syncHasManyRelation(ResourceRecord $record, string $fieldName, array $relation, $payload): void
    {
        $context = $this->resolveHasManyRelationContext($record, $fieldName, $relation);
        $childDocument = $context['document'];
        $childPrimaryKey = $context['primary_key'];
        $foreignKey = $context['foreign_key'];
        $localValue = $context['local_value'];
        $rows = $this->normalizeManySyncRows($fieldName, $payload);

        $existing = [];
        foreach ($childDocument->query()->where($foreignKey, $localValue)->get()->all() as $childRecord) {
            $existing[(string) $childRecord->{$childPrimaryKey}] = $childRecord;
        }

        foreach ($rows as $row) {
            $row[$foreignKey] = $localValue;
            $childId = $row[$childPrimaryKey] ?? null;
            if (null !== $childId && '' !== (string) $childId) {
                $existingRecord = $existing[(string) $childId] ?? null;
                if (null === $existingRecord) {
                    throw new EasyException(__('ptadmin-easy::messages.errors.child_record_invalid', [
                        'field' => $fieldName,
                        'id' => $childId,
                    ]));
                }

                unset($row[$childPrimaryKey]);
                $childDocument->updateRecord($existingRecord, $row);
                unset($existing[(string) $childId]);

                continue;
            }

            $childDocument->insertRecord($row);
        }

        foreach ($existing as $orphan) {
            $childDocument->deleteRecord($orphan);
        }
    }

    /**
     * 判断本次 payload 是否显式包含需要同步的 `hasMany` 关系字段。
     *
     * @param array<string, mixed> $data
     */
    protected function hasPendingRelationSync(array $data): bool
    {
        foreach ((array) $this->resource->getRelations() as $fieldName => $relation) {
            if (!\in_array((string) ($relation['kind'] ?? $relation['relation_kind'] ?? ''), ['hasOne', 'hasMany'], true)) {
                continue;
            }

            if (array_key_exists((string) $fieldName, $data)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 将 hasMany 更新 payload 统一为子记录列表。
     *
     * 允许传入单条关联数据对象，也允许显式传入空数组清空子表。
     *
     * @param mixed $payload
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeManySyncRows(string $fieldName, $payload): array
    {
        if (null === $payload) {
            return [];
        }

        if (!\is_array($payload)) {
            throw new EasyException(__('ptadmin-easy::messages.errors.has_many_must_array', ['field' => $fieldName]));
        }

        if (0 === \count($payload)) {
            return [];
        }

        $isList = array_keys($payload) === range(0, \count($payload) - 1);
        $rows = $isList ? $payload : [$payload];

        foreach ($rows as $index => $row) {
            if (\is_array($row)) {
                continue;
            }

            throw new EasyException(__('ptadmin-easy::messages.errors.has_many_item_must_object', [
                'field' => $fieldName,
                'index' => $index + 1,
            ]));
        }

        return array_values($rows);
    }

    /**
     * 将 hasOne 更新 payload 统一为单条子记录。
     *
     * 允许：
     * 1. 传对象数组
     * 2. 传单个对象
     * 3. 传 `null/[]` 清空当前关系
     *
     * @param mixed $payload
     *
     * @return array<string, mixed>|null
     */
    protected function normalizeOneSyncRow(string $fieldName, $payload): ?array
    {
        if (null === $payload) {
            return null;
        }

        if (!\is_array($payload)) {
            throw new EasyException(__('ptadmin-easy::messages.errors.has_one_must_object', ['field' => $fieldName]));
        }

        if (0 === \count($payload)) {
            return null;
        }

        $isList = array_keys($payload) === range(0, \count($payload) - 1);
        if (!$isList) {
            return $payload;
        }

        if (1 === \count($payload) && \is_array($payload[0] ?? null)) {
            return $payload[0];
        }

        throw new EasyException(__('ptadmin-easy::messages.errors.has_one_single_only', ['field' => $fieldName]));
    }

    /**
     * 解析并校验 `hasMany` 关系所需的运行时上下文。
     *
     * @param array<string, mixed> $relation
     *
     * @return array<string, mixed>
     */
    protected function resolveHasManyRelationContext(ResourceRecord $record, string $fieldName, array $relation): array
    {
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $foreignKey = (string) ($relation['foreign_key'] ?? '');
        $localKey = (string) ($relation['local_key'] ?? $this->resource->getPrimaryKey());
        if ('' === $table || '' === $foreignKey || '' === $localKey) {
            throw new EasyException(__('ptadmin-easy::messages.errors.has_many_relation_incomplete', ['field' => $fieldName]));
        }

        $resource = $table === $this->resource->getRawTable()
            ? $this->resource
            : Easy::schema($table)->raw();

        if (null === $resource->getField($foreignKey)) {
            throw new EasyException(__('ptadmin-easy::messages.errors.relation_resource_missing_foreign_key', [
                'table' => $table,
                'foreign_key' => $foreignKey,
            ]));
        }

        return [
            'table' => $table,
            'foreign_key' => $foreignKey,
            'local_key' => $localKey,
            'local_value' => $record->{$localKey},
            'document' => $resource->document()->useContext($this->context),
            'primary_key' => $resource->getPrimaryKey(),
        ];
    }

    /**
     * 解析运行时关系加载配置里对应的真实字段名。
     *
     * @param mixed $options
     */
    protected function resolveRuntimeRelationFieldName(string $outputName, $options): string
    {
        if (\is_array($options) && isset($options['field']) && \is_string($options['field']) && '' !== trim($options['field'])) {
            return trim($options['field']);
        }

        return $outputName;
    }
}
