<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaValidator;

/**
 * Schema 校验器测试.
 *
 * 用于确认前端提交的资源 schema 会在发布前被正确校验。
 */
final class SchemaValidatorTest extends TestCase
{
    public function test_it_accepts_valid_schema(): void
    {
        $validator = new SchemaValidator();

        $validator->validate([
            'name' => 'articles',
            'title_field' => 'title',
            'search_fields' => ['title'],
            'order' => ['title' => 'desc'],
            'fields' => [
                [
                    'type' => 'section',
                    'label' => '基础',
                    'children' => [
                        ['name' => 'title', 'type' => 'text', 'label' => '标题'],
                        ['name' => 'status', 'type' => 'radio', 'label' => '状态'],
                        ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户'],
                    ],
                ],
            ],
            'charts' => [
                [
                    'title' => 'Status Summary',
                    'dimension' => 'status',
                    'metrics' => [
                        ['type' => 'count', 'label' => '总数'],
                        ['type' => 'sum', 'field' => 'tenant_id', 'label' => '租户和值'],
                    ],
                ],
            ],
        ]);

        $this->assertTrue(true);
    }

    public function test_it_rejects_duplicate_field_names(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema field [title] is duplicated.');

        $validator->validate([
            'name' => 'articles',
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'label' => '标题'],
                ['name' => 'title', 'type' => 'text', 'label' => '标题2'],
            ],
        ]);
    }

    public function test_it_rejects_unknown_search_field(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema search field [missing] does not exist.');

        $validator->validate([
            'name' => 'articles',
            'search_fields' => ['missing'],
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'label' => '标题'],
            ],
        ]);
    }

    public function test_it_rejects_unsupported_field_type(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema field [title] type [legacy_editor] is not supported.');

        $validator->validate([
            'name' => 'articles',
            'fields' => [
                ['name' => 'title', 'type' => 'legacy_editor', 'label' => '标题'],
            ],
        ]);
    }

    public function test_it_rejects_unknown_chart_field_inside_layout_schema(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chart metric field [missing] does not exist.');

        $validator->validate([
            'name' => 'articles',
            'fields' => [
                [
                    'type' => 'section',
                    'children' => [
                        ['name' => 'title', 'type' => 'text', 'label' => '标题'],
                    ],
                ],
            ],
            'charts' => [
                [
                    'title' => 'Broken',
                    'metrics' => [
                        ['type' => 'sum', 'field' => 'missing'],
                    ],
                ],
            ],
        ]);
    }

    public function test_it_uses_name_in_required_error_message(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema name is required and must use letters, numbers, and underscores.');

        $validator->validate([
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'label' => '标题'],
            ],
        ]);
    }

    public function test_it_rejects_field_with_both_secret_and_mask(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema field [secret] cannot define both secret and mask.');

        $validator->validate([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'secret',
                    'type' => 'text',
                    'label' => '密钥',
                    'secret' => true,
                    'mask' => [
                        'strategy' => 'phone',
                    ],
                ],
            ],
        ]);
    }

    public function test_it_rejects_sensitive_config_on_non_text_field(): void
    {
        $validator = new SchemaValidator();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema field [cover] sensitive config is only supported for text/textarea.');

        $validator->validate([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'cover',
                    'type' => 'image',
                    'label' => '封面',
                    'mask' => true,
                ],
            ],
        ]);
    }
}
