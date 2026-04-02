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

namespace PTAdmin\Easy\Engine\Docx\Traits;

use PTAdmin\Easy\Easy;

trait OptionsTrait
{
    protected $options;

    public function getOptions(): array
    {
        if (null !== $this->options) {
            return $this->options;
        }
        $this->parserOptions();

        return $this->options ?? [];
    }

    /**
     * 当前选项类型是否为多选类型.
     *
     * @return bool
     */
    public function isMultiple(): bool
    {
        if ('checkbox' === $this->getType()) {
            return true;
        }
        if ('select' === $this->getType()) {
            return (bool) $this->getMetadata('extends.multiple');
        }

        return false;
    }

    /**
     * 判断选项是否来源docx.
     *
     * @return bool
     */
    public function isSourceDocx(): bool
    {
        $extends = $this->getMetadata('extends', []);

        return isset($extends['table'], $extends['value'], $extends['type']) && 'docx' === $extends['type'];
    }

    /**
     * 当选项来源为docx时，解析docx文档.
     *
     * @return \PTAdmin\Easy\Contracts\IDocx
     */
    public function getOptionDocx(): ?\PTAdmin\Easy\Contracts\IDocx
    {
        $extends = $this->getMetadata('extends');
        // 是否来源于当前docx，直接返回当前docx
        if ($extends['table'] === $this->docx->getRawTable()) {
            return $this->docx;
        }

        return Easy::docx($extends['table']);
    }

    protected function getOptionRules()
    {
        $options = $this->getOptions();

        return data_get($options, '*.value', []);
    }

    protected function parserOptions(): void
    {
        $options = $this->getMetadata('options');
        if (null === $options) {
            $extends = $this->getMetadata('extends');
            // 当未设置options时，使用extends解析
            $method = $extends['type'].'Parser';
            if (!method_exists($this, $method)) {
                return;
            }
            // @phpstan-ignore-next-line
            $this->{$method}($extends);

            return;
        }
        $this->options = $options;
    }

    /**
     * 解析选项来源配置文件.
     *
     * @param array $extends
     */
    protected function configParser(array $extends): void
    {
        if (!isset($extends['key'])) {
            return;
        }
        $this->options = config($extends['key'], []);
    }

    /**
     * 文本域的解析
     * 通过换行的方式分割选项
     * 1：默认格式，下表从0开始
     * 选项1
     * 选项2.
     * 解析后的选项为：
     * [['label' => "选项1", "value" => 0],['label' => "选项2", "value" => 1]]
     * 2：自定义下标，下表从1开始.
     * a=选项1
     * b=选项2.
     * 解析后的选项为：
     * [['label' => "选项1", "value" => "a"],['label' => "选项2", "value" => "b"]].
     *
     * @param array $extends
     */
    protected function textareaParser(array $extends): void
    {
        $content = $extends['content'] ?? '';
        $res = [];
        foreach (explode("\n", $content) as $key => $datum) {
            if ('' === trim($datum)) {
                continue;
            }
            $temp = explode('=', trim($datum));
            if (1 === \count($temp)) {
                $res[$key] = ['label' => $temp[0], 'value' => $key];

                continue;
            }
            $res[$temp[0]] = ['label' => $temp[1], 'value' => $temp[0]];
        }
        $this->options = array_values($res);
    }

    protected function docxParser(array $extends): void
    {
    }
}
