<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Definition;

use PTAdmin\Easy\Contracts\IResourceField;

/**
 * 字段定义对象.
 *
 * 用于将原始字段元数据与字段能力信息组合到一起。
 */
final class FieldDefinition
{
    /** @var IResourceField */
    private $field;

    /** @var array<string, mixed> */
    private $capabilities;

    /** @var array<string, mixed> */
    private $mapping;

    public function __construct(IResourceField $field, array $capabilities = [], array $mapping = [])
    {
        $this->field = $field;
        $this->capabilities = $capabilities;
        $this->mapping = $mapping;
    }

    /**
     * 返回字段名称.
     */
    public function name(): string
    {
        return $this->field->getName();
    }

    /**
     * 返回字段类型.
     */
    public function type(): string
    {
        return $this->field->getType();
    }

    /**
     * 返回字段标签.
     */
    public function label(): string
    {
        return $this->field->getLabel();
    }

    /**
     * 返回字段默认值.
     */
    public function defaultValue()
    {
        return $this->field->getDefault();
    }

    /**
     * 当前字段是否必填.
     */
    public function isRequired(): bool
    {
        return null !== $this->field->required();
    }

    /**
     * 当前字段是否唯一.
     */
    public function isUnique(): bool
    {
        return 1 === (int) $this->field->getMetadata('is_unique', 0);
    }

    /**
     * 返回字段重命名来源.
     *
     * 用于发布时识别 rename，而不是简单 drop + add。
     */
    public function renameFrom(): ?string
    {
        $renameFrom = $this->field->getMetadata('rename_from');
        if (\is_string($renameFrom) && '' !== $renameFrom) {
            return $renameFrom;
        }

        $renameFrom = $this->field->getMetadata('extends.rename_from');
        if (\is_string($renameFrom) && '' !== $renameFrom) {
            return $renameFrom;
        }

        return null;
    }

    /**
     * 返回底层字段元数据.
     */
    public function metadata(): array
    {
        return (array) $this->field->getMetadata();
    }

    /**
     * 返回字段备注信息.
     */
    public function comment(): string
    {
        return $this->field->getComment();
    }

    /**
     * 返回字段占位提示.
     */
    public function placeholder(): ?string
    {
        $value = $this->field->getMetadata('placeholder');

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * 返回字段原始组件类型.
     */
    public function originType(): ?string
    {
        $value = $this->field->getMetadata('origin_type');

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * 返回字段显示场景配置.
     *
     * @return array<string, mixed>
     */
    public function scenes(): array
    {
        return (array) $this->field->getMetadata('scenes', []);
    }

    /**
     * 返回字段标准化后的显示状态配置.
     *
     * @return array<string, mixed>
     */
    public function display(): array
    {
        return (array) $this->field->getMetadata('display', []);
    }

    /**
     * 返回字段自定义校验提示语.
     *
     * @return array<string, string>
     */
    public function ruleMessages(): array
    {
        return (array) $this->field->getMetadata('rule_messages', []);
    }

    /**
     * 当前字段是否隐藏.
     */
    public function isHidden(): bool
    {
        return true === (bool) $this->field->getMetadata('display.hidden', false);
    }

    /**
     * 当前字段是否只读.
     */
    public function isReadonly(): bool
    {
        return true === (bool) $this->field->getMetadata('display.readonly', false);
    }

    /**
     * 当前字段是否禁用编辑.
     */
    public function isDisabled(): bool
    {
        return true === (bool) $this->field->getMetadata('display.disabled', false);
    }

    /**
     * 当前字段是否允许编辑.
     */
    public function isEditable(): bool
    {
        return true === (bool) $this->field->getMetadata('display.editable', true);
    }

    /**
     * 返回字段选项配置.
     *
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        $options = $this->field->getMetadata('options');
        if (\is_array($options)) {
            return array_values(array_filter($options, 'is_array'));
        }

        $extends = $this->field->getMetadata('extends', []);
        if (!\is_array($extends) || !isset($extends['type'])) {
            return [];
        }

        return array_values(array_filter($this->field->getOptions(), 'is_array'));
    }

    /**
     * 当前字段是否为虚拟字段.
     */
    public function isVirtual(): bool
    {
        return $this->field->isVirtual();
    }

    /**
     * 当前字段是否为追加字段.
     */
    public function isAppend(): bool
    {
        return $this->field->isAppend();
    }

    /**
     * 返回字段关联配置.
     *
     * @return array<string, mixed>
     */
    public function relation(): array
    {
        return (array) $this->field->getRelation();
    }

    /**
     * 返回字段校验规则.
     */
    public function rules($id = 0): array
    {
        return $this->field->getRules($id);
    }

    /**
     * 返回底层原始字段对象.
     */
    public function raw(): IResourceField
    {
        return $this->field;
    }

    /**
     * 返回 registry 推导出的字段能力.
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * 返回字段统一映射描述.
     */
    public function mapping(): array
    {
        return $this->mapping;
    }

    /**
     * 导出字段定义.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name(),
            'type' => $this->type(),
            'origin_type' => $this->originType(),
            'label' => $this->label(),
            'comment' => $this->comment(),
            'placeholder' => $this->placeholder(),
            'default' => $this->defaultValue(),
            'required' => $this->isRequired(),
            'unique' => $this->isUnique(),
            'virtual' => $this->isVirtual(),
            'append' => $this->isAppend(),
            'rename_from' => $this->renameFrom(),
            'options' => $this->options(),
            'rules' => $this->rules(),
            'relation' => $this->relation(),
            'scenes' => $this->scenes(),
            'display' => $this->display(),
            'hidden' => $this->isHidden(),
            'readonly' => $this->isReadonly(),
            'disabled' => $this->isDisabled(),
            'editable' => $this->isEditable(),
            'rule_messages' => $this->ruleMessages(),
            'capabilities' => $this->capabilities(),
            'mapping' => $this->mapping(),
            'metadata' => $this->metadata(),
        ];
    }
}
