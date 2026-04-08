<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;
use PTAdmin\Easy\Engine\Model\Document;
use PTAdmin\Easy\Engine\Resource\Traits\BaseResourceTrait;
use PTAdmin\Easy\Engine\Resource\Traits\LoaderTrait;
use PTAdmin\Easy\Engine\Resource\Traits\RelationsTrait;

/**
 * 资源定义实现.
 */
class ResourceDefinition implements IResource
{
    use BaseResourceTrait;
    use LoaderTrait;
    use RelationsTrait;

    public const ID = 'id';
    public const FIELD_NAME = 'fields';

    /** @var IResourceField[] 字段集合 */
    protected $fields = [];

    /** @var string[] 字段名称 */
    private $attributes = [];

    /** @var array */
    private $search_fields;

    /** @var array */
    private $order_fields;

    /** @var array */
    private $export_fields;

    /** @var array<string, array{0: array<string, array<int, mixed>>, 1: array<string, string>, 2: array<string, string>}> */
    private $rules;

    /** @var string */
    private $resource_name;

    /** @var array */
    private $appends = [];

    /**
     * @param array|string|null $resource
     * @param string            $module
     */
    public function __construct($resource = null, string $module = '')
    {
        if (\is_array($resource)) {
            $this->loadThroughMetadata($resource);
        } else {
            $this->parser((string) $resource, $module);
            $this->loader();
        }

        $this->initialize();
    }

    /**
     * @param array|string $resource
     */
    public static function make($resource, string $module = ''): IResource
    {
        return new self($resource, $module);
    }

    public function getTable(): string
    {
        return get_resource_table($this->getRawTable());
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getFillable(): array
    {
        return array_keys($this->getAttributes());
    }

    public function getComment()
    {
        return $this->getMetadata('intro', $this->getMetadata('title'));
    }

    public function getRawTable(): string
    {
        if (null === $this->resource_name) {
            $this->resource_name = $this->getMetadata('name');
        }

        return $this->resource_name;
    }

    public function getRules($id = 0): array
    {
        $cacheKey = null === $id ? 'create' : 'update:'.(string) $id;
        if (!isset($this->rules[$cacheKey])) {
            $rules = $attributes = $messages = [];
            foreach ($this->fields as $field) {
                $rule = $field->getRules($id);
                if (0 === \count($rule)) {
                    continue;
                }
                $attributes[$field->getName()] = $field->getLabel();
                $rules[$field->getName()] = $rule;

                if (method_exists($field, 'getRuleMessages')) {
                    $messages = array_merge($messages, $field->getRuleMessages());
                }
            }

            $this->rules[$cacheKey] = [$rules, $attributes, $messages];
        }

        return $this->rules[$cacheKey];
    }

    public function getAppends(): array
    {
        return $this->appends;
    }

    public function getPrimaryKey(): string
    {
        return self::ID;
    }

    public function getTitleField()
    {
        return $this->getMetadata('title_field', 'title');
    }

    public function getFieldCover()
    {
        return $this->getMetadata('cover_field', 'cover');
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $name): ?IResourceField
    {
        return $this->fields[$name] ?? null;
    }

    public function getExportFields(): array
    {
        if (null === $this->export_fields) {
            $this->export_fields = [];
        }

        return $this->export_fields;
    }

    public function getSearchFields(): array
    {
        if (null === $this->search_fields) {
            $this->search_fields = [];
        }

        return $this->search_fields;
    }

    public function getOrderFields(): array
    {
        if (null === $this->order_fields) {
            $this->order_fields = [];
        }

        return $this->order_fields;
    }

    public function insertField(IResourceField $field): void
    {
        if (!$field->isVirtual()) {
            $this->attributes[$field->getName()] = $field->getDefault();
        }
        $this->fields[$field->getName()] = $field;
    }

    public function document(): Document
    {
        return new Document($this);
    }

    public function allowImport(): bool
    {
        return (bool) $this->getMetadata('allow_import', true);
    }

    public function allowExport(): bool
    {
        return (bool) $this->getMetadata('allow_export', true);
    }

    public function allowCopy(): bool
    {
        return (bool) $this->getMetadata('allow_copy', true);
    }

    public function allowRecycle(): bool
    {
        return (bool) $this->getMetadata('allow_recycle', true);
    }

    public function trackChanges(): bool
    {
        return (bool) $this->getMetadata('track_changes', true);
    }

    public function getAppendsValue($model): array
    {
        if (0 === \count($this->getAppends())) {
            return [];
        }
        $appends = [];
        foreach ($this->getAppends() as $key => $field) {
            $obj = $this->getField($field);
            if (null === $obj) {
                continue;
            }
            $appends[$key] = $obj->getAppendValue($model);
        }

        return $appends;
    }

    public function toArray(): array
    {
        return $this->metadata;
    }

    protected function getMetadata($key = null, $default = null)
    {
        return null !== $key ? data_get($this->metadata, $key, $default) : $this->metadata;
    }

    private function initialize(): void
    {
        $this->metadata = (new SchemaNormalizer())->normalize($this->metadata);
        $this->order_fields = (array) $this->getMetadata('order', []);
        $this->export_fields = [];
        $this->search_fields = [];

        $data = $this->metadata[self::FIELD_NAME];
        foreach ($data as $value) {
            $field = app(IResourceField::class, ['data' => $value, 'resource' => $this]);
            $this->insertField($field);
            if (1 === (int) $field->getMetadata('is_export', 0) && 0 !== (int) $field->getMetadata('is_edit', 1)) {
                $this->export_fields[$field->getName()] = $field->getLabel();
            }
            if (1 === (int) $field->getMetadata('is_search', 0)) {
                $this->search_fields[] = $field->getName();
            }
            if ($field->isAppend()) {
                $this->appends[$field->getAppendName()] = $field->getName();
            }
        }

        if (0 === \count($this->export_fields)) {
            $this->export_fields = $this->normalizeExportFields((array) $this->getMetadata('export_fields', []));
        }
        if (0 === \count($this->search_fields)) {
            $this->search_fields = array_values(array_filter((array) $this->getMetadata('search_fields', []), 'is_string'));
        }
    }

    /**
     * @param array<int|string, mixed> $fields
     *
     * @return array<string, string>
     */
    private function normalizeExportFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $key => $value) {
            if (\is_string($key) && '' !== $key) {
                $normalized[$key] = \is_string($value) && '' !== $value ? $value : $key;

                continue;
            }

            if (\is_string($value) && '' !== $value) {
                $normalized[$value] = $value;
            }
        }

        return $normalized;
    }
}
