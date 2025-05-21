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

/**
 * 文档关联关系管理.
 */
trait RelationsTrait
{
    /** @var array 关联关系 */
    protected $relations = [];

    /**
     * 获取关联关系.
     *
     * @return array
     */
    public function getRelations(): array
    {
        if (\is_array($this->relations) && \count($this->relations) > 0) {
            return $this->relations;
        }
        foreach ($this->fields as $field) {
            if ($field->isRelation()) {
                $this->relations[$field->getName()] = $field->getRelation();
            }
        }

        return $this->relations;
    }
}
