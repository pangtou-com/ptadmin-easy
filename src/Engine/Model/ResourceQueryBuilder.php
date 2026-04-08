<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2026 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

namespace PTAdmin\Easy\Engine\Model;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * 轻量查询包装器.
 *
 * 基于 Laravel Query Builder 封装资源记录的 hydration 逻辑，
 * 让运行时读链在不依赖 Eloquent 的情况下仍保持原有用法。
 */
class ResourceQueryBuilder
{
    /** @var Document */
    private $document;

    /** @var \Illuminate\Database\Query\Builder */
    private $builder;

    public function __construct(Document $document, \Illuminate\Database\Query\Builder $builder)
    {
        $this->document = $document;
        $this->builder = $builder;
    }

    /**
     * 透传其余 Query Builder 方法.
     *
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        $result = \call_user_func_array([$this->builder, $name], $arguments);

        if ($result === $this->builder) {
            return $this;
        }

        return $result;
    }

    /**
     * 返回底层 Query Builder.
     */
    public function toBase(): \Illuminate\Database\Query\Builder
    {
        return $this->builder;
    }

    /**
     * 兼容 Eloquent Builder 的 `getQuery()` 调用习惯.
     */
    public function getQuery(): \Illuminate\Database\Query\Builder
    {
        return $this->builder;
    }

    /**
     * 查询首条记录.
     */
    public function first(): ?ResourceRecord
    {
        $record = $this->document->newRecordFromDatabase((array) ($this->builder->first() ?? []));
        if (null !== $record) {
            $this->document->preloadAppends([$record]);
        }

        return $record;
    }

    /**
     * 查询记录集合.
     *
     * @param string|string[] $columns
     *
     * @return Collection<int, ResourceRecord>
     */
    public function get($columns = ['*']): Collection
    {
        $records = $this->builder->get($columns)->map(function ($row): ResourceRecord {
            return $this->document->newRecordFromDatabase((array) $row);
        });

        $this->document->preloadAppends($records->all());

        return $records;
    }

    /**
     * 查询分页结果，并将 data 集合转换为 ResourceRecord 集合.
     *
     * @param mixed      $perPage
     * @param mixed      $columns
     * @param mixed      $pageName
     * @param null|mixed $page
     */
    public function paginate($perPage = 15, $columns = ['*'], $pageName = 'page', $page = null): LengthAwarePaginator
    {
        $paginator = $this->builder->paginate($perPage, $columns, $pageName, $page);
        $records = $paginator->getCollection()->map(function ($row): ResourceRecord {
            return $this->document->newRecordFromDatabase((array) $row);
        });
        $this->document->preloadAppends($records->all());
        $paginator->setCollection($records);

        return $paginator;
    }
}
