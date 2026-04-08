<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Runtime;

final class OperationResult
{
    /** @var string */
    private $operation;

    /** @var mixed */
    private $data;

    public function __construct(string $operation, $data = null)
    {
        $this->operation = $operation;
        $this->data = $data;
    }

    public function operation(): string
    {
        return $this->operation;
    }

    public function data()
    {
        return $this->data;
    }
}
