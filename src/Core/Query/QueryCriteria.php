<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Query;

/**
 * 查询条件对象.
 *
 * 对外兼容旧格式 `filter/order`，同时支持更显式的
 * `filters/sorts/page/limit/paginate` DSL 结构。
 */
final class QueryCriteria
{
    /** @var QueryFilter[] */
    private $filters;

    /** @var QuerySort[] */
    private $sorts;

    /** @var int|null */
    private $limit;

    /** @var int */
    private $page;

    /** @var bool */
    private $paginate;

    /** @var string|null */
    private $keyword;

    /** @var string[] */
    private $keywordFields;

    /** @var string[] */
    private $groups;

    /** @var QueryAggregate[] */
    private $aggregates;

    /**
     * @param QueryFilter[]    $filters
     * @param QuerySort[]      $sorts
     * @param string[]         $keywordFields
     * @param string[]         $groups
     * @param QueryAggregate[] $aggregates
     */
    public function __construct(
        array $filters = [],
        array $sorts = [],
        ?int $limit = null,
        int $page = 1,
        bool $paginate = false,
        ?string $keyword = null,
        array $keywordFields = [],
        array $groups = [],
        array $aggregates = []
    )
    {
        $this->filters = $filters;
        $this->sorts = $sorts;
        $this->limit = $limit;
        $this->page = max($page, 1);
        $this->paginate = $paginate;
        $this->keyword = null !== $keyword && '' !== trim($keyword) ? trim($keyword) : null;
        $this->keywordFields = $keywordFields;
        $this->groups = $groups;
        $this->aggregates = $aggregates;
    }

    public static function fromArray(array $query = []): self
    {
        $filters = [];
        $sorts = [];

        foreach ($query['filters'] ?? [] as $filter) {
            if (!\is_array($filter) || !isset($filter['field'])) {
                continue;
            }
            $filters[] = new QueryFilter(
                (string) $filter['field'],
                (string) ($filter['operator'] ?? '='),
                $filter['value'] ?? null
            );
        }

        foreach ($query['filter'] ?? [] as $field => $value) {
            $operator = \is_array($value) ? 'in' : '=';
            $filters[] = new QueryFilter((string) $field, $operator, $value);
        }

        foreach ($query['sorts'] ?? [] as $sort) {
            if (!\is_array($sort) || !isset($sort['field'])) {
                continue;
            }
            $sorts[] = new QuerySort((string) $sort['field'], (string) ($sort['direction'] ?? 'asc'));
        }

        foreach ($query['order'] ?? [] as $field => $direction) {
            $sorts[] = new QuerySort((string) $field, (string) $direction);
        }

        $keyword = null;
        if (isset($query['keyword']) && \is_scalar($query['keyword'])) {
            $keyword = (string) $query['keyword'];
        } elseif (isset($query['search']) && \is_scalar($query['search'])) {
            $keyword = (string) $query['search'];
        }

        $keywordFields = [];
        foreach ($query['keyword_fields'] ?? ($query['search_fields'] ?? []) as $field) {
            if (\is_string($field) && '' !== $field) {
                $keywordFields[] = $field;
            }
        }

        $groups = [];
        $groupInput = $query['groups'] ?? ($query['group_by'] ?? ($query['group'] ?? []));
        foreach (\is_array($groupInput) ? $groupInput : [$groupInput] as $field) {
            if (\is_string($field) && '' !== $field) {
                $groups[] = $field;
            }
        }

        $aggregates = [];
        foreach ($query['aggregates'] ?? ($query['metrics'] ?? []) as $aggregate) {
            if (!\is_array($aggregate) || !isset($aggregate['type'])) {
                continue;
            }

            $field = $aggregate['field'] ?? ($aggregate['name'] ?? null);
            $field = \is_string($field) && '' !== $field ? $field : null;
            $alias = $aggregate['as'] ?? ($aggregate['alias'] ?? null);
            $aggregates[] = new QueryAggregate((string) $aggregate['type'], $field, \is_string($alias) ? $alias : null);
        }

        $limit = isset($query['limit']) ? max((int) $query['limit'], 0) : null;
        $page = isset($query['page']) ? (int) $query['page'] : 1;
        $paginate = true === (bool) ($query['paginate'] ?? false);

        return new self($filters, $sorts, $limit, $page, $paginate, $keyword, $keywordFields, $groups, $aggregates);
    }

    /**
     * @return QueryFilter[]
     */
    public function filters(): array
    {
        return $this->filters;
    }

    /**
     * @return QuerySort[]
     */
    public function sorts(): array
    {
        return $this->sorts;
    }

    /**
     * 返回每页/最大条数限制.
     */
    public function limit(): ?int
    {
        return $this->limit;
    }

    /**
     * 返回页码，从 1 开始.
     */
    public function page(): int
    {
        return $this->page;
    }

    /**
     * 是否启用分页输出.
     */
    public function paginate(): bool
    {
        return $this->paginate;
    }

    /**
     * 返回关键字检索文本.
     */
    public function keyword(): ?string
    {
        return $this->keyword;
    }

    /**
     * @return string[]
     */
    public function keywordFields(): array
    {
        return $this->keywordFields;
    }

    /**
     * @return string[]
     */
    public function groups(): array
    {
        return $this->groups;
    }

    /**
     * @return QueryAggregate[]
     */
    public function aggregates(): array
    {
        return $this->aggregates;
    }
}
