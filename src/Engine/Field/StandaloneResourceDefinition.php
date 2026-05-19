<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Engine\Field;

use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Engine\Model\Document;

final class StandaloneResourceDefinition implements IResource
{
    /** @var array<string, mixed> */
    private $schema;

    /** @var array<string, IResourceField> */
    private $fields = [];

    /**
     * @param array<string, mixed> $schema
     * @param array<string, IResourceField> $fields
     */
    public function __construct(array $schema, array $fields)
    {
        $this->schema = $schema;
        $this->fields = $fields;
    }

    public function getTable(): string
    {
        return '';
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function getFillable(): array
    {
        return array_keys($this->fields);
    }

    public function getRawTable(): string
    {
        return '';
    }

    public function getRules($id): array
    {
        return [];
    }

    public function getComment()
    {
        return $this->schema['title'] ?? $this->schema['name'] ?? '';
    }

    public function getPrimaryKey(): string
    {
        return 'id';
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getField(string $name): ?IResourceField
    {
        return $this->fields[$name] ?? null;
    }

    public function document(): Document
    {
        throw new \RuntimeException('Standalone field resource does not support document operations.');
    }

    public function getControl()
    {
        return null;
    }

    public function getRelations()
    {
        return [];
    }

    public function getAppends()
    {
        return [];
    }

    public function allowImport(): bool
    {
        return false;
    }

    public function allowExport(): bool
    {
        return false;
    }

    public function allowCopy(): bool
    {
        return false;
    }

    public function allowRecycle(): bool
    {
        return false;
    }

    public function trackChanges(): bool
    {
        return false;
    }

    public function getAppendsValue($model): array
    {
        return [];
    }

    public function toArray(): array
    {
        return $this->schema;
    }
}
