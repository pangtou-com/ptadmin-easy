<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Contracts;

use PTAdmin\Easy\Engine\Model\Document;

/**
 * 资源定义契约.
 */
interface IResource
{
    public function getTable(): string;

    public function getAttributes(): array;

    public function getFillable(): array;

    public function getRawTable(): string;

    public function getRules($id): array;

    public function getComment();

    public function getPrimaryKey(): string;

    public function getFields(): array;

    public function getField(string $name): ?IResourceField;

    public function document(): Document;

    public function getControl();

    public function getRelations();

    public function getAppends();

    public function allowImport(): bool;

    public function allowExport(): bool;

    public function allowCopy(): bool;

    public function allowRecycle(): bool;

    public function trackChanges(): bool;

    public function getAppendsValue($model): array;

    public function toArray(): array;
}
