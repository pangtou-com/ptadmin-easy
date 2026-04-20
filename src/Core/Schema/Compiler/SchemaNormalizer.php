<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Compiler;

/**
 * Schema 标准化器.
 *
 * 前端拖拽生成的 schema 往往会把布局节点与真实字段混在一起。
 * 这里负责在不丢失原始布局树的前提下，将可落库/可参与运行时的
 * 字段节点抽平到 `fields` 中，并把布局树保留到 `layout`。
 */
final class SchemaNormalizer
{
    /**
     * 关系类型别名映射.
     *
     * 当前运行时会将新的关系协议统一折叠到内部兼容结构上。
     *
     * @var array<string, string>
     */
    private const RELATION_KIND_ALIASES = [
        'belongsto' => 'belongsTo',
        'belongs_to' => 'belongsTo',
        'belongs-to' => 'belongsTo',
        'hasone' => 'hasOne',
        'has_one' => 'hasOne',
        'has-one' => 'hasOne',
        'hasmany' => 'hasMany',
        'has_many' => 'hasMany',
        'has-many' => 'hasMany',
    ];

    /**
     * 常见的纯布局节点类型.
     *
     * 这些节点只负责页面结构组织，不应该参与表结构、校验或查询。
     *
     * @var string[]
     */
    private const LAYOUT_TYPES = [
        'layout',
        'container',
        'grid',
        'row',
        'col',
        'column',
        'columns',
        'tabs',
        'tab',
        'tab-pane',
        'pane',
        'section',
        'group',
        'collapse',
        'collapse-item',
        'card',
        'panel',
        'flex',
        'divider',
        'space',
        'icon',
        'footer',
        'dialog',
        'drawer',
        'default',
        'button',
        'button-group',
        'confirm',
        'control',
        'form',
        'search',
        'form-item',
        'help',
    ];

    /**
     * 可能挂载子节点的常见 key.
     *
     * @var string[]
     */
    private const CHILD_KEYS = [
        'children',
        'nodes',
        'items',
        'body',
        'columns',
        'tabs',
        'fields',
        'schemas',
    ];

    /**
     * 字段组件别名映射.
     *
     * 前端拖拽器的组件类型，与当前后端内核支持的类型不完全一致，
     * 这里统一收口为已有引擎可识别的类型。
     *
     * @var array<string, string>
     */
    private const FIELD_TYPE_ALIASES = [
        'switch' => 'switch',
        'resource' => 'resource',
        'cascader' => 'cascader',
    ];

    /**
     * 标准化 schema.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    public function normalize(array $schema): array
    {
        if (isset($schema['name']) && \is_string($schema['name'])) {
            $schema['name'] = trim((string) $schema['name']);
        }

        $nodes = $this->extractSourceNodes($schema);
        if (0 === \count($nodes)) {
            $schema['fields'] = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));

            return $this->normalizeRelations($schema);
        }

        $fields = [];
        $containsLayoutNode = false;

        foreach ($nodes as $node) {
            $this->collectFields($node, $fields, $containsLayoutNode);
        }

        $schema['fields'] = array_values($fields);

        if ($containsLayoutNode) {
            $schema['layout'] = $schema['layout'] ?? [
                'nodes' => $nodes,
            ];
        }

        return $this->normalizeRelations($schema);
    }

    /**
     * 返回 schema 的原始节点列表.
     *
     * @param array<string, mixed> $schema
     *
     * @return array<int, mixed>
     */
    private function extractSourceNodes(array $schema): array
    {
        foreach (['fields', 'nodes', 'layout.nodes'] as $path) {
            $nodes = data_get($schema, $path);
            if (\is_array($nodes)) {
                return array_values($nodes);
            }
        }

        return [];
    }

    /**
     * 递归抽取真实字段节点.
     *
     * @param mixed                    $node
     * @param array<int, array<string, mixed>> $fields
     */
    private function collectFields($node, array &$fields, bool &$containsLayoutNode): void
    {
        if (!\is_array($node)) {
            return;
        }

        if ($this->isFieldNode($node)) {
            $fields[] = $this->normalizeFieldNode($this->stripNestedLayoutKeys($node));
        } elseif ($this->isLayoutNode($node)) {
            $containsLayoutNode = true;
        }

        foreach ($this->childNodes($node) as $child) {
            $this->collectFields($child, $fields, $containsLayoutNode);
        }
    }

    /**
     * 判断是否为真实字段节点.
     *
     * 只要节点具备合法字段名，并且不是纯布局容器，就认为是字段。
     *
     * @param array<string, mixed> $node
     */
    private function isFieldNode(array $node): bool
    {
        $name = $node['name'] ?? null;
        if (!\is_string($name) || '' === trim($name)) {
            return false;
        }

        if ($this->isExplicitLayoutOnlyNode($node)) {
            return false;
        }

        return true;
    }

    /**
     * 判断是否为布局节点.
     *
     * @param array<string, mixed> $node
     */
    private function isLayoutNode(array $node): bool
    {
        if ($this->isExplicitLayoutOnlyNode($node)) {
            return true;
        }

        return $this->hasChildren($node) && !$this->isFieldNode($node);
    }

    /**
     * 判断节点是否被显式标记为纯布局容器.
     *
     * @param array<string, mixed> $node
     */
    private function isExplicitLayoutOnlyNode(array $node): bool
    {
        if (true === (bool) ($node['is_layout'] ?? false)) {
            return true;
        }

        $nodeType = $node['node_type'] ?? ($node['kind'] ?? null);
        if (\is_string($nodeType) && 'layout' === strtolower($nodeType)) {
            return true;
        }

        $type = $node['type'] ?? ($node['component'] ?? null);
        if (!\is_string($type) || '' === trim($type)) {
            return false;
        }

        return \in_array(strtolower(trim($type)), self::LAYOUT_TYPES, true);
    }

    /**
     * 返回节点的所有子节点.
     *
     * @param array<string, mixed> $node
     *
     * @return array<int, mixed>
     */
    private function childNodes(array $node): array
    {
        $children = [];
        foreach (self::CHILD_KEYS as $key) {
            if (!isset($node[$key]) || !\is_array($node[$key])) {
                continue;
            }

            foreach ($node[$key] as $child) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * 判断节点是否存在子节点.
     *
     * @param array<string, mixed> $node
     */
    private function hasChildren(array $node): bool
    {
        return 0 !== \count($this->childNodes($node));
    }

    /**
     * 字段节点扁平化时移除纯布局子节点定义，避免污染字段元数据.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function stripNestedLayoutKeys(array $node): array
    {
        foreach (self::CHILD_KEYS as $key) {
            unset($node[$key]);
        }

        return $node;
    }

    /**
     * 将前端字段节点转换为后端内核可识别的统一格式.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function normalizeFieldNode(array $node): array
    {
        $type = $node['type'] ?? null;
        if (\is_string($type) && isset(self::FIELD_TYPE_ALIASES[strtolower($type)])) {
            $node['origin_type'] = $node['origin_type'] ?? $type;
            $node['type'] = self::FIELD_TYPE_ALIASES[strtolower($type)];
        }

        if (!isset($node['default']) && array_key_exists('defaultValue', $node)) {
            $node['default'] = $node['defaultValue'];
        }

        if (!isset($node['is_required']) && array_key_exists('required', $node)) {
            $node['is_required'] = true === (bool) $node['required'] ? 1 : 0;
        }

        if (!isset($node['is_required']) && $this->containsRequiredRule((array) ($node['rules'] ?? []))) {
            $node['is_required'] = 1;
        }

        if (!isset($node['comment']) && array_key_exists('help', $node)) {
            $comment = $this->normalizeHelpComment($node['help']);
            if (null !== $comment) {
                $node['comment'] = $comment;
            }
        }

        if (!isset($node['length']) && isset($node['maxlength']) && is_numeric($node['maxlength'])) {
            $node['length'] = (int) $node['maxlength'];
        }

        if (!isset($node['min']) && isset($node['minlength']) && is_numeric($node['minlength'])) {
            $node['min'] = (int) $node['minlength'];
        }

        if (isset($node['min']) || isset($node['max']) || isset($node['step'])) {
            $node['extends'] = \is_array($node['extends'] ?? null) ? $node['extends'] : [];
            foreach (['min', 'max', 'step'] as $key) {
                if (!isset($node[$key]) || isset($node['extends'][$key])) {
                    continue;
                }
                $node['extends'][$key] = $node[$key];
            }
        }

        if ('switch' === ($node['type'] ?? null) && !isset($node['options'])) {
            $activeText = isset($node['active-text']) && '' !== trim((string) $node['active-text'])
                ? trim((string) $node['active-text'])
                : '是';
            $inactiveText = isset($node['inactive-text']) && '' !== trim((string) $node['inactive-text'])
                ? trim((string) $node['inactive-text'])
                : '否';
            $activeValue = $node['active-value'] ?? 1;
            $inactiveValue = $node['inactive-value'] ?? 0;
            $node['options'] = [
                ['label' => $activeText, 'value' => $activeValue],
                ['label' => $inactiveText, 'value' => $inactiveValue],
            ];
            if (!isset($node['default'])) {
                $node['default'] = $inactiveValue;
            }
        }

        $validation = $this->normalizeValidation($node);
        if (0 !== \count($validation['rules'])) {
            $node['rules'] = $validation['rules'];
        }

        if (0 !== \count($validation['messages'])) {
            $node['rule_messages'] = $validation['messages'];
        }

        $node['display'] = $this->normalizeDisplay($node);

        return $node;
    }

    /**
     * 标准化 schema 中的关系协议。
     *
     * 支持两类新写法：
     * 1. 字段内联 `relation`
     * 2. 资源级 `relations.belongs_to.<field>`
     *
     * 归一化后会同时写回：
     * - `field.relation` 作为后续协议演进的标准描述
     * - `field.extends` 作为当前运行时兼容层消费结构
     *
     * @param array<string, mixed> $schema
     *
     * @return array<string, mixed>
     */
    private function normalizeRelations(array $schema): array
    {
        $fields = array_values(array_filter((array) ($schema['fields'] ?? []), 'is_array'));
        if (0 === \count($fields)) {
            $schema['fields'] = $fields;

            return $schema;
        }

        $resourceRelations = $this->normalizeSchemaRelations((array) ($schema['relations'] ?? []));
        $normalizedRelations = [];

        foreach ($fields as $index => $field) {
            $name = $field['name'] ?? null;
            if (!\is_string($name) || '' === trim($name)) {
                $fields[$index] = $field;

                continue;
            }

            $relation = $this->resolveFieldRelationConfig($field, $resourceRelations[$name] ?? null);
            if (null === $relation) {
                $fields[$index] = $field;

                continue;
            }

            $field['relation'] = $relation;
            $field['extends'] = $this->mergeRelationIntoExtends($field, $relation);
            $fields[$index] = $field;
            $normalizedRelations[$name] = $relation;
        }

        $schema['fields'] = array_values($fields);
        if (0 !== \count($normalizedRelations)) {
            $schema['relations'] = $normalizedRelations;
        }

        return $schema;
    }

    /**
     * 标准化资源级关系配置.
     *
     * @param array<string, mixed> $relations
     *
     * @return array<string, array<string, mixed>>
     */
    private function normalizeSchemaRelations(array $relations): array
    {
        $normalized = [];

        foreach ($relations as $key => $value) {
            if (\is_array($value) && $this->isRelationGroupKey((string) $key)) {
                foreach ($value as $field => $config) {
                    if (!\is_string($field) || !\is_array($config)) {
                        continue;
                    }

                    $relation = $this->normalizeRelationConfig($config, (string) $key);
                    if (null !== $relation) {
                        $normalized[$field] = $relation;
                    }
                }

                continue;
            }

            if (!\is_string($key) || !\is_array($value)) {
                continue;
            }

            $relation = $this->normalizeRelationConfig($value);
            if (null !== $relation) {
                $normalized[$key] = $relation;
            }
        }

        return $normalized;
    }

    /**
     * 判断资源级关系 key 是否为关系分组名。
     */
    private function isRelationGroupKey(string $key): bool
    {
        $normalized = strtolower(trim($key));

        return isset(self::RELATION_KIND_ALIASES[$normalized]);
    }

    /**
     * 解析字段最终应使用的关系配置.
     *
     * @param array<string, mixed>      $field
     * @param array<string, mixed>|null $resourceRelation
     *
     * @return array<string, mixed>|null
     */
    private function resolveFieldRelationConfig(array $field, ?array $resourceRelation = null): ?array
    {
        $inlineRelation = null;
        if (\is_array($field['relation'] ?? null)) {
            $inlineRelation = $this->normalizeRelationConfig((array) $field['relation']);
        }

        if (null !== $inlineRelation) {
            return $inlineRelation;
        }

        if (null !== $resourceRelation) {
            return $resourceRelation;
        }

        if (\is_array($field['extends'] ?? null) && isset($field['extends']['type'])) {
            return $this->normalizeRelationConfig((array) $field['extends']);
        }

        return null;
    }

    /**
     * 将不同来源的关系配置折叠成统一结构.
     *
     * @param array<string, mixed> $relation
     *
     * @return array<string, mixed>|null
     */
    private function normalizeRelationConfig(array $relation, ?string $defaultKind = null): ?array
    {
        $kind = $this->normalizeRelationKind((string) ($relation['kind'] ?? $relation['type'] ?? $defaultKind ?? ''));
        $resource = $relation['resource'] ?? $relation['target'] ?? $relation['table'] ?? $relation['name'] ?? null;
        if (!\is_string($resource) || '' === trim($resource)) {
            return null;
        }

        $normalized = [
            'kind' => $kind,
            'type' => \in_array($kind, ['hasOne', 'hasMany'], true) ? $kind : 'resource',
            'table' => trim($resource),
            'value' => $relation['value'] ?? $relation['value_field'] ?? $relation['owner_key'] ?? 'id',
            'label' => $relation['label'] ?? $relation['label_field'] ?? $relation['title_field'] ?? 'title',
            'filter' => $relation['filter'] ?? $relation['filters'] ?? [],
        ];

        $foreignKey = $relation['foreign_key'] ?? $relation['foreignKey'] ?? null;
        if (\is_string($foreignKey) && '' !== trim($foreignKey)) {
            $normalized['foreign_key'] = trim($foreignKey);
        }

        $localKey = $relation['local_key'] ?? $relation['localKey'] ?? null;
        if (\is_string($localKey) && '' !== trim($localKey)) {
            $normalized['local_key'] = trim($localKey);
        }

        $deletePolicy = $this->normalizeRelationDeletePolicy(
            $relation['on_delete'] ?? $relation['onDelete'] ?? $relation['delete_strategy'] ?? $relation['deleteStrategy'] ?? null
        );
        if (null !== $deletePolicy) {
            $normalized['on_delete'] = $deletePolicy;
        }

        $appendName = $relation['append_name'] ?? $relation['append'] ?? null;
        if (\is_string($appendName) && '' !== trim($appendName)) {
            $normalized['append_name'] = trim($appendName);
        }

        if (isset($relation['multiple'])) {
            $normalized['multiple'] = true === (bool) $relation['multiple'];
        }

        return $normalized;
    }

    /**
     * 标准化关系类型名称.
     */
    private function normalizeRelationKind(string $kind): string
    {
        $normalized = strtolower(trim($kind));
        if (isset(self::RELATION_KIND_ALIASES[$normalized])) {
            return self::RELATION_KIND_ALIASES[$normalized];
        }

        return '' === $normalized ? 'belongsTo' : $kind;
    }

    /**
     * 将标准关系协议回写到当前运行时使用的 extends 结构中.
     *
     * @param array<string, mixed> $field
     * @param array<string, mixed> $relation
     *
     * @return array<string, mixed>
     */
    private function mergeRelationIntoExtends(array $field, array $relation): array
    {
        $extends = \is_array($field['extends'] ?? null) ? $field['extends'] : [];
        $extends['type'] = \in_array($relation['kind'], ['hasOne', 'hasMany'], true) ? $relation['kind'] : 'resource';
        $extends['table'] = $relation['table'];
        $extends['name'] = $relation['table'];
        $extends['value'] = $relation['value'];
        $extends['label'] = $relation['label'];
        $extends['filter'] = $relation['filter'];
        $extends['relation_kind'] = $relation['kind'];

        if (isset($relation['append_name']) && !isset($extends['append_name'])) {
            $extends['append_name'] = $relation['append_name'];
        }

        if (isset($relation['multiple']) && !isset($extends['multiple'])) {
            $extends['multiple'] = true === (bool) $relation['multiple'];
        }

        if (isset($relation['on_delete']) && !isset($extends['on_delete'])) {
            $extends['on_delete'] = $relation['on_delete'];
        }

        return $extends;
    }

    /**
     * 标准化关联删除策略。
     */
    private function normalizeRelationDeletePolicy($policy): ?string
    {
        if (!\is_string($policy) || '' === trim($policy)) {
            return null;
        }

        $normalized = strtolower(trim($policy));
        switch ($normalized) {
            case 'cascade':
            case 'restrict':
                return $normalized;

            case 'setnull':
            case 'set_null':
            case 'set-null':
            case 'null':
            case 'nullify':
                return 'set_null';
        }

        return $normalized;
    }

    /**
     * 标准化前端字段规则与提示语配置.
     *
     * 前端拖拽器常见的规则对象形态会在这里收口为 Laravel
     * Validator 可直接识别的规则字符串，并额外提取 rule -> message
     * 的映射供运行时直接使用。
     *
     * @param array<string, mixed> $node
     *
     * @return array{rules: array<int, mixed>, messages: array<string, string>}
     */
    private function normalizeValidation(array $node): array
    {
        $rules = [];
        $messages = [];
        foreach ((array) ($node['rules'] ?? []) as $rule) {
            if (\is_string($rule) || \is_object($rule)) {
                $rules[] = $rule;

                continue;
            }

            if (!\is_array($rule)) {
                continue;
            }

            $message = null;
            if (\is_string($rule['message'] ?? null) && '' !== trim((string) $rule['message'])) {
                $message = trim((string) $rule['message']);
            }

            if (true === (bool) ($rule['required'] ?? false)) {
                $this->appendRule($rules, $messages, 'required', $message);
            }

            if (isset($rule['min']) && is_numeric($rule['min'])) {
                $this->appendRule($rules, $messages, 'min:'.(string) $rule['min'], $message);
            }

            if (isset($rule['max']) && is_numeric($rule['max'])) {
                $this->appendRule($rules, $messages, 'max:'.(string) $rule['max'], $message);
            }

            if (isset($rule['pattern']) && \is_string($rule['pattern']) && '' !== trim($rule['pattern'])) {
                $this->appendRule($rules, $messages, 'regex:'.$this->normalizeRegexPattern($rule['pattern']), $message);
            }

            if (isset($rule['type']) && \is_string($rule['type']) && '' !== trim($rule['type'])) {
                $this->appendRule($rules, $messages, trim((string) $rule['type']), $message);
            }
        }

        return [
            'rules' => $this->uniqueRules($rules),
            'messages' => $messages,
        ];
    }

    /**
     * 标准化字段显示与编辑状态.
     *
     * 这里会把前端常见的 `hidden`、`readonly`、`disabled` 和
     * `scenes.*.visible` 收口为统一 display 结构，便于编辑器回显与
     * 运行时消费保持一致。
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private function normalizeDisplay(array $node): array
    {
        $hidden = true === (bool) ($node['hidden'] ?? false);
        $readonly = true === (bool) ($node['readonly'] ?? false);
        $disabled = true === (bool) ($node['disabled'] ?? false);
        $editable = !$readonly && !$disabled;

        return [
            'hidden' => $hidden,
            'readonly' => $readonly,
            'disabled' => $disabled,
            'editable' => $editable,
            'form' => [
                'visible' => $this->resolveSceneVisible($node, 'form', !$hidden),
                'editable' => $editable,
            ],
            'table' => [
                'visible' => $this->resolveSceneVisible($node, 'table', !$hidden),
            ],
            'detail' => [
                'visible' => $this->resolveSceneVisible($node, 'detail', !$hidden),
            ],
        ];
    }

    /**
     * 解析指定场景的可见性配置.
     *
     * @param array<string, mixed> $node
     */
    private function resolveSceneVisible(array $node, string $scene, bool $default): bool
    {
        $visible = data_get($node, "scenes.{$scene}.visible");
        if (null === $visible) {
            return $default;
        }

        return true === (bool) $visible;
    }

    /**
     * 追加规则并记录对应提示语.
     *
     * @param array<int, mixed>      $rules
     * @param array<string, string>  $messages
     */
    private function appendRule(array &$rules, array &$messages, string $rule, ?string $message): void
    {
        $rules[] = $rule;

        if (null === $message || '' === $message) {
            return;
        }

        $messages[$this->extractRuleName($rule)] = $message;
    }

    /**
     * 从规则字符串中提取规则名.
     */
    private function extractRuleName(string $rule): string
    {
        $segments = explode(':', $rule, 2);

        return trim($segments[0]);
    }

    /**
     * 标准化正则表达式分隔符.
     */
    private function normalizeRegexPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        if ('' === $pattern) {
            return '/^$/';
        }

        $first = substr($pattern, 0, 1);
        $last = substr($pattern, -1);
        if ('/' === $first && '/' === $last && 1 < strlen($pattern)) {
            return $pattern;
        }

        return '/'.str_replace('/', '\/', $pattern).'/';
    }

    /**
     * 去重规则列表，避免别名规则和自动规则重复追加.
     *
     * @param array<int, mixed> $rules
     *
     * @return array<int, mixed>
     */
    private function uniqueRules(array $rules): array
    {
        $normalized = [];
        $seen = [];

        foreach ($rules as $rule) {
            if (\is_string($rule)) {
                if (isset($seen[$rule])) {
                    continue;
                }
                $seen[$rule] = true;
            }

            $normalized[] = $rule;
        }

        return $normalized;
    }

    /**
     * @param array<int, mixed> $rules
     */
    private function containsRequiredRule(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (\is_string($rule) && 'required' === trim($rule)) {
                return true;
            }

            if (\is_array($rule) && true === (bool) ($rule['required'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $help
     */
    private function normalizeHelpComment($help): ?string
    {
        if (\is_string($help) && '' !== trim($help)) {
            return trim($help);
        }

        if (!\is_array($help)) {
            return null;
        }

        foreach (['message', 'content', 'title'] as $key) {
            if (\is_string($help[$key] ?? null) && '' !== trim((string) $help[$key])) {
                return trim((string) $help[$key]);
            }
        }

        return null;
    }
}
