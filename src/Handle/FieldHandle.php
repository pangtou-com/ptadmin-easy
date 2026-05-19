<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Handle;

use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;
use PTAdmin\Easy\Core\Schema\Definition\FieldDefinition;
use PTAdmin\Easy\Engine\Field\StandaloneResourceDefinition;
use PTAdmin\Easy\Engine\Resource\ResourceField;

final class FieldHandle
{
    /** @var array<string, mixed> */
    private $schema;

    /** @var ResourceField */
    private $field;

    /** @var FieldDefinition */
    private $definition;

    /**
     * @param array<string, mixed> $schema
     */
    public function __construct(array $schema)
    {
        $normalizer = new SchemaNormalizer();
        $normalized = $normalizer->normalize([
            'name' => '__standalone_field__',
            'fields' => [$schema],
        ]);

        $fieldSchema = (array) ($normalized['fields'][0] ?? []);
        $resource = new StandaloneResourceDefinition($normalized, []);
        $field = new ResourceField($fieldSchema, $resource);
        $resource = new StandaloneResourceDefinition($normalized, [$field->getName() => $field]);

        $this->schema = $fieldSchema;
        $this->field = new ResourceField($fieldSchema, $resource);
        $this->definition = new FieldDefinition($this->field);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return $this->definition->toArray();
    }

    /**
     * @return mixed
     */
    public function toStorage($value)
    {
        $type = $this->field->getType();
        if ('switch' === $type) {
            return $this->normalizeSwitchToStorage($value);
        }

        if ('key-value' === $type) {
            return $this->normalizeKeyValueToStorage($value);
        }

        if ($this->isMultiValueType($type)) {
            return $this->normalizeMultiValueToStorage($value);
        }

        if ('number' === $type) {
            if (null === $value || '' === $value) {
                return '0';
            }

            return (string) $value;
        }

        if (null === $value && \in_array($type, ['text', 'textarea', 'password', 'rich_text', 'color', 'icon_input'], true)) {
            return '';
        }

        $value = $this->field->setComponentAttributeValue(null, $value);

        return $this->field->getComponent()->saveFormat($value);
    }

    /**
     * @return mixed
     */
    public function toRuntime($value)
    {
        if (null === $value) {
            $default = $this->field->getDefault();
            if (null !== $default) {
                return $default;
            }
        }

        $type = $this->field->getType();
        if ('switch' === $type) {
            return $this->normalizeSwitchToRuntime($value);
        }

        if ('key-value' === $type) {
            return $this->normalizeKeyValueToRuntime($value);
        }

        if ($this->isMultiValueType($type)) {
            return $this->normalizeMultiValueToRuntime($value);
        }

        if ('number' === $type) {
            return $this->normalizeNumberToRuntime($value);
        }

        $value = $this->field->getComponent()->toFormat($value);

        return $this->field->getComponentAttributeValue(null, $value);
    }

    /**
     * @return mixed
     */
    public function defaultValue()
    {
        return $this->field->getDefault();
    }

    private function isMultiValueType(string $type): bool
    {
        if (\in_array($type, ['json', 'key-value', 'cascader', 'checkbox'], true)) {
            return true;
        }

        return 'select' === $type && true === (bool) $this->field->getMetadata('extends.multiple', false);
    }

    /**
     * @param mixed $value
     *
     * @return bool|float|int|string
     */
    private function normalizeSwitchToRuntime($value)
    {
        $active = $this->field->getMetadata('active-value', 1);
        $inactive = $this->field->getMetadata('inactive-value', 0);

        if ($this->switchValueEquals($value, $active)) {
            return $active;
        }

        if ($this->switchValueEquals($value, $inactive)) {
            return $inactive;
        }

        if (\is_bool($value)) {
            return $value ? $active : $inactive;
        }

        if (\is_numeric($value)) {
            return (float) $value > 0 ? $active : $inactive;
        }

        if (\is_string($value)) {
            $normalized = strtolower(trim($value));
            if (\in_array($normalized, ['true', 'yes', 'on'], true)) {
                return $active;
            }

            if (\in_array($normalized, ['false', 'no', 'off', ''], true)) {
                return $inactive;
            }
        }

        return $inactive;
    }

    /**
     * @param mixed $value
     *
     * @return bool|float|int|string
     */
    private function normalizeSwitchToStorage($value)
    {
        return $this->normalizeSwitchToRuntime($value);
    }

    /**
     * @param mixed $left
     * @param mixed $right
     */
    private function switchValueEquals($left, $right): bool
    {
        if ($left === $right) {
            return true;
        }

        if (\is_bool($left) || \is_bool($right)) {
            return $this->normalizeBooleanLike($left) === $this->normalizeBooleanLike($right);
        }

        if ((\is_numeric($left) || \is_string($left)) && (\is_numeric($right) || \is_string($right))) {
            return (string) $left === (string) $right;
        }

        return false;
    }

    /**
     * @param mixed $value
     */
    private function normalizeBooleanLike($value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            return (float) $value > 0;
        }

        if (\is_string($value)) {
            return \in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    /**
     * @param mixed $value
     *
     * @return array<string, mixed>
     */
    private function normalizeKeyValueToRuntime($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (\is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);
        if (\is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * @param mixed $value
     */
    private function normalizeKeyValueToStorage($value): string
    {
        if (null === $value || '' === $value) {
            return json_encode((object) [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (\is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $decoded = json_decode((string) $value, true);
        if (\is_array($decoded)) {
            return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return json_encode((object) [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param mixed $value
     *
     * @return array<int, mixed>
     */
    private function normalizeMultiValueToRuntime($value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (\is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true);
        if (\is_array($decoded)) {
            return array_values($decoded);
        }

        $segments = array_values(array_filter(explode(',', trim((string) $value, ',')), static function ($item): bool {
            return '' !== trim((string) $item);
        }));

        return $segments;
    }

    /**
     * @param mixed $value
     */
    private function normalizeMultiValueToStorage($value): string
    {
        if (null === $value || '' === $value) {
            return json_encode([], JSON_UNESCAPED_UNICODE);
        }

        if (\is_array($value)) {
            return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
        }

        $decoded = json_decode((string) $value, true);
        if (\is_array($decoded)) {
            return json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
        }

        return json_encode([(string) $value], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param mixed $value
     *
     * @return float|int
     */
    private function normalizeNumberToRuntime($value)
    {
        if (null === $value || '' === $value) {
            return 0;
        }

        if (\is_int($value) || \is_float($value)) {
            return $value;
        }

        $stringValue = trim((string) $value);
        if ('' === $stringValue) {
            return 0;
        }

        return false !== strpos($stringValue, '.') ? (float) $stringValue : (int) $stringValue;
    }
}
