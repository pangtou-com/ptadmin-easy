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

namespace PTAdmin\Easy\Engine\Schema;

use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Engine\Docx\Common;
use PTAdmin\Easy\Engine\Docx\Docx;
use PTAdmin\Easy\Engine\Model\Document;
use PTAdmin\Easy\Exceptions\EasyException;

/**
 * 用于数据表的新增处理.
 */
class Schema
{
    use SchemaHandle;
    protected $model;

    public function __construct($docx, string $module = '')
    {
        if ($docx instanceof IDocx) {
            $this->model = $docx;

            return;
        }
        $this->model = Docx::make($docx, $module);
    }

    public function create(): self
    {
        if ($this->tableExists($this->getModel()->getRawTable())) {
            throw new EasyException('数据表已存在');
        }

        try {
            $this->createTable($this->getModel());
        } catch (\Exception $e) {
            $this->dropTable($this->getModel()->getRawTable());

            throw new EasyException($e->getMessage());
        }

        $this->setTableComment($this->getModel()->getTable(), $this->getModel()->getComment());

        return $this;
    }

    public function update(string $docxName = ''): void
    {
        throw new EasyException('暂不支持更新操作');
    }

    /**
     * 重命名数据表名称.
     *
     * @param $name
     */
    public function rename($name): void
    {
        throw new EasyException('暂不支持重命名操作');
    }

    /**
     * 强制创建数据表结构.
     * 当数据表存在时会删除数据表，在重新创建数据表结构.
     */
    public function forceCreate(): self
    {
        $docx = $this->getModel();
        if ($this->tableExists($docx->getRawTable())) {
            $this->dropTable($docx->getRawTable());
        }

        return $this->create();
    }

    public function getModel(): IDocx
    {
        return $this->model;
    }

    /**
     * 存入基础表结构信息.
     */
    public function save(): void
    {
        $document = new Document(Docx::make('docx', Common::INTERNAL_NAMESPACE));

        $document->storeAndSaveMany($this->getModel()->toArray());
    }
}
