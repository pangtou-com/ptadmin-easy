<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource\Traits;

use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Exceptions\EasyException;

trait LoaderTrait
{
    protected $metadata = [];

    public function loader($force = false)
    {
        if (!$force && !Easy::isDevelop()) {
            $this->loadThroughResourceCache($this->getResourceName());

            if (\is_array($this->metadata) && \count($this->metadata) > 0) {
                return $this;
            }
        }
        $filepath = $this->getResourceJsonPath();

        if (is_readable($filepath) && is_file($filepath)) {
            try {
                return $this->loadThroughFile($filepath);
            } catch (EasyException $e) {
                throw new EasyException($e->getMessage(), $e->getCode(), $e);
            }
        }

        return $this->loadThroughResource($this->getResourceName());
    }

    public function loadThroughFile(string $filepath): self
    {
        $data = @json_decode(@file_get_contents($filepath), true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new EasyException("文件【{$filepath}】内容错误:".json_last_error_msg());
        }
        $this->metadata = $data;
        if (method_exists($this, 'after_loading')) {
            $this->after_loading($data);
        }

        return $this;
    }

    /**
     * 从资源标识加载 schema.
     */
    public function loadThroughResource(string $resource): self
    {
        return $this;
    }

    public function loadThroughMetadata(array $metadata): self
    {
        if (!isset($metadata['name'], $metadata['module'])) {
            throw new EasyException('Schema name and module are required.');
        }
        $this->resource = $metadata['name'];
        $this->module = $metadata['module'];
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * 从缓存加载资源 schema.
     */
    public function loadThroughResourceCache(string $resource): self
    {
        return $this->loadThroughCache($resource);
    }

    public function loadThroughCache(string $resource): self
    {
        return $this;
    }
}
