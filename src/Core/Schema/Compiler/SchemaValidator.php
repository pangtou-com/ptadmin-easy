<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Core\Schema\Compiler;

use PTAdmin\Easy\Components\Component;
use PTAdmin\Easy\Core\Chart\ChartCompiler;
use PTAdmin\Easy\Core\Chart\Definition\ChartDefinition;
use PTAdmin\Easy\Core\Support\SensitiveFieldHelper;

/**
 * Schema 校验器.
 *
 * 用于在 schema 发布、草稿保存和运行时编译之前，尽早校验
 * 资源配置的基础合法性，减少错误拖到运行期才暴露。
 */
final class SchemaValidator
{
    private const RESERVED_FIELD_PREFIX = '__';

    /**
     * 会生成展示附加字段的组件类型.
     *
     * @var string[]
     */
    private const APPEND_TYPES = ['radio', 'checkbox', 'select', 'switch', 'link'];

    /**
     * 支持敏感字段协议的组件类型。
     *
     * @var string[]
     */
    private const SENSITIVE_FIELD_TYPES = ['text', 'textarea'];

    /** @var ChartCompiler */
    private $chartCompiler;

    /** @var SchemaNormalizer */
    private $normalizer;

    /** @var Component */
    private $componentManager;

    public function __construct(
        ?ChartCompiler $chartCompiler = null,
        ?SchemaNormalizer $normalizer = null,
        ?Component $componentManager = null
    )
    {
        $this->chartCompiler = $chartCompiler ?? new ChartCompiler();
        $this->normalizer = $normalizer ?? new SchemaNormalizer();
        $this->componentManager = $componentManager ?? new Component();
    }

    /**
     * 校验 schema 配置.
     *
     * @param array<string, mixed> $schema
     */
    public function validate(array $schema): void
    {
        $schema = $this->normalizer->normalize($schema);

        $resource = $schema['name'] ?? null;
        if (!\is_string($resource) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $resource)) {
            throw new \InvalidArgumentException('Schema name is required and must use letters, numbers, and underscores.');
        }

        $fields = $schema['fields'] ?? null;
        if (!\is_array($fields) || 0 === \count($fields)) {
            throw new \InvalidArgumentException('Schema fields are required.');
        }

        $fieldNames = $this->validateFields($fields);

        $this->validateNamedReference($schema['title_field'] ?? null, 'Schema title_field', $fieldNames);
        $this->validateNamedReference($schema['cover_field'] ?? null, 'Schema cover_field', $fieldNames);
        $this->validateSearchFields($schema['search_fields'] ?? [], $fieldNames);
        $this->validateOrderFields($schema['order'] ?? [], $fieldNames);
        $this->validatePermissions($schema['permissions'] ?? []);
        $this->validateCharts((array) ($schema['charts'] ?? []), $fieldNames);
    }

    /**
     * 校验字段定义集合.
     *
     * @param array<int, mixed> $fields
     *
     * @return array<string, true>
     */
    private function validateFields(array $fields): array
    {
        $fieldNames = ['id' => true];
        $appendNames = [];

        foreach ($fields as $index => $field) {
            if (!\is_array($field)) {
                throw new \InvalidArgumentException('Schema field definition must be an array at index ['.$index.'].');
            }

            $name = $field['name'] ?? null;
            if (!\is_string($name) || '' === $name) {
                throw new \InvalidArgumentException('Schema field name is invalid at index ['.$index.'].');
            }
            if (0 === strpos($name, self::RESERVED_FIELD_PREFIX)) {
                throw new \InvalidArgumentException('Schema field ['.$name.'] uses reserved prefix ['.self::RESERVED_FIELD_PREFIX.'].');
            }
            if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
                throw new \InvalidArgumentException('Schema field name is invalid at index ['.$index.'].');
            }

            if (isset($fieldNames[$name])) {
                throw new \InvalidArgumentException('Schema field ['.$name.'] is duplicated.');
            }

            $this->validateFieldType($field, $name, $index);
            $this->validateSensitiveFieldConfig($field, $name);

            $fieldNames[$name] = true;

            $appendName = $this->resolveAppendName($field, $name);
            if (null === $appendName) {
                continue;
            }

            if (isset($fieldNames[$appendName])) {
                throw new \InvalidArgumentException('Schema append field ['.$appendName.'] conflicts with real field ['.$name.'].');
            }
            if (isset($appendNames[$appendName])) {
                throw new \InvalidArgumentException('Schema append field ['.$appendName.'] is duplicated.');
            }

            $appendNames[$appendName] = true;
        }

        return $fieldNames;
    }

    /**
     * 校验字段类型是否合法。
     *
     * 当前要求字段必须显式声明 type，且 type 必须是已注册组件。
     *
     * @param array<string, mixed> $field
     */
    private function validateFieldType(array $field, string $name, int $index): void
    {
        $type = $field['type'] ?? null;
        if (!\is_string($type) || '' === trim($type)) {
            throw new \InvalidArgumentException('Schema field ['.$name.'] type is invalid at index ['.$index.'].');
        }

        if (!$this->componentManager->hasComponent(trim($type))) {
            throw new \InvalidArgumentException('Schema field ['.$name.'] type ['.trim($type).'] is not supported.');
        }
    }

    /**
     * 校验敏感字段协议是否合法。
     *
     * @param array<string, mixed> $field
     */
    private function validateSensitiveFieldConfig(array $field, string $name): void
    {
        $type = strtolower(trim((string) ($field['type'] ?? 'text')));
        $secret = SensitiveFieldHelper::isSecret($field);
        $maskConfig = SensitiveFieldHelper::maskConfigFromMetadata($field);

        if (!$secret && null === $maskConfig) {
            return;
        }

        if (!\in_array($type, self::SENSITIVE_FIELD_TYPES, true)) {
            throw new \InvalidArgumentException('Schema field ['.$name.'] sensitive config is only supported for text/textarea.');
        }

        if ($secret && null !== $maskConfig) {
            throw new \InvalidArgumentException('Schema field ['.$name.'] cannot define both secret and mask.');
        }
    }

    /**
     * 解析字段的展示附加字段名.
     */
    private function resolveAppendName(array $field, string $name): ?string
    {
        $type = strtolower((string) ($field['type'] ?? 'text'));
        if (!\in_array($type, self::APPEND_TYPES, true)) {
            return null;
        }

        $appendName = data_get($field, 'extends.append_name');
        if (null === $appendName || '' === $appendName) {
            return self::RESERVED_FIELD_PREFIX.$name.'_text';
        }

        if (!\is_string($appendName) || !preg_match('/^__[A-Za-z][A-Za-z0-9_]*$/', $appendName)) {
            throw new \InvalidArgumentException('Schema append_name for field ['.$name.'] must start with ['.self::RESERVED_FIELD_PREFIX.'] and use letters, numbers, and underscores.');
        }

        return $appendName;
    }

    /**
     * 校验单个字段引用.
     *
     * @param mixed              $field
     * @param array<string, true> $fieldNames
     */
    private function validateNamedReference($field, string $label, array $fieldNames): void
    {
        if (null === $field || '' === $field) {
            return;
        }

        if (!\is_string($field) || !isset($fieldNames[$field])) {
            throw new \InvalidArgumentException($label.' ['.(string) $field.'] does not exist.');
        }
    }

    /**
     * 校验搜索字段配置.
     *
     * @param mixed               $searchFields
     * @param array<string, true> $fieldNames
     */
    private function validateSearchFields($searchFields, array $fieldNames): void
    {
        if (!\is_array($searchFields)) {
            return;
        }

        foreach ($searchFields as $field) {
            if (!\is_string($field) || !isset($fieldNames[$field])) {
                throw new \InvalidArgumentException('Schema search field ['.(string) $field.'] does not exist.');
            }
        }
    }

    /**
     * 校验默认排序字段配置.
     *
     * @param mixed               $order
     * @param array<string, true> $fieldNames
     */
    private function validateOrderFields($order, array $fieldNames): void
    {
        if (!\is_array($order)) {
            return;
        }

        foreach ($order as $field => $direction) {
            if (!\is_string($field) || !isset($fieldNames[$field])) {
                throw new \InvalidArgumentException('Schema order field ['.(string) $field.'] does not exist.');
            }

            if (!\is_scalar($direction)) {
                throw new \InvalidArgumentException('Schema order direction for field ['.$field.'] is invalid.');
            }
        }
    }

    /**
     * 校验权限配置结构.
     *
     * @param mixed $permissions
     */
    private function validatePermissions($permissions): void
    {
        if (!\is_array($permissions)) {
            return;
        }

        foreach ($permissions as $operation => $rule) {
            if (\is_bool($rule) || \is_string($rule)) {
                continue;
            }

            if (!\is_array($rule)) {
                throw new \InvalidArgumentException('Schema permission ['.(string) $operation.'] must be bool, string, or array.');
            }

            foreach (['abilities', 'roles'] as $key) {
                if (!isset($rule[$key])) {
                    continue;
                }

                if (!\is_array($rule[$key])) {
                    throw new \InvalidArgumentException('Schema permission ['.(string) $operation.'].'.$key.' must be an array.');
                }

                foreach ($rule[$key] as $value) {
                    if (!\is_string($value) || '' === trim($value)) {
                        throw new \InvalidArgumentException('Schema permission ['.(string) $operation.'].'.$key.' contains an invalid value.');
                    }
                }
            }
        }
    }

    /**
     * 校验图表配置及其字段引用.
     *
     * @param array<int, mixed>   $charts
     * @param array<string, true> $fieldNames
     */
    private function validateCharts(array $charts, array $fieldNames): void
    {
        $definitions = $this->chartCompiler->compileMany($charts);
        foreach ($definitions as $definition) {
            $this->validateChartDefinition($definition, $fieldNames);
        }
    }

    /**
     * 校验单个图表定义引用的字段是否存在.
     *
     * @param array<string, true> $fieldNames
     */
    private function validateChartDefinition(ChartDefinition $definition, array $fieldNames): void
    {
        foreach ($definition->dimensions() as $dimension) {
            $this->validateNamedReference($dimension['field'] ?? null, 'Chart dimension field', $fieldNames);
        }

        foreach ($definition->metrics() as $metric) {
            $field = $metric['field'] ?? null;
            if (null === $field || '' === $field) {
                continue;
            }

            $this->validateNamedReference($field, 'Chart metric field', $fieldNames);
        }

        $query = $definition->query();
        foreach ((array) ($query['filters'] ?? []) as $filter) {
            if (\is_array($filter)) {
                $this->validateNamedReference($filter['field'] ?? null, 'Chart filter field', $fieldNames);
            }
        }

        foreach ((array) ($query['keyword_fields'] ?? []) as $field) {
            $this->validateNamedReference($field, 'Chart keyword field', $fieldNames);
        }

        $groups = array_values(array_filter((array) ($query['groups'] ?? []), 'is_string'));
        foreach ($groups as $field) {
            $this->validateNamedReference($field, 'Chart group field', $fieldNames);
        }

        $metricAliases = array_values(array_map(static function (array $metric): string {
            return (string) $metric['as'];
        }, $definition->metrics()));

        foreach ((array) ($query['sorts'] ?? []) as $sort) {
            if (!\is_array($sort)) {
                continue;
            }

            $field = $sort['field'] ?? null;
            if (!\is_string($field) || '' === $field) {
                continue;
            }

            if (isset($fieldNames[$field]) || \in_array($field, $groups, true) || \in_array($field, $metricAliases, true)) {
                continue;
            }

            throw new \InvalidArgumentException('Chart sort field ['.$field.'] does not exist.');
        }
    }
}
