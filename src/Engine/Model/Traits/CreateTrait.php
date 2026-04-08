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

namespace PTAdmin\Easy\Engine\Model\Traits;

use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Engine\Model\FormDTO;
use PTAdmin\Easy\Engine\Model\Validate;
use PTAdmin\Easy\Exceptions\EasyException;

trait CreateTrait
{
    /** @var bool 是否需要校验 */
    protected $validate = true;

    /**
     * 直接更新数据.不会记录跟踪数据更新情况.
     *
     * @param array $data
     *
     * @return int
     */
    public function update(array $data): int
    {
        $model = $this->getEditModel();
        if (null === $model || !method_exists($model, 'getKey')) {
            return 0;
        }

        return null !== $this->updateRecord($model, $data) ? 1 : 0;
    }

    /**
     * 更新数据同时会根据需求更新跟踪数据的更新情况.
     *
     * @param array $data
     */
    public function edit(array $data): void
    {
        $model = $this->getEditModel();
        $dto = FormDTO::make($data, $model);

        if ($this->validate) {
            (new Validate($dto, $this))->validate();
        }

        if (null !== $this->updateRecord($model, $dto->getData())) {
            $this->track($model);
        }
    }

    /**
     * 直接保存数据.
     *
     * @param array $data
     *
     * @return null|\PTAdmin\Easy\Engine\Model\ResourceRecord
     */
    public function save(array $data): ?\PTAdmin\Easy\Engine\Model\ResourceRecord
    {
        $model = $this->newRecord();
        if (false === $this->trigger('before_saving', $model)) {
            return null;
        }
        $saved = $this->insertRecord($data);

        if (null !== $saved) {
            $this->trigger('after_saving', $saved);
        }

        return $this->model = $saved;
    }

    /**
     * 保存数据，并跟踪记录数据的更新情况.
     *
     * @param array $data
     *
     * @return \PTAdmin\Easy\Engine\Model\ResourceRecord|mixed
     */
    public function store(array $data)
    {
        $dto = FormDTO::make($data);
        if ($this->validate) {
            (new Validate($dto, $this))->validate();
        }

        // 保存成功后执行后续处理
        // 1、记录日志
        // 2、更新全局搜索字段信息
        if (null !== $this->save($dto->getData())) {
            $this->handleSaveMany($dto->getData());
            $this->track($this->model);
        }

        return $this->model;
    }

    /**
     * 保存数据并同步修改关联表数据.
     *
     * @param array $data
     */
    public function storeAndSaveMany(array $data): void
    {
        $this->store($data);
    }

    public function many(): void
    {
        $resource = $this->resource();
    }

    /**
     * 关联表的数据存储.1对多的数据存储.
     *
     * @param mixed $field
     * @param array $data
     */
    public function createMany($field, array $data): void
    {
        $field = $this->resource()->getField((string) $field);
        if (null === $field) {
            return;
        }

        $relation = $field->getRelation();
        if ('hasMany' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
            return;
        }

        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $foreignKey = (string) ($relation['foreign_key'] ?? '');
        $localKey = (string) ($relation['local_key'] ?? $this->resource()->getPrimaryKey());
        if ('' === $table || '' === $foreignKey || '' === $localKey) {
            throw new EasyException("字段【{$field->getName()}】的 hasMany 关系配置不完整。");
        }

        $model = $this->model;
        if (null === $model || !method_exists($model, 'getKey')) {
            return;
        }

        $localValue = $model->{$localKey};
        $resource = $table === $this->resource()->getRawTable()
            ? $this->resource()
            : Easy::schema($table)->raw();

        if (null === $resource->getField($foreignKey)) {
            throw new EasyException("关联资源【{$table}】缺少外键字段【{$foreignKey}】。");
        }

        $rows = $this->normalizeManyRows($data);
        if (0 === \count($rows)) {
            return;
        }

        $document = $resource->document();
        foreach ($rows as $row) {
            $row[$foreignKey] = $localValue;
            $document->store($row);
        }
    }

    /**
     * 存储单条 `hasOne` 关联数据。
     *
     * `hasOne` 在创建阶段只负责写入单条子记录；若传入空值则忽略。
     *
     * @param mixed $field
     * @param mixed $data
     */
    public function createOne($field, $data): void
    {
        $field = $this->resource()->getField((string) $field);
        if (null === $field) {
            return;
        }

        $relation = $field->getRelation();
        if ('hasOne' !== (string) ($relation['kind'] ?? $relation['relation_kind'] ?? '')) {
            return;
        }

        $table = (string) ($relation['table'] ?? ($relation['name'] ?? ''));
        $foreignKey = (string) ($relation['foreign_key'] ?? '');
        $localKey = (string) ($relation['local_key'] ?? $this->resource()->getPrimaryKey());
        if ('' === $table || '' === $foreignKey || '' === $localKey) {
            throw new EasyException("字段【{$field->getName()}】的 hasOne 关系配置不完整。");
        }

        $model = $this->model;
        if (null === $model || !method_exists($model, 'getKey')) {
            return;
        }

        $row = $this->normalizeOneRow($field->getName(), $data);
        if (null === $row) {
            return;
        }

        $localValue = $model->{$localKey};
        $resource = $table === $this->resource()->getRawTable()
            ? $this->resource()
            : Easy::schema($table)->raw();

        if (null === $resource->getField($foreignKey)) {
            throw new EasyException("关联资源【{$table}】缺少外键字段【{$foreignKey}】。");
        }

        $row[$foreignKey] = $localValue;
        $resource->document()->store($row);
    }

    /**
     * 设置验证.
     *
     * @param mixed $validate
     *
     * @return BaseTrait|\PTAdmin\Easy\Engine\Model\Document
     */
    public function setValidate($validate = true): self
    {
        $this->validate = $validate;

        return $this;
    }

    /**
     * 保存组件为table类型的一对多关联数据.
     *
     * @param array $data
     *
     * @return false|void
     */
    protected function handleSaveMany(array $data)
    {
        $model = $this->model;
        if (null === $model || !method_exists($model, 'getKey') || null === $model->getKey()) {
            return false;
        }
        $relation = $this->resource->getRelations();
        if (0 === \count($relation)) {
            return false;
        }
        foreach ($relation as $key => $val) {
            if (!isset($data[$key])) {
                continue;
            }
            $kind = (string) ($val['kind'] ?? $val['relation_kind'] ?? '');
            if ('hasMany' === $kind) {
                $this->createMany($key, $data[$key]);

                continue;
            }

            if ('hasOne' === $kind) {
                $this->createOne($key, $data[$key]);
            }
        }
    }

    /**
     * 统一一对多明细数据输入格式。
     *
     * @param mixed $data
     *
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeManyRows($data): array
    {
        if (!\is_array($data)) {
            return [];
        }
        if (0 === \count($data)) {
            return [];
        }

        $isList = array_keys($data) === range(0, \count($data) - 1);
        $rows = $isList ? $data : [$data];

        return array_values(array_filter($rows, static function ($row): bool {
            return \is_array($row);
        }));
    }

    /**
     * 统一单条关联数据输入格式。
     *
     * @param mixed $data
     *
     * @return array<string, mixed>|null
     */
    protected function normalizeOneRow(string $fieldName, $data): ?array
    {
        if (null === $data) {
            return null;
        }

        if (!\is_array($data)) {
            throw new EasyException("字段【{$fieldName}】的 hasOne 数据必须为对象。");
        }

        if (0 === \count($data)) {
            return null;
        }

        $isList = array_keys($data) === range(0, \count($data) - 1);
        if (!$isList) {
            return $data;
        }

        if (1 === \count($data) && \is_array($data[0] ?? null)) {
            return $data[0];
        }

        throw new EasyException("字段【{$fieldName}】的 hasOne 数据只能提交一条记录。");
    }
}
