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

namespace PTAdmin\Easy\Core\Runtime;

use PTAdmin\Easy\Core\Action\ActionRegistry;
use PTAdmin\Easy\Core\Audit\AuditSubscriber;
use PTAdmin\Easy\Core\Hook\HookManager;
use PTAdmin\Easy\Core\Permission\Contracts\PermissionCheckerInterface;
use PTAdmin\Easy\Core\Permission\DefaultPermissionChecker;
use PTAdmin\Easy\Core\Query\QueryEngine;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaCompiler;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Schema\Loader\SchemaLoader;
use PTAdmin\Easy\Core\Scope\ScopeManager;

/**
 * 运行时编排器.
 *
 * 负责将一次资源操作收敛为统一生命周期：
 * schema -> context -> hooks -> action/query -> hooks -> result
 */
class Runtime
{
    /** @var SchemaLoader */
    private $loader;

    /** @var SchemaCompiler */
    private $compiler;

    /** @var QueryEngine */
    private $queryEngine;

    /** @var HookManager */
    private $hooks;

    /** @var ActionRegistry */
    private $actions;

    /** @var PermissionCheckerInterface */
    private $permissions;

    /** @var ScopeManager */
    private $scopes;

    public function __construct(
        ?SchemaLoader $loader = null,
        ?SchemaCompiler $compiler = null,
        ?QueryEngine $queryEngine = null,
        ?HookManager $hooks = null,
        ?ActionRegistry $actions = null,
        ?PermissionCheckerInterface $permissions = null,
        ?ScopeManager $scopes = null
    ) {
        $this->loader = $loader ?? new SchemaLoader();
        $this->compiler = $compiler ?? new SchemaCompiler();
        $this->queryEngine = $queryEngine ?? new QueryEngine();
        $this->hooks = $hooks ?? new HookManager();
        $this->actions = $actions ?? new ActionRegistry();
        $this->permissions = $permissions ?? new DefaultPermissionChecker();
        $this->scopes = $scopes ?? new ScopeManager();
        (new AuditSubscriber())->subscribe($this->hooks);
    }

    /**
     * @param array|string $resource
     */
    public function schema($resource, string $module = ''): SchemaDefinition
    {
        return $this->compiler->compile($this->loader->load($resource, $module));
    }

    /**
     * @param array|string $resource
     */
    public function execute(string $operation, $resource, array $payload = [], string $module = '', ?ExecutionContext $context = null): OperationResult
    {
        $context = $context ?? new ExecutionContext();
        $definition = $this->schema($resource, $module);
        $context->set('scope.manager', $this->scopes);

        $this->permissions->authorize($operation, $definition, $payload, $context);

        $this->hooks->dispatch("before.{$operation}", [$definition, $payload, $context]);
        $this->hooks->dispatch("resource.{$definition->name()}.before.{$operation}", [$definition, $payload, $context]);

        $data = $this->runOperation($operation, $definition, $payload, $context);

        $this->hooks->dispatch("after.{$operation}", [$definition, $payload, $data, $context]);
        $this->hooks->dispatch("resource.{$definition->name()}.after.{$operation}", [$definition, $payload, $data, $context]);

        return new OperationResult($operation, $data);
    }

    /**
     * 返回 hook 管理器，供外部注册扩展点.
     */
    public function hooks(): HookManager
    {
        return $this->hooks;
    }

    /**
     * 返回 Scope 管理器，供外部注册数据范围规则.
     */
    public function scopes(): ScopeManager
    {
        return $this->scopes;
    }

    /**
     * 解析并执行具体操作.
     *
     * @return mixed
     */
    private function runOperation(string $operation, SchemaDefinition $definition, array $payload, ExecutionContext $context)
    {
        if ($this->actions->has($operation)) {
            return $this->actions->get($operation)->execute($definition, $payload, $context);
        }

        switch ($operation) {
            case 'detail':
                return $this->queryEngine->detail($definition, $payload['id'] ?? null, $context);

            case 'list':
                return $this->queryEngine->lists($definition, $payload, $context);
        }

        throw new \InvalidArgumentException("Unsupported operation [{$operation}]");
    }
}
