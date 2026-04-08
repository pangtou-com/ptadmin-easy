<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Action;

use PTAdmin\Easy\Core\Action\Contracts\ActionInterface;
use PTAdmin\Easy\Core\Action\Builtin\CreateAction;
use PTAdmin\Easy\Core\Action\Builtin\DeleteAction;
use PTAdmin\Easy\Core\Action\Builtin\UpdateAction;

/**
 * 行为注册表.
 *
 * 负责维护运行时可执行的写操作处理器。
 */
class ActionRegistry
{
    /** @var array<string, ActionInterface> */
    private $actions = [];

    public function __construct(array $actions = [])
    {
        $actions = 0 === \count($actions) ? [
            new CreateAction(),
            new UpdateAction(),
            new DeleteAction(),
        ] : $actions;

        foreach ($actions as $action) {
            $this->register($action);
        }
    }

    /**
     * 注册一个新的行为处理器.
     */
    public function register(ActionInterface $action): void
    {
        $this->actions[$action->operation()] = $action;
    }

    /**
     * 判断指定操作是否已有处理器.
     */
    public function has(string $operation): bool
    {
        return isset($this->actions[$operation]);
    }

    /**
     * 获取指定操作的处理器.
     */
    public function get(string $operation): ActionInterface
    {
        if (!$this->has($operation)) {
            throw new \InvalidArgumentException("Unsupported action [{$operation}]");
        }

        return $this->actions[$operation];
    }
}
