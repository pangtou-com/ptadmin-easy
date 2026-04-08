<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Unit\Permission;

use PHPUnit\Framework\TestCase;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Permission\DefaultPermissionChecker;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Exceptions\EasyException;

/**
 * 默认权限校验器测试.
 *
 * 这些用例只覆盖规则匹配逻辑，不依赖 Laravel 容器或数据库。
 */
final class DefaultPermissionCheckerTest extends TestCase
{
    public function test_it_allows_operation_when_permission_rule_matches_ability(): void
    {
        $checker = new DefaultPermissionChecker();
        $definition = $this->makeDefinition([
            'permissions' => [
                'create' => 'article.create',
            ],
        ]);
        $context = new ExecutionContext([
            'abilities' => ['article.create'],
        ]);

        $checker->authorize('create', $definition, [], $context);

        $this->assertTrue(true);
    }

    public function test_it_denies_operation_when_permission_rule_is_false(): void
    {
        $checker = new DefaultPermissionChecker();
        $definition = $this->makeDefinition([
            'permissions' => [
                'delete' => false,
            ],
        ]);

        $this->expectException(EasyException::class);
        $this->expectExceptionMessage('Unauthorized operation [delete] on resource [articles].');

        $checker->authorize('delete', $definition, [], new ExecutionContext());
    }

    public function test_it_supports_role_and_ability_rules_with_all_mode(): void
    {
        $checker = new DefaultPermissionChecker();
        $definition = $this->makeDefinition([
            'permissions' => [
                'update' => [
                    'abilities' => ['article.update'],
                    'roles' => ['editor'],
                    'all' => true,
                ],
            ],
        ]);
        $context = new ExecutionContext([
            'abilities' => ['article.update'],
            'roles' => ['editor'],
        ]);

        $checker->authorize('update', $definition, [], $context);

        $this->assertTrue(true);
    }

    /**
     * 构建最小可用的 schema 定义对象.
     *
     * @param array<string, mixed> $schema
     */
    private function makeDefinition(array $schema): SchemaDefinition
    {
        $resource = $this->createMock(IResource::class);
        $resource->method('getRawTable')->willReturn('articles');
        $resource->method('toArray')->willReturn($schema);

        return new SchemaDefinition($resource);
    }
}
