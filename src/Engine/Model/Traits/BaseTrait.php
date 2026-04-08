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

trait BaseTrait
{
    public function get()
    {
        return $this->query()->get();
    }

    public function first()
    {
        return $this->query()->first();
    }

    public function find($id)
    {
        return $this->findRecord($id);
    }

    /**
     * 翻页获取数据.
     *
     * @param mixed $limit
     *
     * @return array
     */
    public function page($limit = null): array
    {
        $filterMap = $this->query();

        return $filterMap->paginate($limit)->toArray();
    }

    /**
     * 获取数据，不翻页的情况下获取.
     *
     * @param int $limit
     *
     * @return array
     */
    public function lists(int $limit = 0): array
    {
        $query = $this->query();
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $this->model = $query->get()->toArray();
    }

    /**
     * 获取当前的操作模型或查询对象，用于修改数据.
     *
     * @return null|mixed
     */
    protected function getEditModel()
    {
        if (null !== $this->model) {
            return $this->model;
        }
        if (null !== $this->query) {
            return $this->query;
        }

        return null;
    }

    /**
     * TODO 数据跟踪.
     *
     * @param mixed $model
     * @param mixed $type
     */
    protected function track($model, $type = 'create'): void
    {
        if (!$this->resource()->trackChanges()) {
            return;
        }
    }
}
