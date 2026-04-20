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

namespace PTAdmin\Easy\Components;

use PTAdmin\Easy\Components\Lib\AutoComponent;
use PTAdmin\Easy\Components\Lib\BlockComponent;
use PTAdmin\Easy\Components\Lib\DateComponent;
use PTAdmin\Easy\Components\Lib\FileComponent;
use PTAdmin\Easy\Components\Lib\JsonComponent;
use PTAdmin\Easy\Components\Lib\LinkComponent;
use PTAdmin\Easy\Components\Lib\NumberComponent;
use PTAdmin\Easy\Components\Lib\SelectComponent;
use PTAdmin\Easy\Components\Lib\TextComponent;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Exceptions\EasyException;

/**
 * 组件管理器.
 */
class Component
{
    protected $appends = [];
    protected $relation = [];

    /** @var string[][] 组件map */
    private static $COMPONENTS = [
        // 文本组件
        'text' => ['class' => TextComponent::class, 'label' => '文本框', 'group' => 'text'],
        'textarea' => ['class' => TextComponent::class, 'label' => '文本域', 'group' => 'text'],
        'password' => ['class' => TextComponent::class, 'label' => '密码框', 'group' => 'text'],
        'color' => ['class' => TextComponent::class, 'label' => '颜色选择器', 'group' => 'text'],
        'rich_text' => ['class' => TextComponent::class, 'label' => '富文本', 'group' => 'text'],
        'icon_input' => ['class' => TextComponent::class, 'label' => '图标输入', 'group' => 'text'],
        'auto' => ['class' => AutoComponent::class, 'label' => '自动编号', 'group' => 'text'],

        // 数字类型组件
        'number' => ['class' => NumberComponent::class, 'label' => '数字', 'group' => 'number'],

        // 文件类型组件
        'image' => ['class' => FileComponent::class, 'label' => '图片', 'group' => 'file'],
        'attachment' => ['class' => FileComponent::class, 'label' => '附件', 'group' => 'file'],

        // 选项类型组件
        'radio' => ['class' => SelectComponent::class, 'label' => '单选框', 'group' => 'select', 'append' => true, 'relation' => true],
        'checkbox' => ['class' => SelectComponent::class, 'label' => '多选选框', 'group' => 'select', 'append' => true, 'relation' => true],
        'select' => ['class' => SelectComponent::class, 'label' => '下拉选择', 'group' => 'select', 'append' => true, 'relation' => true],
        'select-tree' => ['class' => SelectComponent::class, 'label' => '树选择', 'group' => 'select'],
        'switch' => ['class' => SelectComponent::class, 'label' => '开关', 'group' => 'select', 'append' => true],
        'cascader' => ['class' => JsonComponent::class, 'label' => '级联选择', 'group' => 'select'],

        // 时间类型组件
        'time' => ['class' => TextComponent::class, 'label' => '时间', 'group' => 'date'],
        'time_range' => ['class' => JsonComponent::class, 'label' => '时间区间', 'group' => 'date'],
        'date' => ['class' => DateComponent::class, 'label' => '日期选择', 'group' => 'date'],
        'date_range' => ['class' => JsonComponent::class, 'label' => '日期区间', 'group' => 'date'],
        'datetime' => ['class' => DateComponent::class, 'label' => '时间日期', 'group' => 'date'],
        'datetime_range' => ['class' => JsonComponent::class, 'label' => '日期时间区间', 'group' => 'date'],

        // 交互字段
        'rate' => ['class' => NumberComponent::class, 'label' => '评分', 'group' => 'number'],
        'slider' => ['class' => NumberComponent::class, 'label' => '滑块', 'group' => 'number'],

        // 功能组件
        'block' => ['class' => BlockComponent::class, 'label' => '功能块', 'is_virtual' => true, 'group' => 'func'],
        'link' => ['class' => LinkComponent::class, 'label' => '链接表', 'group' => 'func', 'append' => true, 'relation' => true],
        'json' => ['class' => JsonComponent::class, 'label' => 'Json', 'group' => 'func'],
        'table' => ['class' => JsonComponent::class, 'label' => '表格', 'is_virtual' => true, 'group' => 'func', 'relation' => true],
        'mirror' => ['class' => JsonComponent::class, 'label' => '镜像数据', 'is_virtual' => true, 'group' => 'func'],
        'clone' => ['class' => JsonComponent::class, 'label' => '克隆数据', 'group' => 'func'],
    ];

    /**
     * 构建组件对象.
     *
     * @param IResourceField $field
     *
     * @return IComponent
     */
    public function build(IResourceField $field): IComponent
    {
        $component = self::getComponent($field->getType());
        if (null === $component) {
            throw new EasyException(__('ptadmin-easy::messages.errors.component_not_exists', ['type' => $field->getType()]));
        }
        $component = $component['class'];

        try {
            return new $component($field);
        } catch (\Exception $exception) {
            throw new EasyException(__('ptadmin-easy::messages.errors.component_build_failed', [
                'field' => $field->getName(),
                'type' => $field->getType(),
                'message' => $exception->getMessage(),
            ]));
        }
    }

    /**
     * 扩展新的组件.
     *
     * @param string $type
     * @param string $component
     */
    public function insertComponent(string $type, string $component): void
    {
        if (isset(self::$COMPONENTS[$type])) {
            throw new EasyException(__('ptadmin-easy::messages.errors.component_exists', ['type' => $type]));
        }
        (new self())->checkInstallComponent($component);
        self::$COMPONENTS[$type] = $component;
    }

    /**
     * 扩展组件，可覆盖原组件对象
     *
     * @param string $type
     * @param string $component
     */
    public function setComponent(string $type, string $component): void
    {
        (new self())->checkInstallComponent($component);
        self::$COMPONENTS[$type] = $component;
    }

    /**
     * 获取组件对象.
     *
     * @param string $type
     *
     * @return array
     */
    public function getComponent(string $type): ?array
    {
        $component = $this->getRawComponent($type);
        if (null === $component) {
            return null;
        }

        return $this->translateComponentDefinition($type, $component);
    }

    public function getRawComponent(string $type): ?array
    {
        return self::$COMPONENTS[$type] ?? null;
    }

    public function hasComponent(string $type): bool
    {
        return isset(self::$COMPONENTS[$type]);
    }

    /**
     * 是否支持附件属性组件.
     *
     * @param mixed $type
     *
     * @return bool
     */
    public function isAppend($type): bool
    {
        return true === (bool) (self::$COMPONENTS[$type]['append'] ?? false);
    }

    /**
     * 判断组件是否为虚拟组件.
     *
     * @param string $type
     *
     * @return bool
     */
    public function isVirtual(string $type): bool
    {
        return true === (bool) (self::$COMPONENTS[$type]['is_virtual'] ?? false);
    }

    /**
     * 是否关联关系组件.
     *
     * @param mixed $type
     *
     * @return bool
     */
    public function isRelation($type): bool
    {
        return true === (bool) (self::$COMPONENTS[$type]['relation'] ?? false);
    }

    /**
     * 获取支持的组件类型类型.
     *
     * @param null|string $group 选项分组
     *
     * @return array
     */
    public function getComponentOptions(string $group = null): array
    {
        $results = [];
        foreach (self::$COMPONENTS as $type => $component) {
            $g = $component['group'] ?? '';
            if (null !== $group && $group !== $g) {
                continue;
            }
            $component = $this->translateComponentDefinition($type, $component);
            $component['value'] = $type;
            $results[] = $component;
        }

        return $results;
    }

    /**
     * 获取数字类型的组件.
     *
     * @return array
     */
    public function getNumberOptions(): array
    {
        return $this->getComponentOptions('number');
    }

    /**
     * 文件类组件.
     *
     * @return array
     */
    public function getFileOptions(): array
    {
        return $this->getComponentOptions('file');
    }

    /**
     * 选项类组件.
     *
     * @return array
     */
    public function getOptionsOptions(): array
    {
        return $this->getComponentOptions('select');
    }

    /**
     * 时间日期类组件.
     *
     * @return array
     */
    public function getDateOptions(): array
    {
        return $this->getComponentOptions('date');
    }

    /**
     * 功能类组件.
     *
     * @return array
     */
    public function getFunctionOptions(): array
    {
        return $this->getComponentOptions('func');
    }

    /**
     * 获取文本类型组件.
     *
     * @return array
     */
    public function getTextOptions(): array
    {
        return $this->getComponentOptions('text');
    }

    private function checkInstallComponent($component): void
    {
        if (!isset($component['class']) || (!isset($component['label']) && !isset($component['label_key']))) {
            throw new EasyException(__('ptadmin-easy::messages.errors.component_config_invalid'));
        }
        if (!class_exists($component['class'])) {
            throw new EasyException(__('ptadmin-easy::messages.errors.component_type_invalid'));
        }
    }

    /**
     * @param array<string, mixed> $component
     *
     * @return array<string, mixed>
     */
    private function translateComponentDefinition(string $type, array $component): array
    {
        if (isset($component['label'])) {
            $component['label'] = __('ptadmin-easy::messages.components.'.$type);
        }

        return $component;
    }
}
