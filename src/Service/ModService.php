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

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Model\Mod;
use PTAdmin\Easy\Service\Extensions\ModColumnExtension;

/**
 * 模块的服务层
 */
class ModService
{
    /**
     * 新增模型.
     *
     * @param array  $data     模型数据
     * @param string $mod_name 模型类型
     *
     * @see https://docs.pangtou.com/docs/easy-forms/mod.html
     *
     * @return Mod
     */
    public function store(array $data, string $mod_name): Mod
    {
        $this->validateData(array_merge($data, ['mod_name' => $mod_name]));
        if (TableHandle::tableExists($data['table_name'])) {
            throw new EasyException("【{$data['table_name']}】表名已存在");
        }

        DB::beginTransaction();
        $extension = ModColumnExtension::getExtension($mod_name);
        ModColumnExtension::checkExtension($extension);

        try {
            $model = new Mod();
            $model->fill($data);
            $model->mod_name = $mod_name;
            $model->table_name = $data['table_name'];
            $model->intro = $data['intro'] ?? '';
            $model->save();
            TableHandle::createTable($model->table_name);

            // 初始化模型字段信息
            if (\count($extension) > 0) {
                $field = new ModFieldService();
                foreach ($extension as $item) {
                    $item['mod_id'] = $model->id;
                    $field->store($item);
                }
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            TableHandle::dropTable($data['table_name']);

            throw new EasyException($exception->getMessage());
        }

        return $model;
    }

    /**
     * 批量处理.【待完成】.
     *
     * @param $data
     * @param $mod_name
     */
    public function saves($data, $mod_name): void
    {
        $model = isset($data['id']) ? $this->edit($data, $data['id']) : $this->store($data, $mod_name);
        $results = $data['results'] ?? [];
        $names = data_get($results, '*.name', []);
        $model->field()->get()->each(function ($item) use ($names): void {
            if (!\in_array($item, $names, true)) {
                $item->delete();
            }
        });
        foreach ($results as $result) {
            ModFieldService::save($result, $model->id);
        }
    }

    /**
     * 编辑模型.
     *
     * @param $data
     * @param $id
     *
     * @return null|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function edit($data, $id)
    {
        $this->validateData(array_merge($data, ['id' => $id]));
        DB::beginTransaction();

        try {
            $model = Mod::query()->findOrFail($id);
            $model->fill($data);
            $model->save();
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();

            throw new EasyException($exception->getMessage());
        }

        return $model;
    }

    /**
     * 删除数据.
     *
     * @param $id
     */
    public function delete($id): void
    {
        Mod::query()->findOrFail($id)->delete();
    }

    /**
     * 测定删除数据.
     *
     * @param $id
     */
    public function thoroughDel($id): void
    {
        $mod = Mod::withTrashed()->findOrFail($id);
        DB::beginTransaction();

        try {
            TableHandle::dropTable($mod->table_name);
            $mod->forceDelete();
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();

            throw new EasyException($exception->getMessage());
        }
    }

    /**
     * 恢复数据.
     *
     * @param $id
     */
    public function restore($id): void
    {
        Mod::withTrashed()->where('id', $id)->restore();
    }

    /**
     * @param mixed       $search
     * @param null|string $mod_name 模块类型
     *
     * @return array
     */
    public function lists(array $search = [], string $mod_name = null): array
    {
        $filterMap = Mod::query();
        if (null !== $mod_name) {
            $filterMap->where('mod_name', $mod_name);
        }
        if (isset($search['recycle']) && 1 === (int) $search['recycle']) {
            $filterMap = $filterMap->onlyTrashed();
        }

        return $filterMap->orderByDesc('weight')->orderByDesc('id')->paginate()->toArray();
    }

    /**
     * @param $id
     *
     * @return null|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function find($id)
    {
        return Mod::query()->findOrFail($id);
    }

    /**
     * 通过数据表名称获取模型.
     *
     * @param $table_name
     *
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function byTableName($table_name)
    {
        $filterMap = Mod::query();
        $filterMap->where('table_name', $table_name);

        return $filterMap->firstOrFail();
    }

    /**
     * 通过模块名称获取模型.
     *
     * @param $mod_name
     *
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function byModName($mod_name)
    {
        $filterMap = Mod::query();
        $filterMap->where('mod_name', $mod_name);

        return $filterMap->get();
    }

    /**
     * 新建查询.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Mod::query();
    }

    /**
     * 发布模型.
     *
     * @param mixed $id
     */
    public function publish($id): void
    {
        /** @var Mod $mod */
        $mod = Mod::query()->findOrFail($id);
        $mod->is_publish = 1;
        $mod->status = 1;
        $mod->save();
        // 发布模型时更新缓存
        Handler::make($mod, true);
    }

    /**
     * 撤销发布.
     *
     * @param $id
     */
    public function unPublish($id): void
    {
        /** @var Mod $mod */
        $mod = Mod::query()->findOrFail($id);
        $mod->is_publish = 0;
        $mod->status = 0;
        $mod->save();
    }

    /**
     * 设置状态
     *
     * @param $id
     * @param $status
     */
    public function setStatus($id, $status): void
    {
        /** @var Mod $mod */
        $mod = Mod::query()->findOrFail($id);
        if (1 === (int) $status && 1 !== $mod->is_publish) {
            throw new EasyException('请先发布模型');
        }
        $mod->status = (int) $status;
        $mod->save();
    }

    /**
     * 预览模型.
     *
     * @param $id
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function preview($id): \Illuminate\Contracts\View\View
    {
        $mod = Mod::query()->findOrFail($id);

        return Render::make($mod, true)->toPreviewFormHtml();
    }

    /**
     * 验证请求数据是否正确.
     */
    private function validateData(array $data): void
    {
        $rules = [
            'title' => 'required|max:50',
            'table_name' => [
                isset($data['id']) ?: 'required',
                'max:32', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique((new Mod())->getTable(), 'table_name'),
            ],
            'intro' => 'max:255',
            'mod_name' => isset($data['id']) ? 'max:32' : 'required|max:32',
            'weight' => 'integer|max:255|min:0',
            'status' => 'integer|max:255|min:0',
        ];
        if (isset($data['id']) && $data['id']) {
            unset($rules['table_name'], $rules['mod_name']);
        }

        Validator::make($data, $rules)->validate();
    }
}
