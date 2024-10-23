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

namespace PTAdmin\Easy\Components;

use PTAdmin\Easy\Components\Attachment\Attachment;
use PTAdmin\Easy\Components\Attachment\Image;
use PTAdmin\Easy\Components\Attachment\Video;
use PTAdmin\Easy\Components\Number\Connect;
use PTAdmin\Easy\Components\Number\Number;
use PTAdmin\Easy\Components\Select\Checkbox;
use PTAdmin\Easy\Components\Select\Radio;
use PTAdmin\Easy\Components\Select\Select;
use PTAdmin\Easy\Components\Select\SelectMultiple;
use PTAdmin\Easy\Components\Select\Switches;
use PTAdmin\Easy\Components\Text\Color;
use PTAdmin\Easy\Components\Text\Editor;
use PTAdmin\Easy\Components\Text\Email;
use PTAdmin\Easy\Components\Text\Hidden;
use PTAdmin\Easy\Components\Text\Identity;
use PTAdmin\Easy\Components\Text\Link;
use PTAdmin\Easy\Components\Text\Password;
use PTAdmin\Easy\Components\Text\Phone;
use PTAdmin\Easy\Components\Text\Text;
use PTAdmin\Easy\Components\Text\Textarea;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Model\ModField;

/**
 * 组件管理器.
 */
class ComponentManager
{
    /** @var string[][] 组件map */
    private static $COMPONENTS = [
        // 文本组件
        'text' => ['class' => Text::class, 'label' => '单行文本', 'label_key' => 'component.text'],
        'hidden' => ['class' => Hidden::class, 'label' => '隐藏域', 'label_key' => 'component.hidden'],
        'password' => ['class' => Password::class, 'label' => '密码框', 'label_key' => 'component.password'],
        'textarea' => ['class' => Textarea::class, 'label' => '多行文本', 'label_key' => 'component.textarea'],
        'email' => ['class' => Email::class, 'label' => '邮箱', 'label_key' => 'component.email'],
        'phone' => ['class' => Phone::class, 'label' => '手机', 'label_key' => 'component.phone'],
        'link' => ['class' => Link::class, 'label' => '链接', 'label_key' => 'component.link'],
        'identity' => ['class' => Identity::class, 'label' => '身份证', 'label_key' => 'component.identity'],
        'color' => ['class' => Color::class, 'label' => '色彩', 'label_key' => 'component.color'],
        'editor' => ['class' => Editor::class, 'label' => '编辑器', 'label_key' => 'component.editor'],
        'number' => ['class' => Number::class, 'label' => '数值组件', 'label_key' => 'component.number'],

        // 附件组件
        'attachment' => ['class' => Attachment::class, 'label' => '文件', 'label_key' => 'component.file'],
        'image' => ['class' => Image::class, 'label' => '图片', 'label_key' => 'component.image'],
        'video' => ['class' => Video::class, 'label' => '视频', 'label_key' => 'component.video'],

        // 日期组件
        'date' => ['class' => Text::class, 'label' => '日期', 'label_key' => 'component.date'],
        'datetime' => ['class' => Text::class, 'label' => '日期时间', 'label_key' => 'component.datetime'],
        'time' => ['class' => Text::class, 'label' => '时间', 'label_key' => 'component.time'],
        'year' => ['class' => Text::class, 'label' => '年', 'label_key' => 'component.year'],

        // 枚举组件
        'select' => ['class' => Select::class, 'label' => '下拉框', 'label_key' => 'component.select'],
        'radio' => ['class' => Radio::class, 'label' => '单选框', 'label_key' => 'component.radio'],
        'checkbox' => ['class' => Checkbox::class, 'label' => '多选框', 'label_key' => 'component.checkbox'],
        'switches' => ['class' => Switches::class, 'label' => '开关', 'label_key' => 'component.switch'],
        'selectMultiple' => ['class' => SelectMultiple::class, 'label' => '下拉多选', 'label_key' => 'component.selectMultiple'],

        'connect' => ['class' => Connect::class, 'label' => '关联ID', 'label_key' => 'component.connect'],
        // 树形组件
        // 'selectTree' => ['class' => Text::class, 'label' => '下拉树', 'label_key' => 'component.selectTree'],
        // 'treeSelect' => ['class' => Text::class, 'label' => '树形下拉框', 'label_key' => 'component.treeSelect'],
        // 'cascader' => ['class' => Text::class, 'label' => '级联', 'label_key' => 'component.cascader'],
        // 'markdown' => ['class' => Text::class, 'label' => 'Markdown', 'label_key' => 'component.markdown'],
        // 'table' => ['class' => Text::class, 'label' => '表格', 'label_key' => 'component.table'],
        // 'tree' => ['class' => Text::class, 'label' => '树形', 'label_key' => 'component.tree'],
    ];

    /**
     * 构建组件对象.
     *
     * @param string         $type
     * @param array|ModField $data
     *
     * @return IComponent
     */
    public static function build(string $type, $data = null): IComponent
    {
        $component = self::getComponent($type);
        if (0 === \count($component)) {
            throw new EasyException("【{$type}】数据类型未定义");
        }
        $class = $component['class'] ?? '';

        try {
            return new $class($data);
        } catch (\Exception $exception) {
            throw new EasyException("【{$type}】实例化失败：{$exception->getMessage()}");
        }
    }

    /**
     * 扩展新的组件.
     *
     * @param string $type
     * @param array  $component
     */
    public static function insertComponent(string $type, array $component): void
    {
        if (isset(self::$COMPONENTS[$type])) {
            throw new EasyException("【{$type}】组件已存在");
        }
        (new self())->checkInstallComponent($component);
        self::$COMPONENTS[$type] = $component;
    }

    /**
     * 扩展组件，可覆盖原组件对象
     *
     * @param string $type
     * @param array  $component
     */
    public static function setComponent(string $type, array $component): void
    {
        (new self())->checkInstallComponent($component);
        self::$COMPONENTS[$type] = $component;
    }

    /**
     * 获取组件对象.
     *
     * @param string $type
     *
     * @return array|string[]
     */
    public static function getComponent(string $type): array
    {
        return self::$COMPONENTS[$type] ?? [];
    }

    /**
     * 获取支持的组件类型类型.
     *
     * @param string[] $allow 允许返回的组件类型
     * @param string[] $deny  需要排除的组件类型
     *
     * @return array
     */
    public static function getComponentOptions(array $allow = [], array $deny = []): array
    {
        $results = [];
        foreach (self::$COMPONENTS as $key => $val) {
            if (\count($allow) > 0 && !\in_array($key, $allow, true)) {
                continue;
            }
            if (\count($deny) > 0 && \in_array($key, $deny, true)) {
                continue;
            }
            $results[] = ['label' => $val['label'], 'label_key' => $val['label_key'], 'value' => $key];
        }

        return $results;
    }

    /**
     * 获取枚举类型组件.
     *
     * @return array
     */
    public static function getComponentEnumOptions(): array
    {
        $allow = ['checkbox', 'radio', 'select', 'selectMultiple', 'switches'];

        return self::getComponentOptions($allow);
    }

    /**
     * 获取文本类型组件.
     *
     * @return array
     */
    public static function getComponentTextOptions(): array
    {
        $allow = ['text', 'textarea', 'password', 'number', 'email', 'url', 'color', 'identity'];

        return self::getComponentOptions($allow);
    }

    private function checkInstallComponent($component): void
    {
        if (!isset($component['class']) || (!isset($component['label']) && !isset($component['label_key']))) {
            throw new EasyException('组件配置错误');
        }
        if (!class_exists($component['class'])) {
            throw new EasyException('组件类型不可使用');
        }
        $com = new $component['class']();
        if (!$com instanceof IComponent) {
            throw new EasyException('组件必须实现IComponent接口');
        }
    }
}
