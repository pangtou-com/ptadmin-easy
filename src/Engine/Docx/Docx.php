<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Engine\Docx;

use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Contracts\IDocxField;
use PTAdmin\Easy\Engine\Docx\Traits\BaseDocxTrait;
use PTAdmin\Easy\Engine\Docx\Traits\LoaderTrait;
use PTAdmin\Easy\Engine\Docx\Traits\RelationsTrait;
use PTAdmin\Easy\Engine\Model\Document;

class Docx implements IDocx
{
    use BaseDocxTrait;
    use LoaderTrait;
    use RelationsTrait;

    public const ID = 'id';
    public const FIELD_NAME = 'fields';

    /** @var IDocxField[] 字段集合 */
    private $fields = [];
    /** @var string[] 字段名称 */
    private $attributes = [];
    /** @var array 搜索字段 */
    private $search_fields;
    /** @var array 排序规则 */
    private $order_fields;
    /** @var array 导出字段 */
    private $export_fields;
    /** @var array 校验规则 */
    private $rules;
    /** @var string 表名称 */
    private $table_name;
    /** @var array 附加属性信息 */
    private $appends = [];

    public function __construct($docx, string $module = '')
    {
        if (\is_array($docx)) {
            $this->loadThroughMetadata($docx);
        } else {
            $this->parser($docx, $module);
            $this->loader();
        }

        $this->initialize();
    }

    /**
     * 构建文档.
     *
     * @param array|string $docx   文档名称
     * @param string       $module 模块
     *
     * @return IDocx
     */
    public static function make($docx, string $module = ''): IDocx
    {
        return new self($docx, $module);
    }

    /**
     * 获取表名称.
     *
     * @return string
     */
    public function getTable(): string
    {
        return get_table_name($this->getRawTable());
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

    /**
     * 获取原始表名称.
     *
     * @return string
     */
    public function getRawTable(): string
    {
        if (null === $this->table_name) {
            $this->table_name = $this->getMetadata('table_name');
        }

        return $this->table_name;
    }

    public function getRules($id = 0): array
    {
        if (null === $this->rules) {
            $rules = $attributes = [];
            foreach ($this->fields as $field) {
                $rule = $field->getRules($id);
                if (0 === \count($rule)) {
                    continue;
                }
                $attributes[$field->getName()] = $field->getLabel();
                $rules[$field->getName()] = $rule;
            }

            $this->rules = [$rules, $attributes];
        }

        return $this->rules;
    }

    public function getAppends(): array
    {
        return $this->appends;
    }

    /**
     * 获取主键字段.
     */
    public function getPrimaryKey(): string
    {
        return self::ID;
    }

    /**
     * 返回标题字段.
     *
     * @return mixed|string
     */
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

    public function getField(string $name): ?IDocxField
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

    public function insertField(IDocxField $field): void
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

    /**
     * 初始化.
     */
    private function initialize(): void
    {
        $data = $this->metadata[self::FIELD_NAME];
        foreach ($data as $key => $value) {
            /** @var IDocxField $field */
            $field = app(IDocxField::class, ['data' => $value, 'docx' => $this]);
            $this->insertField($field);
            if ($field->isAppend()) {
                $this->appends[$field->getAppendName()] = $field->getName();
            }
            if ($field->isRelation()) {
                // $this->easy_relations[$field->getName()] = $field->getRelation();
            }
        }
    }
}
