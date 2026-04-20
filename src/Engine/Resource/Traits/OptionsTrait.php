<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Resource\Traits;

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;

trait OptionsTrait
{
    protected $options;

    public function getOptions(): array
    {
        if ($this->isSourceResource()) {
            $this->options = null;
        }

        if (null !== $this->options) {
            return $this->options;
        }
        $this->parserOptions();

        return $this->options ?? [];
    }

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
     * 判断选项是否来自其他资源定义.
     */
    public function isSourceResource(): bool
    {
        $extends = $this->getMetadata('extends', []);

        return isset($extends['table'], $extends['value'], $extends['type']) && 'resource' === $extends['type'];
    }

    /**
     * 返回选项来源资源.
     */
    public function getOptionResource(): ?\PTAdmin\Easy\Contracts\IResource
    {
        $extends = $this->getMetadata('extends');
        if (($extends['table'] ?? null) === $this->getResource()->getRawTable()) {
            return $this->getResource();
        }

        return Easy::schema($extends['table'])->raw();
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
            $method = $extends['type'].'Parser';
            if (!method_exists($this, $method)) {
                return;
            }
            $this->{$method}($extends);

            return;
        }
        $this->options = $options;
    }

    protected function configParser(array $extends): void
    {
        if (!isset($extends['key'])) {
            return;
        }
        $this->options = config($extends['key'], []);
    }

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

    protected function resourceParser(array $extends): void
    {
        $table = (string) ($extends['table'] ?? ($extends['name'] ?? ''));
        if ('' === $table) {
            return;
        }

        $resource = $table === $this->getResource()->getRawTable()
            ? $this->getResource()
            : Easy::schema($table)->raw();
        $valueColumn = (string) ($extends['value'] ?? $resource->getPrimaryKey());
        $labelColumn = (string) ($extends['label'] ?? $resource->getTitleField());
        if ('' === $valueColumn || '' === $labelColumn) {
            return;
        }

        $query = DB::table($resource->getRawTable())
            ->select([$valueColumn, $labelColumn]);

        if ($resource->allowRecycle()) {
            $query->whereNull('deleted_at');
        }

        $this->applyOptionResourceFilters($query, (array) ($extends['filter'] ?? []));

        $this->options = $query->get()->map(static function ($row) use ($valueColumn, $labelColumn): array {
            $record = (array) $row;

            return [
                'label' => $record[$labelColumn] ?? null,
                'value' => $record[$valueColumn] ?? null,
            ];
        })->filter(static function (array $option): bool {
            return null !== $option['label'] && null !== $option['value'];
        })->values()->all();
    }

    /**
     * 将关联资源过滤条件应用到选项查询.
     *
     * @param mixed $query
     * @param array<int|string, mixed> $filters
     */
    protected function applyOptionResourceFilters($query, array $filters): void
    {
        foreach ($filters as $key => $filter) {
            if (\is_string($key) && !\is_array($filter)) {
                $query->where($key, $filter);

                continue;
            }

            if (!\is_array($filter)) {
                continue;
            }

            $field = $filter['field'] ?? $filter['name'] ?? null;
            if (!\is_string($field) || '' === $field) {
                continue;
            }

            $operator = strtolower((string) ($filter['operator'] ?? '='));
            $value = $filter['value'] ?? null;
            if ('in' === $operator && \is_array($value)) {
                $query->whereIn($field, $value);

                continue;
            }

            $query->where($field, $operator, $value);
        }
    }
}
