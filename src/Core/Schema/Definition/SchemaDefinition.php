<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Definition;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Schema\Mapping\FieldMappingResolver;
use PTAdmin\Easy\Core\Support\FieldTypeRegistry;
use PTAdmin\Easy\Engine\Model\Document;

/**
 * 编译后的 schema 定义对象.
 *
 * 对外暴露只读的资源元数据和字段能力信息。
 */
final class SchemaDefinition
{
    /** @var IResource */
    private $resource;

    /** @var FieldTypeRegistry */
    private $fieldTypeRegistry;

    /** @var FieldDefinition[]|null */
    private $fields;

    /** @var FieldMappingResolver */
    private $fieldMappingResolver;

    public function __construct(IResource $resource, ?FieldTypeRegistry $fieldTypeRegistry = null)
    {
        $this->resource = $resource;
        $this->fieldTypeRegistry = $fieldTypeRegistry ?? new FieldTypeRegistry();
        $this->fieldMappingResolver = new FieldMappingResolver();
    }

    /**
     * 返回资源名称.
     */
    public function name(): string
    {
        return $this->resource->getRawTable();
    }

    /**
     * 返回带前缀的数据表名称.
     */
    public function table(): string
    {
        return $this->resource->getTable();
    }

    /**
     * 返回主键字段名.
     */
    public function primaryKey(): string
    {
        return $this->resource->getPrimaryKey();
    }

    /**
     * @return FieldDefinition[]
     */
    public function fields(): array
    {
        if (null !== $this->fields) {
            return $this->fields;
        }

        $this->fields = [];
        foreach ($this->resource->getFields() as $field) {
            $definition = new FieldDefinition($field);
            $capabilities = $this->fieldTypeRegistry->resolve($definition);
            $resolvedDefinition = new FieldDefinition($field, $capabilities);
            $this->fields[$field->getName()] = new FieldDefinition(
                $field,
                $capabilities,
                $this->fieldMappingResolver->resolve($resolvedDefinition)
            );
        }

        return $this->fields;
    }

    /**
     * 返回指定字段定义.
     */
    public function field(string $name): ?FieldDefinition
    {
        $fields = $this->fields();

        return $fields[$name] ?? null;
    }

    /**
     * 返回默认属性集合.
     */
    public function attributes(): array
    {
        return $this->resource->getAttributes();
    }

    /**
     * 返回校验规则集合.
     */
    public function rules($id = 0): array
    {
        return $this->resource->getRules($id);
    }

    /**
     * 返回关联关系定义.
     */
    public function relations()
    {
        return $this->resource->getRelations();
    }

    /**
     * 返回底层 Document 对象.
     */
    public function document(): Document
    {
        return $this->resource->document();
    }

    /**
     * 返回底层原始资源定义对象.
     */
    public function raw(): IResource
    {
        return $this->resource;
    }

    /**
     * 返回资源所属模块.
     */
    public function module(): string
    {
        return (string) data_get($this->resource->toArray(), 'module', 'App');
    }

    /**
     * 返回资源标题.
     */
    public function title(): string
    {
        return (string) data_get($this->resource->toArray(), 'title', $this->name());
    }

    /**
     * 返回资源备注说明.
     */
    public function comment(): string
    {
        return (string) $this->resource->getComment();
    }

    /**
     * 返回表格视图配置.
     *
     * @return array<string, mixed>
     */
    public function tableView(): array
    {
        return (array) data_get($this->resource->toArray(), 'table', []);
    }

    /**
     * 返回表单视图配置.
     *
     * @return array<string, mixed>
     */
    public function formView(): array
    {
        return (array) data_get($this->resource->toArray(), 'form', []);
    }

    /**
     * 返回布局节点配置.
     *
     * @return array<string, mixed>
     */
    public function layout(): array
    {
        return (array) data_get($this->resource->toArray(), 'layout', []);
    }

    /**
     * 返回权限配置.
     *
     * @return array<string, mixed>
     */
    public function permissions(): array
    {
        return (array) data_get($this->resource->toArray(), 'permissions', []);
    }

    /**
     * 返回图表配置.
     *
     * @return array<int, mixed>
     */
    public function charts(): array
    {
        return array_values((array) data_get($this->resource->toArray(), 'charts', []));
    }

    /**
     * 返回资源标准化蓝图.
     *
     * 该结构面向前端编辑器、配置预览和发布确认页，屏蔽内部
     * 兼容细节，统一输出资源、视图、布局和字段能力描述。
     *
     * @return array<string, mixed>
     */
    public function blueprint(): array
    {
        return [
            'resource' => [
                'name' => $this->name(),
                'title' => $this->title(),
                'module' => $this->module(),
                'table' => $this->table(),
                'primary_key' => $this->primaryKey(),
                'comment' => $this->comment(),
            ],
            'views' => [
                'table' => $this->tableView(),
                'form' => $this->formView(),
            ],
            'layout' => $this->layout(),
            'fields' => array_values(array_map(static function (FieldDefinition $field): array {
                return $field->toArray();
            }, $this->fields())),
            'relations' => $this->relations(),
            'permissions' => $this->permissions(),
            'charts' => $this->charts(),
        ];
    }

    /**
     * 返回字段类型注册表.
     */
    public function fieldTypeRegistry(): FieldTypeRegistry
    {
        return $this->fieldTypeRegistry;
    }

    /**
     * 返回全部字段统一映射.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fieldMappings(): array
    {
        $mappings = [];
        foreach ($this->fields() as $field) {
            $mappings[$field->name()] = $field->mapping();
        }

        return $mappings;
    }

    /**
     * 将 schema 定义导出为数组.
     */
    public function toArray(): array
    {
        return $this->resource->toArray();
    }
}
