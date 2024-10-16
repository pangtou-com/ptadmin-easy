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

use Illuminate\Support\Facades\Validator;
use PTAdmin\Easy\Components\ComponentManager;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Model\Mod;
use PTAdmin\Easy\Service\Traits\ModCache;

abstract class AbstractCore
{
    use ModCache;
    /** @var string 模型名称 */
    protected $code;

    /** @var Mod 模型对象 */
    protected $mod;

    /** @var array 模型字段内容 */
    protected $modField;

    /** @var array 表单数据验证规则信息 */
    protected $formValidateRules;

    /** @var array 表单渲染规则信息 */
    protected $formRenderRules = [];

    /** @var array 搜索表单渲染规则 */
    protected $searchRenderRules = [];

    /** @var array 列表渲染规则信息 */
    protected $tableRenderRules = [];

    /** @var array 字段属性信息 */
    protected $attributes;

    /** @var IComponent[] 模型字段对象 集合 */
    protected $column;

    /**
     * 构造函数.
     * 传入参数支持： Mod对象、模型ID、模型名称.
     *
     * @param Mod|string $param
     */
    private function __construct($param)
    {
        if ($param instanceof Mod) {
            $this->mod = $param;
            $this->code = $param->table_name;
        } elseif (\is_string($param) && 0 !== (int) $param) {
            $this->mod = Mod::query()->findOrFail($param);
            $this->code = $this->mod->table_name;
        } elseif (\is_string($param)) {
            $this->code = $param;
        } else {
            throw new EasyException("【{$param}】参数错误,未知的模型名称");
        }
    }

    /**
     * 初始化构建.
     *
     * @param Mod|string $code  mod对象、模型ID、模型名称
     * @param bool       $force 是否强制更新， 默认为 false
     *
     * @return static
     */
    public static function make($code, bool $force = false): self
    {
        $obj = new static($code);
        if ($force) {
            $obj->updateCache();
        }
        $obj->initialize();

        return $obj;
    }

    /**
     * 获取模型信息.
     */
    public function getModInfo(): array
    {
        return $this->getMod()->toArray();
    }

    /**
     * 获取表单构建规则.
     *
     * @param bool $is_release 是否为用户投稿，当为用户投稿时表单只返回用户投稿相关内容
     *
     * @return array
     */
    public function getFormBuildRender(bool $is_release = false): array
    {
        if (!$is_release) {
            return $this->formRenderRules;
        }
        $data = [];
        foreach ($this->formRenderRules as $field) {
            if ($is_release && 0 === $field['is_release']) {
                continue;
            }
            $data[$field['name']] = $field;
        }

        return $data;
    }

    /**
     * 执行校验.
     *
     * @param array $data
     * @param array $message
     *
     * @throws \Illuminate\Validation\ValidationException
     *
     * @return array
     */
    public function validate(array $data, array $message = []): array
    {
        list($rule, $attributes) = $this->getValidateRules();

        return Validator::make($data, $rule, $message, $attributes)->validate();
    }

    /**
     * 获取验证规则.
     *
     * @return array[]
     */
    public function getValidateRules(): array
    {
        return [$this->formValidateRules, $this->attributes];
    }

    /**
     * 获取当前已启用的模型对象
     *
     * @return Mod
     */
    protected function getMod(): Mod
    {
        if (null === $this->mod) {
            /** @var Mod $mod */
            $mod = Mod::query()->where('table_name', $this->code)->firstOrFail();
            if (0 === $mod->is_publish) {
                throw new EasyException("【{$mod->title}】未发布");
            }
            if (0 === $mod->status) {
                throw new EasyException("【{$mod->title}】已禁用");
            }
            $this->mod = $mod;
        }

        return $this->mod;
    }

    /**
     * 获取模型字段内容.
     *
     * @return array
     */
    protected function getModField(): array
    {
        if (null !== $this->modField) {
            return $this->modField;
        }

        return $this->modField = $this->getMod()->field()->where('status', 1)->get()->toArray();
    }

    /**
     * 初始化规则信息，将模型规则分类解析为：表单、表单验证、搜索等.
     */
    protected function initialize(): void
    {
        $fields = $this->getCacheModField();
        foreach ($fields as $field) {
            $column = $this->getComponent($field['type'], $field['name']);
            $validateRule = $this->parserValidateRule($column, $field);
            $rule = $this->parserFormRenderData($column, $field);
            if (\count($validateRule) > 0) {
                $this->formValidateRules[$field['name']] = $validateRule;
            }
            $this->attributes[$field['name']] = $field['title'];
            $this->formRenderRules[$field['name']] = $rule;

            if (isset($field['is_search'])) {
                $this->searchRenderRules[$field['name']] = $rule;
            }
            if (isset($field['is_table'])) {
                $this->tableRenderRules[$field['name']] = $rule;
            }
        }
    }

    /**
     * 获取组件对象
     *
     * @param $type
     * @param $name
     *
     * @return IComponent
     */
    protected function getComponent($type, $name): IComponent
    {
        if (isset($this->column[$name])) {
            return $this->column[$name];
        }
        $this->column[$name] = ComponentManager::build($type);

        return $this->column[$name];
    }

    /**
     * 解析出验证规则.
     *
     * @param $column
     * @param $field
     *
     * @return array
     */
    protected function parserValidateRule($column, $field): array
    {
        $data = [];
        if ($field['is_required']) {
            $data[] = 'required';
        }
        if ($column->isOption()) {
            $in = data_get($column->getColumnOptions($field['setup']), '*.value');
            $data[] = 'in:'.implode(',', $in);
        }
        if ($column->isNumber()) {
            $data[] = 'integer';
        }
        if (isset($field['setup']['rules'])) {
            $data = array_merge($data, $field['setup']['rules']);
        }

        return $data;
    }

    /**
     * 解析表单渲染数据.
     *
     * @param $column
     * @param $field
     *
     * @return array
     */
    protected function parserFormRenderData($column, $field): array
    {
        return [
            'field' => $field['name'],
            'type' => $field['type'],
            'value' => $field['default_val'],
            'title' => $field['title'],
            'required' => $field['is_required'],
            'release' => $field['is_release'],
            'options' => $column->isOption() ? $column->getColumnOptions($field['setup']) : [],
            'setup' => $field['setup'],
        ];
    }
}
