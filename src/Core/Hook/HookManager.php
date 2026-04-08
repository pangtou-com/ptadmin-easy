<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Hook;

class HookManager
{
    /** @var array<string, array<int, callable>> */
    private $listeners = [];

    public function register(string $hook, callable $listener): void
    {
        $this->listeners[$hook][] = $listener;
    }

    /**
     * register 的语义化别名.
     */
    public function on(string $hook, callable $listener): void
    {
        $this->register($hook, $listener);
    }

    public function dispatch(string $hook, array $arguments = []): void
    {
        foreach ($this->listeners[$hook] ?? [] as $listener) {
            \call_user_func_array($listener, $arguments);
        }
    }
}
