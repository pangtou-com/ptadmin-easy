<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Permission;

use PTAdmin\Easy\Core\Permission\Contracts\PermissionCheckerInterface;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Core\Schema\Definition\SchemaDefinition;
use PTAdmin\Easy\Exceptions\EasyException;

/**
 * 默认权限校验器.
 *
 * 通过 schema 中的 `permissions` 配置和上下文中的
 * `abilities/roles/is_super_admin` 信息进行权限判断。
 */
class DefaultPermissionChecker implements PermissionCheckerInterface
{
    public function authorize(string $operation, SchemaDefinition $definition, array $payload = [], ?ExecutionContext $context = null): void
    {
        $context = $context ?? new ExecutionContext();
        if (true === (bool) $context->get('bypass_authorization', false)) {
            return;
        }
        if (true === (bool) $context->get('is_super_admin', false)) {
            return;
        }

        $permissions = (array) data_get($definition->toArray(), 'permissions', []);
        $rule = $permissions[$operation] ?? ($permissions['*'] ?? null);
        if (null === $rule || [] === $rule || true === $rule) {
            return;
        }
        if (false === $rule) {
            throw new EasyException("Unauthorized operation [{$operation}] on resource [{$definition->name()}].");
        }

        $abilities = array_values(array_filter((array) $context->get('abilities', [])));
        $roles = array_values(array_filter((array) $context->get('roles', [])));
        $granted = $this->matchesRule($rule, $abilities, $roles);
        if (!$granted) {
            throw new EasyException("Unauthorized operation [{$operation}] on resource [{$definition->name()}].");
        }
    }

    /**
     * @param mixed    $rule
     * @param string[] $abilities
     * @param string[] $roles
     */
    private function matchesRule($rule, array $abilities, array $roles): bool
    {
        if (\is_string($rule)) {
            return \in_array($rule, $abilities, true) || \in_array($rule, $roles, true);
        }

        if (!\is_array($rule)) {
            return false;
        }

        $normalized = array_values(array_filter($rule, static function ($value) {
            return \is_string($value) && '' !== $value;
        }));
        if (0 !== \count($normalized)) {
            $owned = array_merge($abilities, $roles);

            return 0 !== \count(array_intersect($normalized, $owned));
        }

        $abilityRules = array_values(array_filter((array) ($rule['abilities'] ?? [])));
        $roleRules = array_values(array_filter((array) ($rule['roles'] ?? [])));
        $requireAll = true === (bool) ($rule['all'] ?? false);

        if (0 === \count($abilityRules) && 0 === \count($roleRules)) {
            return true;
        }

        $abilityMatched = 0 === \count($abilityRules) ? !$requireAll : $this->matchSet($abilityRules, $abilities, $requireAll);
        $roleMatched = 0 === \count($roleRules) ? !$requireAll : $this->matchSet($roleRules, $roles, $requireAll);

        return $requireAll ? $abilityMatched && $roleMatched : $abilityMatched || $roleMatched;
    }

    /**
     * @param string[] $required
     * @param string[] $owned
     */
    private function matchSet(array $required, array $owned, bool $requireAll): bool
    {
        $matched = array_intersect($required, $owned);

        return $requireAll ? \count($matched) === \count($required) : 0 !== \count($matched);
    }
}
