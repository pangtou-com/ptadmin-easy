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

use PTAdmin\Easy\Engine\Model\FormDTO;
use PTAdmin\Easy\Engine\Model\Validate;

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

        return (int) $model->update($data);
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

        if ((int) $model->update($data) > 0) {
            $this->track($model);
        }
    }

    /**
     * 直接保存数据.
     *
     * @param array $data
     *
     * @return null|\PTAdmin\Easy\Engine\Model\EasyModel
     */
    public function save(array $data): ?\PTAdmin\Easy\Engine\Model\EasyModel
    {
        $model = $this->newModel();
        if (false === $this->trigger('before_saving', $model)) {
            return null;
        }
        $model->fill($data);
        $saved = $model->save();

        if ($saved) {
            $this->trigger('after_saving', $model);
        }

        return $saved ? $this->model = $model : null;
    }

    /**
     * 保存数据，并跟踪记录数据的更新情况.
     *
     * @param array $data
     *
     * @return \PTAdmin\Easy\Engine\Model\EasyModel|\PTAdmin\Easy\Engine\Model\EasyModel[]
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
        if (null !== $this->store($data)) {
            $this->handleSaveMany($data);
        }
    }

    public function many(): void
    {
        $docx = $this->docx();
    }

    /**
     * 关联表的数据存储.1对多的数据存储.
     *
     * @param mixed $field
     * @param array $data
     */
    public function createMany($field, array $data): void
    {
        $field = $this->docx()->getField($field);
        $relation = $field->getRelation();
        dump($relation);
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
        if (null === $model || !$model->exists) {
            return false;
        }
        $relation = $this->docx->getRelations();
        if (0 === \count($relation)) {
            return false;
        }
        foreach ($relation as $key => $val) {
            if (!isset($data[$key])) {
                continue;
            }
            $this->createMany($key, $data[$key]);
        }
    }
}
