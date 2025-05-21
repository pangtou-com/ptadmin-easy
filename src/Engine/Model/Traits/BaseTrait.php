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

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PTAdmin\Easy\Engine\Model\FormDTO;
use PTAdmin\Easy\Exceptions\EasyException;

trait BaseTrait
{
    /** @var bool 是否需要校验 */
    protected $validate = true;

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
        return $this->newModel()->newQuery()->find($id);
    }

    /**
     * 直接更新数据.不会记录跟踪数据更新情况.
     *
     * @param array $data
     */
    public function update(array $data): void
    {
        if (null === $this->query) {
            throw new EasyException('数据不存在');
        }
        $this->query->update($data);
    }

    /**
     * 更新数据同时会根据需求更新跟踪数据的更新情况.
     *
     * @param array $data
     */
    public function edit(array $data): void
    {
        $model = $this->getModelForActiveFirst();
        if (null === $model) {
            throw new EasyException('数据不存在');
        }
        $data = $this->editValidate($data, $model);

        try {
            DB::transaction(function () use ($data, $model): void {
                $model->update($data);
            });
        } catch (\Exception $exception) {
            throw new EasyException($exception->getMessage());
        }
        // 记录跟踪
        $this->track($model);
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
        return $this->model = $this->query()->limit($limit)->get();
    }

    /**
     * 启用数据验证
     *
     * @return BaseTrait|\PTAdmin\Easy\Engine\Model\Document
     */
    public function enableValidate(): self
    {
        $this->validate = true;

        return $this;
    }

    /**
     * 禁用数据验证
     *
     * @return BaseTrait|\PTAdmin\Easy\Engine\Model\Document
     */
    public function disableValidate(): self
    {
        $this->validate = false;

        return $this;
    }

    /**
     * 编辑数据验证
     *
     * @param $data
     * @param $model
     *
     * @return array
     */
    public function editValidate($data, $model): array
    {
        list($rules, $attributes) = $this->docx->getRules($model->getKey());
        foreach ($rules as $key => $rule) {
            if (!isset($data[$key])) {
                unset($rules[$key]);
            }
        }
        $dto = FormDTO::make($data, $model);
        $dto->setAttributes($attributes)->setRules($rules);
        $this->validate($dto);

        return $dto->getData();
    }

    /**
     * 创建数据验证
     *
     * @param $data
     *
     * @return array
     */
    public function createValidate($data): array
    {
        list($rules, $attributes) = $this->docx->getRules();
        $dto = FormDTO::make($data);
        $dto->setAttributes($attributes)->setRules($rules);
        $this->validate($dto);

        return $dto->getData();
    }

    protected function getModelForActiveFirst()
    {
        if (null !== $this->model) {
            if ($this->model instanceof Collection) {
                return $this->model->first();
            }
            if ($this->model->exists) {
                return $this->model;
            }
        }
        if (null !== $this->query) {
            return $this->query->first();
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
        if (!$this->docx()->trackChanges()) {
            return;
        }
    }

    protected function validate(FormDTO $dto): void
    {
        if (!$this->validate) {
            return;
        }
        // 当自定义事件明确返回false时可以将取消验证直接返回
        if (false === $this->trigger('validate', $dto)) {
            return;
        }

        try {
            Validator::make($dto->getData(), $dto->getRules(), $dto->getMessages(), $dto->getAttributes())->validate();
        } catch (ValidationException $e) {
            $msg = $e->validator->getMessageBag()->toArray();
            $msg = collect($msg)->map(function ($item) {
                return implode('|', $item);
            })->toArray();
            $messages = implode('', $msg);

            throw new EasyException($messages, $e->getCode(), $e);
        }
    }
}
