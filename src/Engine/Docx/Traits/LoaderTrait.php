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
use PTAdmin\Easy\Engine\Docx\DocxNameParser;
use PTAdmin\Easy\Exceptions\EasyException;

trait LoaderTrait
{
    /** @var array metadata 原始数据 */
    protected $metadata = [];
    /** @var DocxNameParser */
    protected $parser;

    public function loader($force = false)
    {
        if (!$force && !Easy::isDevelop()) {
            $this->loadThroughCache($this->getDocxName());

            if (\is_array($this->metadata) && \count($this->metadata) > 0) {
                return $this;
            }
        }

        try {
            return $this->loadThroughFile($this->getDocxJsonPath());
        } catch (EasyException $e) {
        }

        return $this->loadThroughDocx($this->getDocxName());
    }

    /**
     * 通过文件加载.
     *
     * @param string $filepath
     *
     * @return LoaderTrait|\PTAdmin\Easy\Engine\Schema\Schema
     */
    public function loadThroughFile(string $filepath): self
    {
        if (!is_readable($filepath) || !is_file($filepath)) {
            throw new EasyException("无效或无权限的[{$filepath}]文件");
        }
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
     * 查询文档数据表，获取数据表结构信息.
     *
     * @param string $docx
     *
     * @return LoaderTrait|\PTAdmin\Easy\Engine\Schema\Schema
     */
    public function loadThroughDocx(string $docx): self
    {
        return $this;
    }

    /**
     * 通过元数据加载文档.
     *
     * @param array $metadata
     *
     * @return LoaderTrait|\PTAdmin\Easy\Engine\Schema\Schema
     */
    public function loadThroughMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * 查询缓存数据.
     *
     * @param string $docx
     *
     * @return LoaderTrait|\PTAdmin\Easy\Engine\Schema\Schema
     */
    public function loadThroughCache(string $docx): self
    {
        return $this;
    }
}
