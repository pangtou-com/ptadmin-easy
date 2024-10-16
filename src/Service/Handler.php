<?php

declare(strict_types=1);

/**
 *  PTAdmin
 *  ============================================================================
 *  版权所有 2022-2024 重庆胖头网络技术有限公司，并保留所有权利。
 *  网站地址: https://www.pangtou.com
 *  ----------------------------------------------------------------------------
 *  尊敬的用户，
 *     感谢您对我们产品的关注与支持。我们希望提醒您，在商业用途中使用我们的产品时，请务必前往官方渠道购买正版授权。
 *  购买正版授权不仅有助于支持我们不断提供更好的产品和服务，更能够确保您在使用过程中不会引起不必要的法律纠纷。
 *  正版授权是保障您合法使用产品的最佳方式，也有助于维护您的权益和公司的声誉。我们一直致力于为客户提供高质量的解决方案，并通过正版授权机制确保产品的可靠性和安全性。
 *  如果您有任何疑问或需要帮助，我们的客户服务团队将随时为您提供支持。感谢您的理解与合作。
 *  诚挚问候，
 *  【重庆胖头网络技术有限公司】
 *  ============================================================================
 *  Author:    Zane
 *  Homepage:  https://www.pangtou.com
 *  Email:     vip@pangtou.com
 */

namespace PTAdmin\Easy\Service;

use Illuminate\Support\Arr;
use PTAdmin\Easy\Model\ModelBuild;
use PTAdmin\Easy\Service\Traits\ModSearch;

class Handler extends AbstractCore
{
    use ModSearch;

    /**
     * 获取列表数据.
     *
     * @param array $data  搜索数据
     * @param array $allow 搜索规则信息, 参考模型规则的写法
     * @param array $order 排序方式['field' => 'desc', 'field1' => 'asc']
     *
     * @return mixed
     */
    public function lists(array $data = [], array $allow = [], array $order = [])
    {
        $filterMap = $this->newQuery();

        // 搜索处理
        if (\count($data) > 0) {
            $filterMap = $this->buildQuery($filterMap, $data, $allow);
        }

        // 排序处理
        if (\count($order)) {
            foreach ($order as $key => $val) {
                if (!\in_array($val, ['asc', 'desc'], true)) {
                    continue;
                }
                $filterMap->orderBy($key, $val);
            }
        }

        $data = $filterMap->paginate()->toArray();
        if (\count($data['data']) > 0) {
            $data['data'] = $this->actionsFormat($data['data']);
        }

        return $data;
    }

    /**
     * 存储数据.
     *
     * @param array $data       保存的数据
     * @param bool  $isValidate 是否执行数据验证
     *
     * @throws \Illuminate\Validation\ValidationException
     *
     * @return ModelBuild
     */
    public function store(array $data, bool $isValidate = true): ModelBuild
    {
        if ($isValidate) {
            $this->validate($data);
        }
        // 1、参数过滤，只展示存储需要的字段
        // 2、参数转换，需要对特殊格式的参数进行转换
        $model = ModelBuild::build($this->code);
        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * @param mixed $data
     * @param mixed $id
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function edit($data, $id, bool $isValidate = true): int
    {
        if ($isValidate) {
            $this->validate($data);
        }

        $model = $this->newQuery();

        return $model->where('id', $id)->update($data);
    }

    public function delete($ids): void
    {
        $this->newQuery()->whereIn('id', Arr::wrap($ids))->delete();
    }

    public function show($id)
    {
        return $this->actionFormat(
            $this->newQuery()->findOrFail($id)
        );
    }

    /**
     * 返回查询对象
     */
    public function newQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ModelBuild::build($this->code)->newQuery();
    }
}
