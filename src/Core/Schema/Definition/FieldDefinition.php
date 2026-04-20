<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Definition;

use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Core\Support\SensitiveFieldHelper;

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
     * 返回字段帮助说明.
     *
     * @return mixed
     */
    public function help()
    {
        $help = $this->field->getMetadata('help');
        if (null !== $help) {
            return $help;
        }

        $comment = trim($this->comment());

        return '' === $comment ? null : $comment;
    }

    public function col()
    {
        return $this->field->getMetadata('col');
    }

    public function control()
    {
        $control = $this->field->getMetadata('control');

        return \is_array($control) ? $control : null;
    }

    public function formItem()
    {
        $formItem = $this->field->getMetadata('formItem');

        return \is_array($formItem) ? $formItem : null;
    }

    public function layoutConfig()
    {
        $layout = $this->field->getMetadata('layout');

        return \is_array($layout) ? $layout : null;
    }

    public function maxLength(): ?int
    {
        $value = $this->field->getMetadata('maxlength', $this->field->getMetadata('length'));

        return \is_numeric($value) ? (int) $value : null;
    }

    public function minValue()
    {
        $value = $this->field->getMetadata('min', $this->field->getMetadata('extends.min'));

        return \is_numeric($value) ? 0 + $value : $value;
    }

    public function maxValue()
    {
        $value = $this->field->getMetadata('max', $this->field->getMetadata('extends.max'));

        return \is_numeric($value) ? 0 + $value : $value;
    }

    public function namespace(): ?string
    {
        $value = $this->field->getMetadata('namespace');

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function slots(): ?array
    {
        $slots = $this->field->getMetadata('slots');

        return \is_array($slots) ? $slots : null;
    }

    /**
     * @return mixed
     */
    public function generator()
    {
        return $this->field->getMetadata('generator');
    }

    public function secret(): bool
    {
        return SensitiveFieldHelper::isSecret($this->metadata());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function maskConfig(): ?array
    {
        return SensitiveFieldHelper::maskConfigFromMetadata($this->metadata());
    }

    public function show(): ?bool
    {
        $value = $this->field->getMetadata('show');
        if (null === $value) {
            return null;
        }

        return true === (bool) $value;
    }

    /**
     * @return mixed
     */
    public function operator()
    {
        $operator = $this->field->getMetadata('operator');
        if (\is_array($operator)) {
            return $operator;
        }

        if (\is_bool($operator) || null === $operator) {
            return $operator;
        }

        return null;
    }

    public function switchProps(): array
    {
        $props = [];
        foreach (['active-text', 'inactive-text', 'active-value', 'inactive-value'] as $key) {
            if ($this->field->getMetadata($key) !== null) {
                $props[$key] = $this->field->getMetadata($key);
            }
        }

        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    public function uploadProps(): array
    {
        if (!\in_array($this->type(), ['image', 'attachment'], true)) {
            return [];
        }

        $props = [];
        foreach ([
            'accept',
            'ext',
            'mime',
            'limit',
            'action',
            'headers',
            'data',
            'method',
            'withCredentials',
            'enableAlt',
            'maxSize',
            'minSize',
            'maxHeight',
            'maxWidth',
            'minHeight',
            'minWidth',
        ] as $key) {
            if ($this->field->getMetadata($key) !== null) {
                $props[$key] = $this->field->getMetadata($key);
            }
        }

        return $props;
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
        return array_filter([
            'name' => $this->name(),
            'type' => $this->type(),
            'label' => $this->label(),
            'placeholder' => $this->placeholder(),
            'defaultValue' => $this->defaultValue(),
            'help' => $this->help(),
            'col' => $this->col(),
            'control' => $this->control(),
            'formItem' => $this->formItem(),
            'layout' => $this->layoutConfig(),
            'maxlength' => $this->maxLength(),
            'min' => $this->minValue(),
            'max' => $this->maxValue(),
            'namespace' => $this->namespace(),
            'slots' => $this->slots(),
            'generator' => $this->generator(),
            'show' => $this->show(),
            'operator' => $this->operator(),
            'required' => $this->isRequired(),
            'secret' => $this->secret() ? true : null,
            'mask' => $this->maskConfig(),
            'options' => $this->options(),
            'rules' => $this->rules(),
            'relation' => $this->relation(),
        ] + $this->switchProps() + $this->uploadProps(), static function ($value): bool {
            return null !== $value;
        });
    }
}
