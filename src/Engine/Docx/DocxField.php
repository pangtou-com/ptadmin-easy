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

use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use PTAdmin\Easy\Components\Traits\BaseComponentTrait;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Contracts\IDocxField;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Engine\Docx\Traits\OptionsTrait;

class DocxField implements IDocxField
{
    use BaseComponentTrait;
    use OptionsTrait;

    /** @var string 字段类型 */
    private $type;
    private $name;

    /** @var IComponent 组件 */
    private $component;
    private $metadata;

    /** @var IDocx 字段所属文档对象 */
    private $docx;

    public function __construct($data, $docx)
    {
        $this->metadata = $data;
        $this->docx = $docx;
    }

    /**
     * 返回字段组件对象
     *
     * @return IComponent
     */
    public function getComponent(): IComponent
    {
        if (null !== $this->component) {
            return $this->component;
        }
        $this->component = Easy::component()->build($this);

        return $this->component;
    }

    public function getComment(): string
    {
        return $this->getMetadata('comment', $this->getLabel());
    }

    public function getLabel(): string
    {
        return $this->getMetadata('label');
    }

    public function getRelation(): array
    {
        return $this->getMetadata('extends', []);
    }

    public function isRelation(): bool
    {
        if (!Easy::component()->isRelation($this->getType())) {
            return false;
        }
        // 当类型为选项类型时需要查看选项来源是否来源与文档
        if (\in_array($this->getType(), ['radio', 'checkbox', 'select'], true)) {
            $relation = $this->getRelation();

            return isset($relation['type'], $relation['table']) && 'docx' === $relation['type'];
        }

        return true;
    }

    public function isVirtual(): bool
    {
        return Easy::component()->isVirtual($this->getType());
    }

    public function isAppend(): bool
    {
        return Easy::component()->isAppend($this->getType());
    }

    public function getAppendName(): string
    {
        return $this->getMetadata('extends.append_name', $this->getDefaultAppendName());
    }

    /**
     * @param $model
     *
     * @return mixed
     */
    public function getAppendValue($model)
    {
        /** @phpstan-ignore-next-line */
        $value = $model->{$this->getName()};
        if (null === $value) {
            return null;
        }
        $method = 'getAppend'.ucfirst($this->getType()).'Value';
        if (method_exists($this, $method)) {
            $value = \call_user_func([$this, $method], $model, $value);
        }

        return $value;
    }

    public function getRules($id = 0): array
    {
        if ($this->isVirtual() || 'auto' === $this->getType()) {
            return [];
        }
        $type = ucfirst($this->getType());
        $checkMethod = ['required', 'unique', "get{$type}Rule"];
        $rules = Arr::wrap($this->getMetadata('rules', []));
        foreach ($checkMethod as $method) {
            if (method_exists($this, $method)) {
                $rule = \call_user_func_array([$this, $method], [$id]);
                if (null !== $rule && false !== $rule) {
                    $rules = array_merge($rules, Arr::wrap($rule));
                }
            }
        }

        return $rules;
    }

    public function getComponentAttributeValue($model, $val)
    {
        if ($this->hasGetMutator()) {
            return \call_user_func_array([$this, $this->getGetMutatorMethod()], [$val, $model]);
        }

        return $val;
    }

    public function setComponentAttributeValue($model, $val)
    {
        if ($this->hasSetMutator()) {
            return \call_user_func_array([$this, $this->getSetMutatorMethod()], [$val, $model]);
        }

        return $val;
    }

    public function getDefault()
    {
        return $this->getMetadata('default');
    }

    public function getMetadata($key = null, $default = null)
    {
        return $key ? data_get($this->metadata, $key, $default) : $this->metadata;
    }

    public function getName(): string
    {
        if (null === $this->name) {
            $this->name = $this->getMetadata('name');
        }

        return $this->name;
    }

    /**
     * 用于判断当前文档是否已保存数据表.
     *
     * @return bool
     */
    public function exists(): bool
    {
        $id = (int) $this->getMetadata('id');

        return $id > 0;
    }

    public function getType(): string
    {
        if (null === $this->type) {
            $this->type = $this->getMetadata('type', 'text');
        }

        return $this->type;
    }

    public function getDocx(): IDocx
    {
        return $this->docx;
    }

    /**
     * 是否必填.
     *
     * @return null|string
     */
    public function required(): ?string
    {
        if (1 === (int) $this->getMetadata('is_required', 0)) {
            return 'required';
        }

        return null;
    }

    protected function getAppendRadioValue($model, $value)
    {
        $options = $this->getOptions();
        foreach ($options as $option) {
            if ($option['value'] === $value) {
                return $option['label'];
            }
        }

        return null;
    }

    protected function getAppendCheckboxValue($model, $value): array
    {
        $options = $this->getOptions();
        if (!\is_array($value)) {
            $value = explode(',', $value);
            $value = array_filter($value);
        }
        $result = [];
        foreach ($options as $option) {
            if (\in_array($option['value'], $value, true)) {
                $result[] = $option['label'];
            }
        }

        return $result;
    }

    protected function getAppendSelectValue($model, $value)
    {
        return $this->isMultiple() ? $this->getAppendCheckboxValue($model, $value) : $this->getAppendRadioValue($model, $value);
    }

    /**
     * 默认追加字段名.
     *
     * @return string
     */
    protected function getDefaultAppendName(): string
    {
        return $this->getName().'_text';
    }

    /**
     * 是否唯一
     *
     * @param $id
     *
     * @return null|\Illuminate\Validation\Rules\Unique
     */
    protected function unique($id): ?\Illuminate\Validation\Rules\Unique
    {
        if (1 === (int) $this->getMetadata('is_unique')) {
            $rule = Rule::unique($this->docx->getRawTable(), $this->getName());
            if ($this->docx->allowRecycle()) {
                $rule->whereNull('deleted_at');
            }

            return $rule->ignore($id);
        }

        return null;
    }
}
