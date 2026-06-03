<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use PTAdmin\Easy\Contracts\IComponent;
use PTAdmin\Easy\Contracts\IResource;
use PTAdmin\Easy\Contracts\IResourceField;
use PTAdmin\Easy\Components\Lib\SelectComponent;
use PTAdmin\Easy\Core\Schema\Definition\FieldDefinition;
use PTAdmin\Easy\Core\Schema\Mapping\FieldMappingResolver;
use PTAdmin\Easy\Core\Support\FieldTypeRegistry;

/**
 * 字段公开协议测试.
 *
 * 聚焦前后端约定的公开字段结构，避免内部兼容字段泄漏到蓝图输出。
 */
final class FieldProtocolTest extends TestCase
{
    public function test_it_exports_image_field_using_frontend_protocol_keys(): void
    {
        $field = new FieldDefinition($this->makeFieldMock([
            'name' => 'cover',
            'type' => 'image',
            'label' => '封面',
            'help' => '建议尺寸：300x200',
            'required' => false,
            'limit' => 1,
            'enableAlt' => true,
            'withCredentials' => false,
            'headers' => ['X-Token' => 'demo'],
        ]));

        $payload = $field->toArray();

        $this->assertSame('cover', $payload['name']);
        $this->assertSame('image', $payload['type']);
        $this->assertSame('封面', $payload['label']);
        $this->assertSame('建议尺寸：300x200', $payload['help']);
        $this->assertFalse($payload['required']);
        $this->assertSame(1, $payload['limit']);
        $this->assertTrue($payload['enableAlt']);
        $this->assertFalse($payload['withCredentials']);
        $this->assertSame(['X-Token' => 'demo'], $payload['headers']);
        $this->assertArrayNotHasKey('default', $payload);
        $this->assertArrayNotHasKey('comment', $payload);
        $this->assertArrayNotHasKey('is_required', $payload);
        $this->assertArrayNotHasKey('display', $payload);
        $this->assertArrayNotHasKey('mapping', $payload);
    }

    public function test_it_maps_attachment_field_as_multiple_array_storage(): void
    {
        $rawField = $this->makeFieldMock([
            'name' => 'attachments',
            'type' => 'attachment',
            'label' => '附件',
            'limit' => 3,
        ], 'json');
        $registry = new FieldTypeRegistry();
        $baseDefinition = new FieldDefinition($rawField);
        $resolvedDefinition = new FieldDefinition($rawField, $registry->resolve($baseDefinition));

        $mapping = (new FieldMappingResolver())->resolve($resolvedDefinition);

        $this->assertSame('attachment', $mapping['component']['type']);
        $this->assertSame('file', $mapping['component']['group']);
        $this->assertTrue($mapping['component']['multiple']);
        $this->assertSame('json', $mapping['storage']['column_definition']);
        $this->assertSame('array', $mapping['storage']['runtime_cast']);
        $this->assertFalse($mapping['query']['sortable']);
    }

    public function test_it_maps_checkbox_field_to_default_255_length_string_storage(): void
    {
        $rawField = $this->makeFieldMock([
            'name' => 'tags',
            'type' => 'checkbox',
            'label' => '标签',
        ], 'string');

        $registry = new FieldTypeRegistry();
        $baseDefinition = new FieldDefinition($rawField);
        $resolvedDefinition = new FieldDefinition($rawField, $registry->resolve($baseDefinition));
        $mapping = (new FieldMappingResolver())->resolve($resolvedDefinition);

        $this->assertSame('checkbox', $mapping['component']['type']);
        $this->assertTrue($mapping['component']['multiple']);
        $this->assertSame('varchar(255)', $mapping['storage']['column_definition']);
        $this->assertSame(255, $mapping['storage']['length']);
        $this->assertSame('array', $mapping['storage']['runtime_cast']);
    }

    public function test_it_keeps_checkbox_string_storage_at_least_255_when_length_is_smaller(): void
    {
        $rawField = $this->makeFieldMock([
            'name' => 'tags',
            'type' => 'checkbox',
            'label' => '标签',
            'length' => 30,
        ], 'string');

        $registry = new FieldTypeRegistry();
        $baseDefinition = new FieldDefinition($rawField);
        $resolvedDefinition = new FieldDefinition($rawField, $registry->resolve($baseDefinition));
        $mapping = (new FieldMappingResolver())->resolve($resolvedDefinition);

        $this->assertSame('varchar(255)', $mapping['storage']['column_definition']);
        $this->assertSame(255, $mapping['storage']['length']);
    }

    public function test_it_exports_text_and_password_formal_props(): void
    {
        $textField = new FieldDefinition($this->makeFieldMock([
            'name' => 'slug',
            'type' => 'text',
            'label' => '标识',
            'maxlength' => 120,
            'generator' => true,
            'slots' => [
                'prepend' => [
                    ['type' => 'select', 'name' => 'protocol'],
                ],
            ],
            'operator' => true,
        ], 'string'));

        $passwordField = new FieldDefinition($this->makeFieldMock([
            'name' => 'password',
            'type' => 'password',
            'label' => '密码',
            'show' => true,
            'generator' => true,
            'slots' => [
                'append' => [
                    ['type' => 'button', 'text' => '生成'],
                ],
            ],
        ], 'string'));

        $textPayload = $textField->toArray();
        $passwordPayload = $passwordField->toArray();

        $this->assertSame(120, $textPayload['maxlength']);
        $this->assertTrue($textPayload['generator']);
        $this->assertTrue($textPayload['operator']);
        $this->assertSame([
            'prepend' => [
                ['type' => 'select', 'name' => 'protocol'],
            ],
        ], $textPayload['slots']);

        $this->assertTrue($passwordPayload['show']);
        $this->assertTrue($passwordPayload['generator']);
        $this->assertSame([
            'append' => [
                ['type' => 'button', 'text' => '生成'],
            ],
        ], $passwordPayload['slots']);
    }

    private function makeFieldMock(
        array $metadata,
        string $columnType = 'json',
        ?IComponent $component = null
    ): IResourceField
    {
        if ('checkbox' === ($metadata['type'] ?? null)) {
            return $this->makeConcreteResourceField($metadata);
        }

        $resource = $this->createMock(IResource::class);
        $resource->method('toArray')->willReturn([
            'search_fields' => [],
            'title_field' => '',
        ]);

        if (null === $component) {
            $component = $this->createMock(IComponent::class);
            $component->method('getColumnType')->willReturn($columnType);
            $component->method('getColumnArguments')->willReturn([$metadata['name'] ?? 'field']);
        }

        $field = $this->createMock(IResourceField::class);
        $field->method('getName')->willReturn((string) ($metadata['name'] ?? 'field'));
        $field->method('getType')->willReturn((string) ($metadata['type'] ?? 'text'));
        $field->method('getLabel')->willReturn((string) ($metadata['label'] ?? '字段'));
        $field->method('getComment')->willReturn((string) ($metadata['comment'] ?? ($metadata['help'] ?? '')));
        $field->method('getDefault')->willReturn($metadata['default'] ?? ($metadata['defaultValue'] ?? null));
        $field->method('required')->willReturn(!empty($metadata['required']) ? 'required' : null);
        $field->method('isVirtual')->willReturn((bool) ($metadata['virtual'] ?? false));
        $field->method('isAppend')->willReturn((bool) ($metadata['append'] ?? false));
        $field->method('getRelation')->willReturn((array) ($metadata['relation'] ?? []));
        $field->method('getRules')->willReturn((array) ($metadata['rules'] ?? []));
        $field->method('getOptions')->willReturn((array) ($metadata['options'] ?? []));
        $field->method('getResource')->willReturn($resource);
        $field->method('getComponent')->willReturn($component);
        $field->method('getMetadata')->willReturnCallback(static function ($key = null, $default = null) use ($metadata) {
            return null === $key ? $metadata : data_get($metadata, $key, $default);
        });

        return $field;
    }

    private function makeConcreteResourceField(array $metadata): IResourceField
    {
        $resource = $this->createMock(IResource::class);
        $resource->method('toArray')->willReturn([
            'search_fields' => [],
            'title_field' => '',
        ]);

        return new class($metadata, $resource) implements IResourceField {
            /** @var array<string, mixed> */
            private $metadata;

            /** @var IResource */
            private $resource;

            /** @var null|IComponent */
            private $component = null;

            public function __construct(array $metadata, IResource $resource)
            {
                $this->metadata = $metadata;
                $this->resource = $resource;
            }

            public function getName(): string
            {
                return (string) ($this->metadata['name'] ?? 'field');
            }

            public function getType(): string
            {
                return (string) ($this->metadata['type'] ?? 'text');
            }

            public function getOptions(): array
            {
                return (array) ($this->metadata['options'] ?? []);
            }

            public function isRelation(): bool
            {
                return false;
            }

            public function isAppend(): bool
            {
                return false;
            }

            public function getAppendName(): string
            {
                return $this->getName().'_text';
            }

            public function getRelation(): array
            {
                return (array) ($this->metadata['relation'] ?? []);
            }

            public function getComment(): string
            {
                return (string) ($this->metadata['comment'] ?? ($this->metadata['help'] ?? ''));
            }

            public function getLabel(): string
            {
                return (string) ($this->metadata['label'] ?? '字段');
            }

            public function getRules($id): array
            {
                return (array) ($this->metadata['rules'] ?? []);
            }

            public function getComponentAttributeValue($model, $val)
            {
                return $val;
            }

            public function setComponentAttributeValue($model, $val)
            {
                return $val;
            }

            public function getDefault()
            {
                return $this->metadata['default'] ?? ($this->metadata['defaultValue'] ?? null);
            }

            public function required(): ?string
            {
                return !empty($this->metadata['required']) ? 'required' : null;
            }

            public function getMetadata($key = null, $default = null)
            {
                return null === $key ? $this->metadata : data_get($this->metadata, $key, $default);
            }

            public function exists(): bool
            {
                return false;
            }

            public function isVirtual(): bool
            {
                return false;
            }

            public function getResource(): IResource
            {
                return $this->resource;
            }

            public function getComponent(): IComponent
            {
                if (null === $this->component) {
                    $this->component = new SelectComponent($this);
                }

                return $this->component;
            }

            public function isMultiple(): bool
            {
                return 'checkbox' === $this->getType();
            }

            public function isSourceResource(): bool
            {
                return false;
            }
        };
    }
}
