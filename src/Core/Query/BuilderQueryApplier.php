<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Query;

/**
 * 通用 Builder 查询 DSL 适配器.
 *
 * 让 `filters/sorts/keyword/page/limit/paginate` 这套请求协议
 * 可以直接复用到 Laravel Query Builder / Eloquent Builder。
 */
final class BuilderQueryApplier
{
    /**
     * @param mixed $builder
     * @param array<string, mixed> $query
     * @param array<string, mixed> $options
     */
    public function apply($builder, array $query = [], array $options = []): QueryCriteria
    {
        $criteria = QueryCriteria::fromArray($query);
        $allowedFilters = $this->normalizeFieldSet($options['allowed_filters'] ?? null);
        $allowedSorts = $this->normalizeFieldSet($options['allowed_sorts'] ?? null);

        foreach ($criteria->filters() as $filter) {
            if (!$this->isAllowedField($filter->field(), $allowedFilters)) {
                continue;
            }

            $this->applyFilter($builder, $filter);
        }

        $this->applyKeyword($builder, $criteria, $options);

        foreach ($criteria->sorts() as $sort) {
            if (!$this->isAllowedField($sort->field(), $allowedSorts)) {
                continue;
            }

            $builder->orderBy($sort->field(), $sort->direction());
        }

        $this->applyDefaultOrder($builder, $criteria, $options, $allowedSorts);

        if (!$criteria->paginate() && null !== $criteria->limit() && $criteria->limit() > 0) {
            if ($criteria->page() > 1) {
                $builder->forPage($criteria->page(), $criteria->limit());
            } else {
                $builder->limit($criteria->limit());
            }
        }

        return $criteria;
    }

    /**
     * @param mixed $builder
     * @param array<string, mixed> $query
     * @param array<string, mixed> $options
     *
     * @return mixed
     */
    public function fetch($builder, array $query = [], array $options = [])
    {
        $criteria = $this->apply($builder, $query, $options);

        if ($criteria->paginate()) {
            $perPage = $criteria->limit() ?? (int) ($options['default_limit'] ?? 15);

            return $builder->paginate($perPage, ['*'], 'page', $criteria->page());
        }

        return $builder->get();
    }

    /**
     * @param mixed $builder
     */
    private function applyFilter($builder, QueryFilter $filter): void
    {
        $field = $filter->field();
        $value = $filter->value();

        switch ($filter->operator()) {
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                $builder->where($field, $filter->operator(), $value);

                return;

            case 'like':
                $builder->where($field, 'like', (string) $value);

                return;

            case 'in':
                if (\is_array($value)) {
                    $builder->whereIn($field, $value);
                }

                return;

            case 'between':
                if (\is_array($value) && \count($value) === 2) {
                    $builder->whereBetween($field, array_values($value));
                }

                return;

            case 'contains':
                $items = \is_array($value) ? array_values($value) : [$value];
                $items = array_values(array_filter($items, static function ($item): bool {
                    return \is_scalar($item) && '' !== (string) $item;
                }));
                if (0 === \count($items)) {
                    return;
                }

                $builder->where(function ($query) use ($field, $items): void {
                    foreach ($items as $index => $item) {
                        $method = 0 === $index ? 'where' : 'orWhere';
                        $query->{$method}($field, 'like', '%,'.(string) $item.',%');
                    }
                });

                return;

            case 'null':
                $builder->whereNull($field);

                return;

            case 'not_null':
                $builder->whereNotNull($field);

                return;
        }
    }

    /**
     * @param mixed $builder
     * @param array<string, mixed> $options
     */
    private function applyKeyword($builder, QueryCriteria $criteria, array $options): void
    {
        $keyword = $criteria->keyword();
        if (null === $keyword || '' === $keyword) {
            return;
        }

        $fields = $criteria->keywordFields();
        if (0 === \count($fields)) {
            $fields = array_values(array_filter((array) ($options['keyword_fields'] ?? []), 'is_string'));
        }

        $fields = array_values(array_filter($fields, function (string $field) use ($options): bool {
            return $this->isAllowedField($field, $this->normalizeFieldSet($options['allowed_keyword_fields'] ?? null));
        }));
        if (0 === \count($fields)) {
            return;
        }

        $builder->where(function ($query) use ($fields, $keyword): void {
            foreach ($fields as $index => $field) {
                $method = 0 === $index ? 'where' : 'orWhere';
                $query->{$method}($field, 'like', '%'.$keyword.'%');
            }
        });
    }

    /**
     * @param mixed $builder
     * @param array<string, mixed> $options
     * @param array<string, true>|null $allowedSorts
     */
    private function applyDefaultOrder($builder, QueryCriteria $criteria, array $options, ?array $allowedSorts): void
    {
        if (0 !== \count($criteria->sorts())) {
            return;
        }

        foreach ($this->normalizeDefaultOrder($options['default_order'] ?? []) as $field => $direction) {
            if (!$this->isAllowedField($field, $allowedSorts)) {
                continue;
            }

            $builder->orderBy($field, $direction);
        }
    }

    /**
     * @param mixed $fields
     *
     * @return array<string, true>|null
     */
    private function normalizeFieldSet($fields): ?array
    {
        if (!\is_array($fields) || 0 === \count($fields)) {
            return null;
        }

        $normalized = [];
        foreach ($fields as $field) {
            if (!\is_string($field) || !$this->isSafeField($field)) {
                continue;
            }

            $normalized[$field] = true;
        }

        return 0 === \count($normalized) ? null : $normalized;
    }

    private function isAllowedField(string $field, ?array $allowedFields): bool
    {
        if (!$this->isSafeField($field)) {
            return false;
        }

        return null === $allowedFields || isset($allowedFields[$field]);
    }

    private function isSafeField(string $field): bool
    {
        return 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_\.]*$/', $field);
    }

    /**
     * @param mixed $defaultOrder
     *
     * @return array<string, string>
     */
    private function normalizeDefaultOrder($defaultOrder): array
    {
        if (!\is_array($defaultOrder)) {
            return [];
        }

        $normalized = [];
        foreach ($defaultOrder as $key => $item) {
            if (\is_array($item) && isset($item['field'])) {
                $field = (string) $item['field'];
                if (!$this->isSafeField($field)) {
                    continue;
                }

                $normalized[$field] = 'desc' === strtolower((string) ($item['direction'] ?? 'asc')) ? 'desc' : 'asc';

                continue;
            }

            if (!\is_string($key) || !$this->isSafeField($key)) {
                continue;
            }

            $normalized[$key] = 'desc' === strtolower((string) $item) ? 'desc' : 'asc';
        }

        return $normalized;
    }
}
