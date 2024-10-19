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
use PTAdmin\Easy\Components\ComponentManager;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Model\Mod;
use PTAdmin\Easy\Model\ModField;
use PTAdmin\Easy\Service\Extensions\ModColumnExtension;

class ModFieldService
{
    public function store(array $data): void
    {
        $this->validateData($data);
        /** @var Mod $mod */
        $mod = Mod::query()->findOrFail($data['mod_id']);
        if (TableFieldHandle::existsTableColumn($mod->table_name, $data['name'])) {
            throw new EasyException("【{$data['name']}】字段名已存在");
        }
        if (!TableHandle::tableExists($mod->table_name)) {
            throw new EasyException("模型【{$mod->title}】数据表不存在");
        }

        DB::beginTransaction();

        try {
            $setup = ComponentManager::build($data['type'])->setData($data)->getSetup();
            $filterMap = new ModField();
            $filterMap->fill($data);
            $filterMap->name = strtolower($data['name']);
            $filterMap->type = $data['type'];
            $filterMap->setup = $setup;
            $filterMap->save();

            TableFieldHandle::createField($filterMap)->handle();

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            TableFieldHandle::dropTableColumn($mod->table_name, $data['name']);

            throw new EasyException($exception->getMessage());
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
            $setup = ComponentManager::build($data['type'])->setData($data)->getSetup();
            /** @var ModField $model */
            $model = ModField::query()->findOrFail($id);
            $model->fill($data);
            $model->setup = $setup;
            $model->save();

            TableFieldHandle::editField($model, $data)->handle();
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();

            throw new EasyException($exception->getMessage());
        }

        return $model;
    }

    public static function save($data, $modId): void
    {
        $data['mod_id'] = $modId;
        /** @var ModField $mod */
        $mod = ModField::query()->where('mod_id', $modId)->where('name', $data['name'])->first();
        $mod ? (new self())->edit($data, $mod->id) : (new self())->store($data);
    }

    /**
     * 删除数据.
     *
     * @param $id
     */
    public function delete($id): void
    {
        /** @var ModField $dao */
        $dao = ModField::query()->findOrFail($id);
        if (1 === $dao->is_system) {
            throw new EasyException('系统字段不允许删除');
        }
        $dao->delete();
    }

    /**
     * 彻底删除数据.
     *
     * @param $id
     */
    public function thoroughDel($id): void
    {
        $mod = ModField::withTrashed()->findOrFail($id);
        DB::beginTransaction();
        $tableName = $mod->mod()->first()->table_name;

        try {
            $mod->forceDelete();
            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();

            throw new EasyException($exception->getMessage());
        }
        TableFieldHandle::dropTableColumn($tableName, $mod->name);
    }

    /**
     * 恢复数据.
     *
     * @param $id
     */
    public function restore($id): void
    {
        ModField::withTrashed()->where('id', $id)->restore();
    }

    /**
     * @param mixed $search
     * @param mixed $modId
     *
     * @return array
     */
    public function lists(array $search, $modId): array
    {
        $filterMap = ModField::query();
        $filterMap->where('mod_id', (int) $modId);

        if (isset($search['recycle']) && 1 === (int) $search['recycle']) {
            $filterMap = $filterMap->onlyTrashed();
        }

        return collect($filterMap->orderByDesc('weight')->orderByDesc('id')->paginate())->toArray();
    }

    /**
     * @param $id
     *
     * @return null|\Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
     */
    public function find($id)
    {
        return ModField::query()->findOrFail($id);
    }

    /**
     * 设置状态
     *
     * @param $id
     * @param $status
     */
    public function setStatus($id, $status): void
    {
        /** @var ModField $mod */
        $mod = ModField::query()->findOrFail($id);
        $mod->status = (int) $status;
        $mod->save();
    }

    /**
     * 批量新增字段内容.
     *
     * @param $data
     * @param $id
     */
    public function saves($data, $id): void
    {
    }

    public function extend(): self
    {
        return $this;
    }

    /**
     * 初始化模块字段内容.
     *
     * @param mixed $modName
     */
    private function initialize($modName): void
    {
        $extension = ModColumnExtension::getExtension($modName);
    }

    /**
     * 验证请求数据是否正确.
     */
    private function validateData(array $data): void
    {
        $rules = [
            'title' => 'required|max:255',
            'name' => [
                'required', 'max:32', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
                Rule::unique((new ModField())->getTable(), 'name')->where('mod_id', $data['mod_id'])->ignore($data['id'] ?? 0),
            ],
            'type' => 'required|max:32',
            'mod_id' => 'required|integer',
            'default_val' => 'max:50',
            'intro' => 'max:255',
            'tips' => 'max:255',
            'is_release' => 'integer|min:0|max:255',
            'is_search' => 'integer|min:0|max:255',
            'is_table' => 'integer|min:0|max:255',
            'is_required' => 'integer|min:0|max:255',
            'weight' => 'integer|max:255|min:0',
            'status' => 'integer|max:255|min:0',
        ];
        if (isset($data['id']) && $data['id']) {
            unset($rules['mod_id']);
        }
        Validator::make($data, $rules)->validate();
    }
}
