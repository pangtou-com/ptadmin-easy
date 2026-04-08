<?php

declare(strict_types=1);

namespace PTAdmin\Easy\Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use PTAdmin\Easy\Core\Schema\Compiler\SchemaNormalizer;

/**
 * Schema 标准化器测试.
 *
 * 用于确认拖拽生成的混合节点树会被正确拆分为数据字段与布局树。
 */
final class SchemaNormalizerTest extends TestCase
{
    public function test_it_extracts_fields_from_mixed_layout_nodes(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                [
                    'type' => 'section',
                    'label' => '基础信息',
                    'children' => [
                        ['name' => 'title', 'type' => 'text', 'label' => '标题'],
                        [
                            'type' => 'grid',
                            'columns' => [
                                [
                                    'children' => [
                                        ['name' => 'status', 'type' => 'radio', 'label' => '状态'],
                                    ],
                                ],
                                [
                                    'children' => [
                                        ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('articles', $schema['name']);
        $this->assertSame(['title', 'status', 'tenant_id'], array_column($schema['fields'], 'name'));
        $this->assertArrayHasKey('layout', $schema);
        $this->assertCount(1, $schema['layout']['nodes']);
    }

    public function test_it_keeps_explicit_field_nodes_and_layout_tree_together(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                ['name' => 'title', 'type' => 'text', 'label' => '标题'],
            ],
            'layout' => [
                'nodes' => [
                    [
                        'type' => 'tabs',
                        'children' => [
                            ['name' => 'title', 'type' => 'text', 'label' => '标题'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('articles', $schema['name']);
        $this->assertSame(['title'], array_column($schema['fields'], 'name'));
        $this->assertArrayHasKey('layout', $schema);
        $this->assertCount(1, $schema['layout']['nodes']);
    }

    public function test_it_normalizes_frontend_builder_aliases(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'category',
            'module' => 'cms',
            'fields' => [
                [
                    'type' => 'row',
                    'children' => [
                        [
                            'type' => 'col',
                            'children' => [
                                [
                                    'name' => 'status',
                                    'type' => 'switch',
                                    'label' => '状态',
                                    'required' => true,
                                    'def' => 1,
                                ],
                                [
                                    'name' => 'cover',
                                    'type' => 'resource',
                                    'label' => '封面',
                                    'help' => '图片资源',
                                ],
                                [
                                    'name' => 'parent_ids',
                                    'type' => 'cascader',
                                    'label' => '父级栏目',
                                    'maxlength' => 255,
                                    'pl' => '请选择',
                                    'max' => 255,
                                ],
                                [
                                    'name' => 'alias',
                                    'type' => 'text',
                                    'label' => '别名',
                                    'maxlength' => 100,
                                    'rules' => [
                                        [
                                            'min' => 2,
                                            'max' => 100,
                                        ],
                                        [
                                            'pattern' => '^[a-zA-Z0-9_-]+$',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('category', $schema['name']);
        $this->assertSame(['status', 'cover', 'parent_ids', 'alias'], array_column($schema['fields'], 'name'));
        $this->assertSame(1, $schema['fields'][0]['is_required']);
        $this->assertSame(1, $schema['fields'][0]['default']);
        $this->assertSame([
            ['label' => '是', 'value' => 1],
            ['label' => '否', 'value' => 0],
        ], $schema['fields'][0]['options']);
        $this->assertSame('图片资源', $schema['fields'][1]['comment']);
        $this->assertSame('请选择', $schema['fields'][2]['placeholder']);
        $this->assertSame(255, $schema['fields'][2]['length']);
        $this->assertSame(255, $schema['fields'][2]['extends']['max']);
        $this->assertSame([
            'min:2',
            'max:100',
            'regex:/^[a-zA-Z0-9_-]+$/',
        ], $schema['fields'][3]['rules']);
    }

    public function test_it_normalizes_builder_rule_messages_and_display_state(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'alias',
                    'type' => 'text',
                    'label' => '别名',
                    'readonly' => true,
                    'scenes' => [
                        'form' => ['visible' => true],
                        'table' => ['visible' => false],
                    ],
                    'rules' => [
                        [
                            'required' => true,
                            'message' => '别名不能为空',
                        ],
                        [
                            'pattern' => '^[a-z0-9_-]+$',
                            'message' => '别名格式错误',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertSame('articles', $schema['name']);
        $this->assertSame(['required', 'regex:/^[a-z0-9_-]+$/'], $schema['fields'][0]['rules']);
        $this->assertSame([
            'required' => '别名不能为空',
            'regex' => '别名格式错误',
        ], $schema['fields'][0]['rule_messages']);
        $this->assertSame([
            'hidden' => false,
            'readonly' => true,
            'disabled' => false,
            'editable' => false,
            'form' => [
                'visible' => true,
                'editable' => false,
            ],
            'table' => [
                'visible' => false,
            ],
            'detail' => [
                'visible' => true,
            ],
        ], $schema['fields'][0]['display']);
    }

    public function test_it_normalizes_field_level_relation_protocol(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'category_id',
                    'type' => 'select',
                    'label' => '所属分类',
                    'relation' => [
                        'type' => 'belongs_to',
                        'resource' => 'categories',
                        'value_field' => 'id',
                        'label_field' => 'title',
                        'filters' => ['status' => 1],
                        'append_name' => '__category_text',
                    ],
                ],
            ],
        ]);

        $this->assertSame('articles', $schema['name']);
        $this->assertSame('belongsTo', $schema['fields'][0]['relation']['kind']);
        $this->assertSame('articles', $schema['name']);
        $this->assertSame('categories', $schema['fields'][0]['relation']['table']);
        $this->assertSame('id', $schema['fields'][0]['relation']['value']);
        $this->assertSame('title', $schema['fields'][0]['relation']['label']);
        $this->assertSame(['status' => 1], $schema['fields'][0]['relation']['filter']);
        $this->assertSame('__category_text', $schema['fields'][0]['relation']['append_name']);
        $this->assertSame('resource', $schema['fields'][0]['extends']['type']);
        $this->assertSame('categories', $schema['fields'][0]['extends']['table']);
        $this->assertSame('belongsTo', $schema['fields'][0]['extends']['relation_kind']);
    }

    public function test_it_merges_schema_level_relation_protocol_into_fields(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'category_id',
                    'type' => 'select',
                    'label' => '所属分类',
                ],
                [
                    'name' => 'author_id',
                    'type' => 'link',
                    'label' => '作者',
                ],
            ],
            'relations' => [
                'belongs_to' => [
                    'category_id' => [
                        'resource' => 'categories',
                        'value_field' => 'id',
                        'label_field' => 'title',
                    ],
                    'author_id' => [
                        'resource' => 'users',
                        'value_field' => 'id',
                        'label_field' => 'nickname',
                    ],
                ],
            ],
        ]);

        $this->assertSame('categories', $schema['fields'][0]['relation']['table']);
        $this->assertSame('users', $schema['fields'][1]['relation']['table']);
        $this->assertSame('nickname', $schema['fields'][1]['relation']['label']);
        $this->assertSame('categories', $schema['relations']['category_id']['table']);
        $this->assertSame('users', $schema['relations']['author_id']['table']);
    }

    public function test_it_normalizes_has_many_relation_protocol(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'comments',
                    'type' => 'table',
                    'label' => '评论',
                    'relation' => [
                        'type' => 'has_many',
                        'resource' => 'article_comments',
                        'foreign_key' => 'article_id',
                        'local_key' => 'id',
                        'on_delete' => 'restrict',
                    ],
                ],
            ],
        ]);

        $this->assertSame('articles', $schema['name']);
        $this->assertSame('hasMany', $schema['fields'][0]['relation']['kind']);
        $this->assertSame('article_comments', $schema['fields'][0]['relation']['table']);
        $this->assertSame('article_id', $schema['fields'][0]['relation']['foreign_key']);
        $this->assertSame('id', $schema['fields'][0]['relation']['local_key']);
        $this->assertSame('restrict', $schema['fields'][0]['relation']['on_delete']);
        $this->assertSame('hasMany', $schema['fields'][0]['extends']['relation_kind']);
        $this->assertSame('restrict', $schema['fields'][0]['extends']['on_delete']);
    }

    public function test_it_normalizes_has_one_relation_protocol(): void
    {
        $normalizer = new SchemaNormalizer();

        $schema = $normalizer->normalize([
            'name' => 'articles',
            'fields' => [
                [
                    'name' => 'seo',
                    'type' => 'table',
                    'label' => 'SEO信息',
                    'relation' => [
                        'type' => 'has_one',
                        'resource' => 'article_seo',
                        'foreign_key' => 'article_id',
                        'local_key' => 'id',
                        'on_delete' => 'set-null',
                    ],
                ],
            ],
        ]);

        $this->assertSame('articles', $schema['name']);
        $this->assertSame('hasOne', $schema['fields'][0]['relation']['kind']);
        $this->assertSame('article_seo', $schema['fields'][0]['relation']['table']);
        $this->assertSame('article_id', $schema['fields'][0]['relation']['foreign_key']);
        $this->assertSame('id', $schema['fields'][0]['relation']['local_key']);
        $this->assertSame('set_null', $schema['fields'][0]['relation']['on_delete']);
        $this->assertSame('hasOne', $schema['fields'][0]['extends']['type']);
        $this->assertSame('hasOne', $schema['fields'][0]['extends']['relation_kind']);
        $this->assertSame('set_null', $schema['fields'][0]['extends']['on_delete']);
    }

}
