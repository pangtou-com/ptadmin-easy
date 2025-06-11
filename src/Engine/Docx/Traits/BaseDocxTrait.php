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

use Illuminate\Support\Str;
use PTAdmin\Easy\Engine\Docx\Common;

trait BaseDocxTrait
{
    /** @var string 文档原始名称 */
    protected $docx;
    /** @var string 文档名称 */
    protected $name;
    /** @var string 文档所属模块名称 */
    protected $module;
    /** @var string 文档路径 */
    protected $path;
    /** @var string 文档根路径 */
    protected $base_path;
    /** @var string 文档基础命名空间 */
    protected $base_namespace;

    public function parser(string $docx, string $module = '')
    {
        $this->docx = $docx;
        $this->module = $module;
        $docx = $this->parserName();
        if (Str::contains($docx, '.')) {
            $docx = explode('.', $docx);
            $this->name = array_pop($docx);
        } else {
            $this->name = $docx;
        }
        $this->normalizePath($docx);

    }

    /**
     * 返回文档名称.
     *
     * @return string
     */
    public function getDocxName(): string
    {
        return $this->name;
    }

    /**
     * 返回解析后的文档根路径.绝对路径.
     *
     * @param mixed $file
     *
     * @return string
     */
    public function getDocxRootPath($file = ''): string
    {
        $base = $this->base_path;
        if (null !== $this->path) {
            $base = $base.\DIRECTORY_SEPARATOR.$this->path;
        }
        if ('' !== $file) {
            return $base.\DIRECTORY_SEPARATOR.$file;
        }

        return $base;
    }

    public function getDocxJsonPath(): string
    {

        return $this->getDocxRootPath($this->name.'.json');
    }

    public function getDocxJsPath(): string
    {
        return $this->getDocxRootPath($this->name.'.js');
    }

    public function getDocxControllerPath(): string
    {
        return $this->getDocxRootPath(Str::studly($this->name).'Controller.php');
    }

    public function getControl(): ?string
    {
        if (!file_exists($this->getDocxControllerPath())) {
            return null;
        }

        $namespace = $this->base_namespace;
        if (null !== $this->path) {
            $namespace = $namespace.'\\'.$this->path;
        }

        return $namespace.'\\'.Str::studly($this->name).'Controller';
    }

    /**
     * 规范化路径和命名空间.
     *
     * @param $path
     */
    private function normalizePath($path): void
    {
        if (\is_array($path) && \count($path) > 0) {
            $this->path = implode(\DIRECTORY_SEPARATOR, array_map(function ($item) {
                return Str::ucfirst($item);
            }, $path));
        }
        $path = [];
        // 设置完整路径地址和命名空间
        switch ($this->module) {
            case '':
            case null:
            case Common::NORM_NAMESPACE:
                $this->module = Common::NORM_NAMESPACE;
                $this->base_namespace = 'App\\Docx';
                $path[] = 'app';
                $path[] = 'Docx';

                break;

            case Common::INTERNAL_NAMESPACE:
                $this->base_path = \dirname(__DIR__, 2).\DIRECTORY_SEPARATOR.'Docx';
                $this->base_namespace = 'PTAdmin\\Easy\\Docx';

                return;

            default:
                $namespace = Str::ucfirst($this->module);
                $path[] = 'addons';
                $path[] = $namespace;
                $path[] = 'Docx';
                $this->base_namespace = 'PTAdmin\\Addon\\'.$namespace.'\\Docx';

                break;
        }
        $this->base_path = base_path(implode(\DIRECTORY_SEPARATOR, $path));

    }

    /**
     * 解析出文档名称和命名空间
     * 支持在名称中 使用 冒号的方式定义命名空间的情况如： 'demo::docx'.
     */
    private function parserName()
    {
        $docx = $this->docx;
        if (Str::contains($docx, '::')) {
            $name = explode('::', $docx);
            $this->module = null !== $this->module && '' !== $this->module ? $this->module : $name[0];
            $docx = $name[1];
        }

        return $docx;
    }
}
