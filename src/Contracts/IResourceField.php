<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Contracts;

/**
 * 资源字段契约.
 */
interface IResourceField
{
    public function getName(): string;

    public function getType(): string;

    public function getOptions(): array;

    public function isRelation(): bool;

    public function isAppend(): bool;

    public function getAppendName(): string;

    public function getRelation(): array;

    public function getComment(): string;

    public function getLabel(): string;

    public function getRules($id): array;

    public function getComponentAttributeValue($model, $val);

    public function setComponentAttributeValue($model, $val);

    public function getDefault();

    public function required(): ?string;

    public function getMetadata($key = null, $default = null);

    public function exists(): bool;

    public function isVirtual(): bool;

    /**
     * 返回当前字段所属资源定义.
     */
    public function getResource(): IResource;

    public function getComponent(): IComponent;
}
