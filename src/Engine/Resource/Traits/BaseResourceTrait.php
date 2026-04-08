<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource\Traits;

use Illuminate\Support\Str;
use PTAdmin\Easy\Engine\Resource\ResourceNamespace;

trait BaseResourceTrait
{
    protected $resource;
    protected $name;
    protected $module;
    protected $path;
    protected $base_path;
    protected $base_namespace;

    /**
     * 解析资源名称、模块和文件路径信息.
     */
    public function parser(string $resource, string $module = ''): void
    {
        $this->resource = $resource;
        $this->module = $module;
        $resource = $this->parserName();
        if (Str::contains($resource, '.')) {
            $resource = explode('.', $resource);
            $this->name = array_pop($resource);
        } else {
            $this->name = $resource;
        }

        $this->normalizePath($resource);
    }

    /**
     * 返回当前资源名称.
     */
    public function getResourceName(): string
    {
        return $this->name;
    }

    public function getResourceRootPath($file = ''): string
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

    public function getResourceJsonPath(): string
    {
        return $this->getResourceRootPath($this->name.'.json');
    }

    public function getResourceJsPath(): string
    {
        return $this->getResourceRootPath($this->name.'.js');
    }

    public function getResourceControllerPath(): string
    {
        return $this->getResourceRootPath(Str::studly($this->name).'Controller.php');
    }

    public function getControl(): ?string
    {
        if (!file_exists($this->getResourceControllerPath())) {
            return null;
        }

        $namespace = $this->base_namespace;
        if (null !== $this->path) {
            $namespace = $namespace.'\\'.$this->path;
        }

        return $namespace.'\\'.Str::studly($this->name).'Controller';
    }

    private function normalizePath($path): void
    {
        if (\is_array($path) && \count($path) > 0) {
            $this->path = implode(\DIRECTORY_SEPARATOR, array_map(static function ($item) {
                return $item;
            }, $path));
        }

        $path = [];
        switch ($this->module) {
            case '':
            case null:
            case ResourceNamespace::NORM_NAMESPACE:
                $this->module = ResourceNamespace::NORM_NAMESPACE;
                $this->base_namespace = 'App\\Docx';
                $path[] = 'app';
                $path[] = 'Docx';

                break;

            case ResourceNamespace::INTERNAL_NAMESPACE:
                $this->base_path = \dirname(__DIR__, 3).\DIRECTORY_SEPARATOR.'Docx';
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

    private function parserName()
    {
        $resource = $this->resource;
        if (Str::contains($resource, '::')) {
            $name = explode('::', $resource);
            $this->module = null !== $this->module && '' !== $this->module ? $this->module : $name[0];
            $resource = $name[1];
        }

        return $resource;
    }
}
