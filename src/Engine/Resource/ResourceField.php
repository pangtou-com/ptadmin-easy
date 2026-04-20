<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use PTAdmin\Easy\Components\Traits\BaseComponentTrait;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Engine\Model\ResourceRecord;
use PTAdmin\Easy\Engine\Resource\Traits\OptionsTrait;

/**
 * 资源字段实现.
 */
class ResourceField implements IResourceField
{
    use BaseComponentTrait;
    use OptionsTrait;

    /** @var array<string, array<string, string>> 当前进程内的关系标签缓存 */
    private static $relationLabelCache = [];

    private $type;
    private $name;
    private $component;
    private $metadata;
    private $resource;

    /**
     * @param array<string, mixed> $data
     * @param IResource|null       $resource
     */
    public function __construct($data, ?IResource $resource = null)
    {
        $this->metadata = $data;
        $this->resource = $resource;
    }

    public function getComponent(): IComponent
    {
        if (null !== $this->component) {
            return $this->component;
        }
        $this->component = Easy::component()->build($this);

        return $this->component;
    }

    /**
     * 在写操作后清空关系标签缓存，避免同进程长生命周期场景读取到旧值。
     */
    public static function flushRelationLabelCache(): void
    {
        self::$relationLabelCache = [];
    }

    public function getComment(): string
    {
        $help = $this->getMetadata('help');
        if (\is_string($help) && '' !== trim($help)) {
            return trim($help);
        }

        return $this->getMetadata('comment', $this->getLabel());
    }

    public function getLabel(): string
    {
        return $this->getMetadata('label');
    }

    public function getRelation(): array
    {
        $relation = array_replace(
            (array) $this->getMetadata('extends', []),
            (array) $this->getMetadata('relation', [])
        );
        if (!isset($relation['table']) && isset($relation['name']) && \is_string($relation['name'])) {
            $relation['table'] = $relation['name'];
        }

        return $relation;
    }

    public function isRelation(): bool
    {
        if (!Easy::component()->isRelation($this->getType())) {
            return false;
        }
        if (\in_array($this->getType(), ['radio', 'checkbox', 'select'], true)) {
            $relation = $this->getRelation();

            return isset($relation['type'], $relation['table']) && 'resource' === $relation['type'];
        }

        return true;
    }

    public function isVirtual(): bool
    {
        return Easy::component()->isVirtual($this->getType());
    }

    public function isAppend(): bool
    {
        return Easy::component()->isAppend($this->getType());
    }

    public function getAppendName(): string
    {
        return $this->getMetadata('extends.append_name', $this->getDefaultAppendName());
    }

    public function getAppendValue($model)
    {
        if ($model instanceof ResourceRecord) {
            $cacheKey = 'append:'.$this->getAppendName();
            if ($model->hasRuntimeValue($cacheKey)) {
                return $model->getRuntimeValue($cacheKey);
            }
        }

        $value = $model->{$this->getName()};
        if (null === $value) {
            if ($model instanceof ResourceRecord) {
                $model->setRuntimeValue('append:'.$this->getAppendName(), null);
            }

            return null;
        }
        $method = 'getAppend'.ucfirst($this->getType()).'Value';
        if (method_exists($this, $method)) {
            $value = \call_user_func([$this, $method], $model, $value);
        }

        if ($model instanceof ResourceRecord) {
            $model->setRuntimeValue('append:'.$this->getAppendName(), $value);
        }

        return $value;
    }

    /**
     * 批量预加载 append 展示值，减少列表场景下的重复关系查询。
     *
     * 当前仅处理依赖关联资源的 select/radio/checkbox/link 字段，
     * 其他类型保持惰性计算。
     *
     * @param array<int, ResourceRecord> $models
     */
    public function preloadAppendValues(array $models): void
    {
        $records = array_values(array_filter($models, static function ($model): bool {
            return $model instanceof ResourceRecord;
        }));
        if (0 === \count($records)) {
            return;
        }

        $type = $this->getType();
        if ('link' === $type) {
            $this->preloadSingleRelationAppendValues($records, true);

            return;
        }

        if (!\in_array($type, ['radio', 'checkbox', 'select'], true) || !$this->isSourceResource()) {
            return;
        }

        if ($this->isMultiple()) {
            $this->preloadMultiRelationAppendValues($records);

            return;
        }

        $this->preloadSingleRelationAppendValues($records);
    }

    public function getRules($id = 0): array
    {
        if ($this->isVirtual() || 'auto' === $this->getType()) {
            return [];
        }
        $type = ucfirst($this->getType());
        $checkMethod = ['required', 'unique', "get{$type}Rule"];
        $rules = array_merge(
            Arr::wrap($this->getMetadata('rules', [])),
            $this->derivedRules()
        );
        foreach ($checkMethod as $method) {
            if (method_exists($this, $method)) {
                $rule = \call_user_func_array([$this, $method], [$id]);
                if (null !== $rule && false !== $rule) {
                    $rules = array_merge($rules, Arr::wrap($rule));
                }
            }
        }

        return $this->uniqueRules($rules);
    }

    public function getRuleMessages(): array
    {
        $messages = [];
        foreach ((array) $this->getMetadata('rule_messages', []) as $rule => $message) {
            if (!\is_string($rule) || '' === trim($rule)) {
                continue;
            }

            if (!\is_string($message) || '' === trim($message)) {
                continue;
            }

            $messages[$this->getName().'.'.trim($rule)] = trim($message);
        }

        return $messages;
    }

    public function getComponentAttributeValue($model, $val)
    {
        if ($this->hasGetMutator()) {
            return \call_user_func_array([$this, $this->getGetMutatorMethod()], [$val, $model]);
        }

        return $val;
    }

    public function setComponentAttributeValue($model, $val)
    {
        if ($this->hasSetMutator()) {
            return \call_user_func_array([$this, $this->getSetMutatorMethod()], [$val, $model]);
        }

        return $val;
    }

    public function getDefault()
    {
        return $this->getMetadata('default', $this->getMetadata('defaultValue'));
    }

    public function getMetadata($key = null, $default = null)
    {
        return $key ? data_get($this->metadata, $key, $default) : $this->metadata;
    }

    public function getName(): string
    {
        if (null === $this->name) {
            $this->name = $this->getMetadata('name');
        }

        return $this->name;
    }

    public function exists(): bool
    {
        $id = (int) $this->getMetadata('id');

        return $id > 0;
    }

    public function getType(): string
    {
        if (null === $this->type) {
            $this->type = $this->getMetadata('type', 'text');
        }

        return $this->type;
    }

    public function getResource(): IResource
    {
        return $this->resource;
    }

    public function required(): ?string
    {
        if (true === (bool) $this->getMetadata('required', false)) {
            return 'required';
        }

        if (1 === (int) $this->getMetadata('is_required', 0)) {
            return 'required';
        }

        return null;
    }

    protected function getAppendRadioValue($model, $value)
    {
        if ($this->isSourceResource()) {
            $labels = $this->relationLabelsForValues([$value]);

            return $labels[(string) $value] ?? null;
        }

        $options = $this->getOptions();
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return null;
    }

    protected function getAppendCheckboxValue($model, $value): array
    {
        if (!\is_array($value)) {
            $value = explode(',', $value);
            $value = array_filter($value);
        }

        if ($this->isSourceResource()) {
            return array_values($this->relationLabelsForValues($value));
        }

        $options = $this->getOptions();
        $result = [];
        foreach ($options as $option) {
            if (\in_array($option['value'], $value, true)) {
                $result[] = $option['label'];
            }
        }

        return $result;
    }

    protected function getAppendSelectValue($model, $value)
    {
        return $this->isMultiple() ? $this->getAppendCheckboxValue($model, $value) : $this->getAppendRadioValue($model, $value);
    }

    protected function getAppendSwitchValue($model, $value)
    {
        return $this->getAppendRadioValue($model, $value);
    }

    protected function getAppendLinkValue($model, $value)
    {
        $labels = $this->relationLabelsForValues([$value], true);

        return $labels[(string) $value] ?? null;
    }

    protected function getDefaultAppendName(): string
    {
        return '__'.$this->getName().'_text';
    }

    /**
     * @param array<int, ResourceRecord> $records
     */
    private function preloadSingleRelationAppendValues(array $records, bool $forceRelation = false): void
    {
        $values = [];
        foreach ($records as $record) {
            $value = $record->{$this->getName()};
            if (null === $value || '' === (string) $value) {
                continue;
            }

            $values[] = $value;
        }

        $labels = $this->relationLabelsForValues($values, $forceRelation);
        foreach ($records as $record) {
            $value = $record->{$this->getName()};
            $record->setRuntimeValue(
                'append:'.$this->getAppendName(),
                null === $value ? null : ($labels[(string) $value] ?? null)
            );
        }
    }

    /**
     * @param array<int, ResourceRecord> $records
     */
    private function preloadMultiRelationAppendValues(array $records): void
    {
        $values = [];
        $recordValues = [];

        foreach ($records as $record) {
            $value = $record->{$this->getName()};
            if (!\is_array($value)) {
                $value = explode(',', (string) $value);
                $value = array_filter($value, static function ($item): bool {
                    return '' !== (string) $item;
                });
            }

            $normalized = array_values($value);
            $recordValues[spl_object_hash($record)] = $normalized;
            $values = array_merge($values, $normalized);
        }

        $labels = $this->relationLabelsForValues($values);
        foreach ($records as $record) {
            $resolved = [];
            foreach ($recordValues[spl_object_hash($record)] ?? [] as $value) {
                if (isset($labels[(string) $value])) {
                    $resolved[] = $labels[(string) $value];
                }
            }

            $record->setRuntimeValue('append:'.$this->getAppendName(), $resolved);
        }
    }

    /**
     * 根据关联配置批量解析展示标签.
     *
     * @param array<int, mixed> $values
     *
     * @return array<string, string>
     */
    private function relationLabelsForValues(array $values, bool $forceRelation = false): array
    {
        $values = array_values(array_filter($values, static function ($value): bool {
            return null !== $value && '' !== (string) $value;
        }));
        if (0 === \count($values)) {
            return [];
        }

        $relation = $forceRelation ? $this->getRelation() : (array) $this->getMetadata('extends', []);
        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $valueColumn = (string) ($relation['value'] ?? 'id');
        $labelColumn = (string) ($relation['label'] ?? 'title');
        if ('' === $table || '' === $valueColumn || '' === $labelColumn) {
            return [];
        }

        $cacheKey = $this->relationLabelCacheKey($table, $valueColumn, $labelColumn, (array) ($relation['filter'] ?? []), $values);
        if (isset(self::$relationLabelCache[$cacheKey])) {
            return self::$relationLabelCache[$cacheKey];
        }

        $resource = $table === $this->getResource()->getRawTable()
            ? $this->getResource()
            : Easy::schema($table)->raw();

        $query = DB::table($resource->getRawTable())
            ->select([$valueColumn, $labelColumn])
            ->whereIn($valueColumn, $values);

        if ($resource->allowRecycle()) {
            $query->whereNull('deleted_at');
        }

        $this->applyRelationFilters($query, (array) ($relation['filter'] ?? []));

        self::$relationLabelCache[$cacheKey] = $query->get()->reduce(static function (array $carry, $row) use ($valueColumn, $labelColumn): array {
            $record = (array) $row;
            $key = isset($record[$valueColumn]) ? (string) $record[$valueColumn] : null;
            $label = isset($record[$labelColumn]) ? (string) $record[$labelColumn] : null;
            if (null === $key || null === $label) {
                return $carry;
            }

            $carry[$key] = $label;

            return $carry;
        }, []);

        return self::$relationLabelCache[$cacheKey];
    }

    /**
     * 生成关系标签缓存键，按查询语义合并相同的关系解析请求。
     *
     * @param array<int|string, mixed> $filters
     * @param array<int, mixed>        $values
     */
    private function relationLabelCacheKey(string $table, string $valueColumn, string $labelColumn, array $filters, array $values): string
    {
        $normalizedValues = array_map(static function ($value): string {
            return (string) $value;
        }, $values);
        sort($normalizedValues);

        return md5(json_encode([
            'table' => $table,
            'value' => $valueColumn,
            'label' => $labelColumn,
            'filter' => $filters,
            'values' => $normalizedValues,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 将关联过滤配置应用到查询上.
     *
     * @param mixed $query
     * @param array<int|string, mixed> $filters
     */
    private function applyRelationFilters($query, array $filters): void
    {
        foreach ($filters as $key => $filter) {
            if (\is_string($key) && !\is_array($filter)) {
                $query->where($key, $filter);

                continue;
            }

            if (!\is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? $filter['name'] ?? null;
            if (!\is_string($field) || '' === $field) {
                continue;
            }

            $operator = (string) ($filter['operator'] ?? '=');
            $value = $filter['value'] ?? null;
            if ('in' === strtolower($operator) && \is_array($value)) {
                $query->whereIn($field, $value);

                continue;
            }

            $query->where($field, $operator, $value);
        }
    }

    protected function unique($id): ?\Illuminate\Validation\Rules\Unique
    {
        if (1 === (int) $this->getMetadata('is_unique')) {
            $rule = Rule::unique($this->resource->getRawTable(), $this->getName());
            if ($this->resource->allowRecycle()) {
                $rule->whereNull('deleted_at');
            }

            return $rule->ignore($id);
        }

        return null;
    }

    protected function derivedRules(): array
    {
        $rules = [];
        $type = $this->getType();
        $length = $this->getMetadata('maxlength', $this->getMetadata('length'));
        $min = $this->getMetadata('min');
        $max = $this->getMetadata('max', $this->getMetadata('extends.max'));
        $pattern = $this->getMetadata('pattern');

        if ('number' === $type) {
            $rules[] = 'numeric';
        }

        if (\in_array($type, ['image', 'attachment'], true)) {
            $rules[] = 'array';

            $limit = $this->getMetadata('limit', $this->getMetadata('extends.limit'));
            if (\is_numeric($limit) && (int) $limit > 0) {
                $rules[] = 'max:'.(string) ((int) $limit);
            }
        }

        if (\is_numeric($length) && \in_array($type, ['text', 'textarea', 'password', 'color', 'icon_input', 'rich_text', 'time'], true)) {
            $rules[] = 'max:'.(string) $length;
        }

        if (\is_numeric($min) && \in_array($type, ['text', 'textarea', 'password', 'color', 'icon_input', 'rich_text', 'number', 'rate', 'slider'], true)) {
            $rules[] = 'min:'.(string) $min;
        }

        if (\is_numeric($max) && \in_array($type, ['rate', 'slider'], true)) {
            $rules[] = 'max:'.(string) $max;
        }

        if ('number' === $type && \is_numeric($max)) {
            $rules[] = 'max:'.(string) $max;
        }

        if (\is_string($pattern) && '' !== trim($pattern)) {
            $rules[] = 'regex:'.$this->normalizeRegexPattern($pattern);
        }

        return $this->uniqueRules($rules);
    }

    protected function uniqueRules(array $rules): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rules as $rule) {
            if (\is_string($rule)) {
                if (isset($seen[$rule])) {
                    continue;
                }
                $seen[$rule] = true;
            }

            $normalized[] = $rule;
        }

        return $normalized;
    }

    protected function normalizeRegexPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ('' === $pattern) {
            return '/^$/';
        }

        $first = substr($pattern, 0, 1);
        $last = substr($pattern, -1);
        if ('/' === $first && '/' === $last && 1 < strlen($pattern)) {
            return $pattern;
        }

        return '/'.str_replace('/', '\/', $pattern).'/';
    }
}
