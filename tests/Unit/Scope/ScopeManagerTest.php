<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Unit\Scope;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Core\Scope\ScopeManager;

/**
 * Scope 管理器测试.
 *
 * 用于确认全局 scope 与资源级 scope 会按预期叠加执行。
 */
final class ScopeManagerTest extends TestCase
{
    public function test_it_applies_global_and_resource_scopes_in_order(): void
    {
        $manager = new ScopeManager();
        $builder = new ArrayObject([
            'applied' => [],
        ]);
        $definition = $this->makeDefinition('articles');

        $manager->on('*', function (ArrayObject $builder): void {
            $builder['applied'][] = 'global:*';
        });
        $manager->on('list', function (ArrayObject $builder): void {
            $builder['applied'][] = 'global:list';
        });
        $manager->onResource('articles', '*', function (ArrayObject $builder): void {
            $builder['applied'][] = 'resource:*';
        });
        $manager->onResource('articles', 'list', function (ArrayObject $builder): void {
            $builder['applied'][] = 'resource:list';
        });

        $manager->apply($builder, $definition, 'list', new ExecutionContext());

        $this->assertSame([
            'global:*',
            'global:list',
            'resource:*',
            'resource:list',
        ], $builder['applied']);
    }

    private function makeDefinition(string $resource): SchemaDefinition
    {
        $definitionResource = $this->createMock(IResource::class);
        $definitionResource->method('getRawTable')->willReturn($resource);

        return new SchemaDefinition($definitionResource);
    }
}
