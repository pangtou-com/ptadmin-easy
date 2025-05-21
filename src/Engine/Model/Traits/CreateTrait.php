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

use Illuminate\Support\Facades\DB;

trait CreateTrait
{
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
        $model->save();

        $this->trigger('after_saving', $model);

        return $model;
    }

    /**
     * 保存数据，并跟踪记录数据的更新情况.
     * 当数据存在一对多的情况时，会同步更新关联表数据。
     *
     * @param array $data
     * @param bool  $many 是否同步修改关联表数据
     *
     * @return bool
     */
    public function store(array $data, bool $many = true): bool
    {
        $data = $this->createValidate($data);
        // 1、关联表的数据存储
        // 2、数据修改器[已处理]
        // 3、数据校验的完整性
        DB::transaction(function () use ($data, $many): void {
            $this->model = $this->save($data);
            if ($many && null !== $this->model) {
                $this->createMany($data, $this->model);
            }
        });
        // TODO 更新全局搜索字段信息
        // TODO 记录更新日志
        $this->track($this->model);

        return null !== $this->model;
    }

    public function many(array $data): void
    {
    }

    /**
     * 关联表的数据存储.
     *
     * @param array      $data
     * @param null|mixed $model
     *
     * @return bool
     */
    public function createMany(array $data, $model = null): bool
    {
        $model = null === $model ? $this->model : $model;
        if (null === $model || !$model->exists) {
            return false;
        }
        $relation = $this->docx->getRelations();
        if (!\is_array($relation) || 0 === \count($relation)) {
            return false;
        }
        foreach ($relation as $key => $val) {
            if (!isset($data[$key])) {
                continue;
            }
            // todo 待处理
        }

        return true;
    }
}
