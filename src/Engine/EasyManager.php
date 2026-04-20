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

namespace PTAdmin\Easy\Engine;

use Illuminate\Support\Str;
use PTAdmin\Easy\Components\Component;
use PTAdmin\Easy\Contracts\IEasyManager;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Action\ActionRegistry;
use PTAdmin\Easy\Core\Hook\HookManager;
use PTAdmin\Easy\Core\Permission\Contracts\PermissionCheckerInterface;
use PTAdmin\Easy\Core\Permission\DefaultPermissionChecker;
use PTAdmin\Easy\Core\Query\BuilderQueryApplier;
use PTAdmin\Easy\Core\Query\QueryEngine;
use PTAdmin\Easy\Core\Runtime\Runtime;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaCompiler;
use PTAdmin\Easy\Core\Schema\Loader\SchemaLoader;
use PTAdmin\Easy\Core\Schema\Versioning\Contracts\SchemaVersionStoreInterface;
use PTAdmin\Easy\Core\Schema\Versioning\Stores\DatabaseSchemaVersionStore;
use PTAdmin\Easy\Core\Scope\ScopeManager;
use PTAdmin\Easy\Core\Support\FieldTypeRegistry;
use PTAdmin\Easy\Engine\Model\Document;
use PTAdmin\Easy\Engine\Resource\ResourceNameParser;
use PTAdmin\Easy\Engine\Schema\Schema;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Handle\ChartHandle;
use PTAdmin\Easy\Handle\DocHandle;
use PTAdmin\Easy\Handle\ReleaseHandle;
use PTAdmin\Easy\Handle\ResourceHandle;
use PTAdmin\Easy\Handle\SchemaHandle;

final class EasyManager implements IEasyManager
{
    /** @var null|FieldTypeRegistry */
    private $fieldTypeRegistry;

    /** @var null|SchemaLoader */
    private $schemaLoader;

    /** @var null|SchemaCompiler */
    private $schemaCompiler;

    /** @var null|QueryEngine */
    private $queryEngine;

    /** @var null|BuilderQueryApplier */
    private $builderQueryApplier;

    /** @var null|ActionRegistry */
    private $actionRegistry;

    /** @var null|Runtime */
    private $runtime;

    /** @var null|SchemaVersionStoreInterface */
    private $versionStore;

    /** @var null|PermissionCheckerInterface */
    private $permissionChecker;

    /** @var null|ScopeManager */
    private $scopeManager;

    /** @var ResourceHandle[] 已解析的内部资源句柄集合. */
    private static $handles = [];

    /** @var DocHandle[] 已解析的运行时句柄集合. */
    private static $docHandles = [];

    /** @var SchemaHandle[] 已解析的 schema 句柄集合. */
    private static $schemaHandles = [];

    /** @var ReleaseHandle[] 已解析的发布句柄集合. */
    private static $releaseHandles = [];

    /** @var IResource[] 已解析的原始资源定义集合. */
    private static $resources = [];

    /**
     * 资源运行时入口.
     *
     * @param array|string $resource
     */
    public function doc($resource, string $module = ''): DocHandle
    {
        $name = $this->resolveHandleName($resource, $module);
        if (isset(self::$docHandles[$name])) {
            return self::$docHandles[$name];
        }

        return self::$docHandles[$name] = new DocHandle($this->resourceHandle($resource, $module));
    }

    /**
     * 获取文档操作管理.
     *
     * @param string $resource
     * @param string $module
     *
     * @return Document
     */
    public function document(string $resource, string $module = ''): Document
    {
        return new Document($this->resolveRawResource($resource, $module));
    }

    /**
     * 返回文档模型.
     *
     * @param string $resource
     * @param string $module
     *
     * @return Model\EasyModel
     */
    public function model(string $resource, string $module = ''): Model\EasyModel
    {
        return (new Document($this->resolveRawResource($resource, $module)))->newModel();
    }

    /**
     * 数据表结构管理.
     *
     * @param mixed  $resource
     * @param string $module
     *
     * @return SchemaHandle
     */
    public function schema($resource, string $module = ''): SchemaHandle
    {
        $name = $this->resolveHandleName($resource, $module);
        if (isset(self::$schemaHandles[$name])) {
            return self::$schemaHandles[$name];
        }

        return self::$schemaHandles[$name] = new SchemaHandle($this->resourceHandle($resource, $module));
    }

    /**
     * 数据表结构管理.
     *
     * @param mixed  $resource
     * @param string $module
     *
     * @return Schema
     */
    public function table($resource, string $module = ''): Schema
    {
        return new Schema($resource, $module);
    }

    /**
     * 资源发布入口.
     *
     * @param array|string $resource
     */
    public function release($resource, string $module = ''): ReleaseHandle
    {
        $name = $this->resolveHandleName($resource, $module);
        if (isset(self::$releaseHandles[$name])) {
            return self::$releaseHandles[$name];
        }

        return self::$releaseHandles[$name] = new ReleaseHandle($this->resourceHandle($resource, $module));
    }

    /**
     * 组件管理.
     *
     * @return Component
     */
    public function component(): Component
    {
        return new Component();
    }

    /**
     * 返回资源图表句柄.
     *
     * @param array|string $resource
     */
    public function charts($resource, string $module = ''): ChartHandle
    {
        return $this->resourceHandle($resource, $module)->charts();
    }

    public function builderQuery(): BuilderQueryApplier
    {
        if (null === $this->builderQueryApplier) {
            $this->builderQueryApplier = new BuilderQueryApplier();
        }

        return $this->builderQueryApplier;
    }

    /**
     * 返回全局 Hook 管理器.
     */
    public function hooks(): HookManager
    {
        return $this->runtime()->hooks();
    }

    /**
     * 返回全局 Scope 管理器.
     */
    public function scopes(): ScopeManager
    {
        return $this->runtime()->scopes();
    }

    /**
     * 返回权限校验器实例.
     */
    public function permissionChecker(): PermissionCheckerInterface
    {
        if (null === $this->permissionChecker) {
            $this->permissionChecker = new DefaultPermissionChecker();
        }

        return $this->permissionChecker;
    }

    /**
     * 返回数据范围管理器实例.
     */
    public function scopeManager(): ScopeManager
    {
        if (null === $this->scopeManager) {
            $this->scopeManager = new ScopeManager();
        }

        return $this->scopeManager;
    }

    /**
     * 资源是否已存在.
     *
     * @param string $resource
     *
     * @return bool
     */
    public function hasResource(string $resource): bool
    {
        if (isset(self::$resources[$resource]) || isset(self::$handles[$resource])) {
            return true;
        }

        return false;
    }

    /**
     * 是否为开发模式.
     *
     * @return bool
     */
    public function isDevelop(): bool
    {
        return true === (bool) config('app.debug');
    }

    /**
     * 兼容多种资源名称写法：
     * 1. 'demo::article' 所属 demo 模块下的资源
     * 2. 'cms.article' app 默认模块下的层级资源
     * 3. 'article' app 默认模块下的资源.
     *
     * @param string $resource
     * @param string $module
     *
     * @return mixed|string
     */
    private function getResourceName(string $resource, string $module = '')
    {
        if (Str::contains($resource, ['.', '::'])) {
            $resource = ResourceNameParser::handle($resource, $module)->getResourceName();
        }

        return $resource;
    }

    /**
     * @param array|string $resource
     */
    private function resolveRawResource($resource, string $module = ''): IResource
    {
        $name = $this->resolveHandleName($resource, $module);

        if (isset(self::$resources[$name])) {
            return self::$resources[$name];
        }

        return self::$resources[$name] = app(IResource::class, ['resource' => $resource, 'module' => $module]);
    }

    /**
     * 返回内部资源聚合句柄.
     *
     * 该句柄仅用于在不同公开入口之间复用内部实现，不再作为对外 API 暴露。
     *
     * @param array|string $resource
     */
    private function resourceHandle($resource, string $module = ''): ResourceHandle
    {
        $name = $this->resolveHandleName($resource, $module);
        if (isset(self::$handles[$name])) {
            return self::$handles[$name];
        }

        return self::$handles[$name] = new ResourceHandle(
            $resource,
            $module,
            $this->runtime(),
            $this->schemaLoader(),
            $this->schemaCompiler(),
            $this->queryEngine(),
            $this->fieldTypeRegistry(),
            $this->actionRegistry(),
            $this->versionStore()
        );
    }

    /**
     * 统一解析资源句柄缓存键.
     *
     * @param array|string $resource
     */
    private function resolveHandleName($resource, string $module = ''): string
    {
        $resolvedModule = $this->resolveHandleModule($resource, $module);

        if (\is_array($resource)) {
            $name = (string) ($resource['name'] ?? '');
            if ('' === $name) {
                throw new EasyException('Schema name is required.');
            }

            return '' === $resolvedModule ? $name : $resolvedModule.'::'.$name;
        }

        $name = (string) $this->getResourceName($resource, $module);

        return '' === $resolvedModule ? $name : $resolvedModule.'::'.$name;
    }

    /**
     * 统一解析资源句柄所属模块.
     *
     * @param array|string $resource
     */
    private function resolveHandleModule($resource, string $module = ''): string
    {
        if (\is_array($resource)) {
            return (string) ($resource['module'] ?? $module);
        }

        return $module;
    }

    private function fieldTypeRegistry(): FieldTypeRegistry
    {
        if (null === $this->fieldTypeRegistry) {
            $this->fieldTypeRegistry = new FieldTypeRegistry();
        }

        return $this->fieldTypeRegistry;
    }

    private function schemaLoader(): SchemaLoader
    {
        if (null === $this->schemaLoader) {
            $this->schemaLoader = new SchemaLoader();
        }

        return $this->schemaLoader;
    }

    private function schemaCompiler(): SchemaCompiler
    {
        if (null === $this->schemaCompiler) {
            $this->schemaCompiler = new SchemaCompiler($this->fieldTypeRegistry());
        }

        return $this->schemaCompiler;
    }

    private function queryEngine(): QueryEngine
    {
        if (null === $this->queryEngine) {
            $this->queryEngine = new QueryEngine();
        }

        return $this->queryEngine;
    }

    private function actionRegistry(): ActionRegistry
    {
        if (null === $this->actionRegistry) {
            $this->actionRegistry = new ActionRegistry();
        }

        return $this->actionRegistry;
    }

    private function runtime(): Runtime
    {
        if (null === $this->runtime) {
            $this->runtime = new Runtime(
                $this->schemaLoader(),
                $this->schemaCompiler(),
                $this->queryEngine(),
                null,
                $this->actionRegistry(),
                $this->permissionChecker(),
                $this->scopeManager()
            );
        }

        return $this->runtime;
    }

    private function versionStore(): SchemaVersionStoreInterface
    {
        if (null === $this->versionStore) {
            $store = config('easy.schema.version.store');
            if (\is_string($store) && class_exists($store)) {
                $instance = app($store);
                if ($instance instanceof SchemaVersionStoreInterface) {
                    $this->versionStore = $instance;

                    return $this->versionStore;
                }
            }
            $this->versionStore = new DatabaseSchemaVersionStore();
        }

        return $this->versionStore;
    }
}
