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

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PTAdmin\Easy\Core\Runtime\ExecutionContext;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Exceptions\EasyException;
use PTAdmin\Easy\Exceptions\EasyValidateException;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('publishes schema versions and supports rollback with schema sync', function (): void {
    $table = easyRuntimeTable('publish');
    $schemaV1 = easyRuntimeSchema($table);
    $schemaV2 = easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'length' => 120,
            ],
        ]),
    ]);

    $release = Easy::release($table);
    $plan = $release->plan($schemaV1);

    expect($plan->operations()['create_table'])->toBeTrue()
        ->and($plan->operations()['add_fields'])->toContain('title', 'tenant_id', 'status');

    $publishedV1 = $release->publish($schemaV1, ['remark' => 'publish v1']);

    expect($publishedV1->synced())->toBeTrue()
        ->and(Schema::hasTable($table))->toBeTrue()
        ->and($release->current()['remark'])->toBe('publish v1');

    $draft = $release->saveDraft($schemaV2, ['remark' => 'draft v2']);
    $plannedV2 = $release->plan($schemaV2);

    expect($draft['status'])->toBe('draft')
        ->and($draft['persisted'])->toBeTrue()
        ->and($release->latestDraft()['remark'])->toBe('draft v2')
        ->and($plannedV2->operations()['add_fields'])->toContain('excerpt');

    $publishedV2 = $release->publish($schemaV2, ['remark' => 'publish v2']);
    $history = $release->history();
    $historyByRemark = collect($history)->keyBy('remark');

    expect($publishedV2->version()['status'])->toBe('published')
        ->and(Schema::hasColumn($table, 'excerpt'))->toBeTrue()
        ->and($release->current()['remark'])->toBe('publish v2')
        ->and($release->latestDraft())->toBeNull()
        ->and($history)->toHaveCount(3)
        ->and(data_get($historyByRemark->get('publish v2'), 'status'))->toBe('published')
        ->and(data_get($historyByRemark->get('publish v2'), 'is_current'))->toBe(1)
        ->and(data_get($historyByRemark->get('draft v2'), 'status'))->toBe('superseded')
        ->and(data_get($historyByRemark->get('draft v2'), 'is_current'))->toBe(0)
        ->and(data_get($historyByRemark->get('publish v1'), 'status'))->toBe('archived')
        ->and(data_get($historyByRemark->get('publish v1'), 'is_current'))->toBe(0);

    $rollback = $release->rollbackTo((int) $publishedV1->version()['id'], ['sync' => true, 'force' => true]);
    $historyAfterRollback = collect($release->history())->keyBy('remark');

    expect($rollback->synced())->toBeTrue()
        ->and($rollback->plan()->isDestructive())->toBeTrue()
        ->and($release->current()['id'])->toBe($publishedV1->version()['id'])
        ->and(Schema::hasColumn($table, 'excerpt'))->toBeFalse()
        ->and(data_get($historyAfterRollback->get('publish v1'), 'status'))->toBe('published')
        ->and(data_get($historyAfterRollback->get('publish v1'), 'is_current'))->toBe(1)
        ->and(data_get($historyAfterRollback->get('publish v2'), 'status'))->toBe('archived')
        ->and(data_get($historyAfterRollback->get('publish v2'), 'is_current'))->toBe(0);
});

it('exposes split doc schema release and table handles', function (): void {
    $table = easyRuntimeTable('split_handles');
    $schema = easyRuntimeSchema($table, [
        'module' => 'cms',
    ]);

    $schemaHandle = Easy::schema($schema);
    $releaseHandle = Easy::release($table);
    $tableHandle = Easy::table($schema);

    expect($schemaHandle->blueprint())->toHaveKey('resource')
        ->and(data_get($schemaHandle->fieldMappings(), 'title.storage.column_definition'))->toBe('varchar(100)')
        ->and($tableHandle->tableExists($table))->toBeFalse();

    $plan = $releaseHandle->plan($schema);
    $published = $releaseHandle->publish($schema);
    $created = Easy::doc($table)->create([
        'title' => 'split api',
        'tenant_id' => 1,
        'status' => 1,
    ]);

    expect($plan->operations()['create_table'])->toBeTrue()
        ->and($published->synced())->toBeTrue()
        ->and($releaseHandle->current()['resource'])->toBe($table)
        ->and($created->title)->toBe('split api')
        ->and(Easy::doc($table)->detail($created->id)->title)->toBe('split api');
});

it('enforces schema permissions during runtime execution', function (): void {
    $table = easyRuntimeTable('permission');
    $schema = easyRuntimeSchema($table, [
        'permissions' => [
            'create' => 'article.create',
            'update' => [
                'abilities' => ['article.update'],
                'roles' => ['editor'],
                'all' => true,
            ],
            'delete' => false,
        ],
    ]);

    Easy::release($table)->publish($schema);

    expect(function () use ($table): void {
        Easy::doc($table)->create([
            'title' => 'unauthorized',
            'tenant_id' => 1,
            'status' => 1,
        ], new ExecutionContext());
    })->toThrow(EasyException::class, 'Unauthorized operation [create] on resource ['.$table.'].');

    $created = Easy::doc($table)->create([
        'title' => 'allowed',
        'tenant_id' => 1,
        'status' => 1,
    ], new ExecutionContext([
        'abilities' => ['article.create', 'article.update'],
        'roles' => ['editor'],
    ]));

    expect($created)->not->toBeNull()
        ->and($created->title)->toBe('allowed')
        ->and(function () use ($table, $created): void {
            Easy::doc($table)->delete($created->id, new ExecutionContext([
                'abilities' => ['article.create', 'article.update'],
                'roles' => ['editor'],
            ]));
        })->toThrow(EasyException::class, 'Unauthorized operation [delete] on resource ['.$table.'].');
});

it('applies scopes to reads and writes and only audits successful writes', function (): void {
    $table = easyRuntimeTable('scope');
    $schema = easyRuntimeSchema($table);
    $handle = Easy::doc($table);

    Easy::release($table)->publish($schema);

    Easy::scopes()->on('*', function ($builder, $definition, ExecutionContext $context): void {
        $builder->where('tenant_id', $context->get('tenant_id'));
    });
    $handle->scope('list', function ($builder): void {
        $builder->where('status', 1);
    });

    $tenantOneContext = new ExecutionContext(['tenant_id' => 1]);
    $tenantTwoContext = new ExecutionContext(['tenant_id' => 2]);

    $visible = $handle->create([
        'title' => 'visible',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $hiddenByListScope = $handle->create([
        'title' => 'inactive',
        'tenant_id' => 1,
        'status' => 0,
    ]);
    $otherTenant = $handle->create([
        'title' => 'other tenant',
        'tenant_id' => 2,
        'status' => 1,
    ]);

    $listed = $handle->lists([
        'sorts' => [
            ['field' => 'id', 'direction' => 'desc'],
        ],
    ], $tenantOneContext);

    expect(array_column($listed, 'title'))->toBe(['visible'])
        ->and($handle->detail($visible->id, $tenantOneContext))->not->toBeNull()
        ->and($handle->detail($otherTenant->id, $tenantOneContext))->toBeNull();

    $blockedUpdate = $handle->update($otherTenant->id, ['title' => 'blocked'], $tenantOneContext);
    $blockedDelete = $handle->delete($otherTenant->id, $tenantOneContext);
    $allowedDelete = $handle->delete($otherTenant->id, $tenantTwoContext);

    expect($blockedUpdate)->toBeNull()
        ->and($blockedDelete)->toBeFalse()
        ->and($allowedDelete)->toBeTrue()
        ->and($handle->detail($hiddenByListScope->id, $tenantOneContext))->not->toBeNull();

    $auditOperations = DB::table('audit_logs')
        ->where('resource', $table)
        ->orderBy('id')
        ->pluck('operation')
        ->all()
    ;

    expect($auditOperations)->toBe(['create', 'create', 'create', 'delete']);
});

it('supports list query dsl and legacy filter order format', function (): void {
    $table = easyRuntimeTable('query');
    $handle = Easy::doc($table);

    Easy::release($table)->publish(easyRuntimeSchema($table));

    $handle->create([
        'title' => 'alpha',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'beta',
        'tenant_id' => 1,
        'status' => 0,
    ]);
    $handle->create([
        'title' => 'gamma',
        'tenant_id' => 1,
        'status' => 1,
    ]);

    $dslResult = $handle->lists([
        'filters' => [
            ['field' => 'status', 'operator' => '=', 'value' => 1],
            ['field' => 'title', 'operator' => 'like', 'value' => '%a%'],
        ],
        'sorts' => [
            ['field' => 'id', 'direction' => 'desc'],
        ],
        'limit' => 1,
        'page' => 1,
    ]);

    $legacyResult = $handle->lists([
        'filter' => [
            'status' => 1,
        ],
        'order' => [
            'id' => 'asc',
        ],
    ]);

    $paginated = $handle->lists([
        'filter' => [
            'status' => 1,
        ],
        'order' => [
            'id' => 'asc',
        ],
        'limit' => 1,
        'page' => 2,
        'paginate' => true,
    ]);

    expect(array_column($dslResult, 'title'))->toBe(['gamma'])
        ->and(array_column($legacyResult, 'title'))->toBe(['alpha', 'gamma'])
        ->and($paginated['current_page'])->toBe(2)
        ->and($paginated['per_page'])->toBe(1)
        ->and($paginated['total'])->toBe(2)
        ->and(array_column($paginated['data'], 'title'))->toBe(['gamma']);
});

it('uses schema search_fields and default order for keyword queries', function (): void {
    $table = easyRuntimeTable('keyword');
    $schema = easyRuntimeSchema($table, [
        'search_fields' => ['title'],
        'order' => ['id' => 'desc'],
    ]);
    $handle = Easy::doc($table);

    Easy::release($table)->publish($schema);
    $handle->create([
        'title' => 'alpha',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'beta',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'gamma',
        'tenant_id' => 1,
        'status' => 1,
    ]);

    $defaultOrdered = $handle->lists();
    $keywordResult = $handle->lists([
        'keyword' => 'mm',
    ]);

    expect(array_column($defaultOrdered, 'title'))->toBe(['gamma', 'beta', 'alpha'])
        ->and(array_column($keywordResult, 'title'))->toBe(['gamma']);
});

it('supports contains filters for multi select values', function (): void {
    $table = easyRuntimeTable('contains');
    $schema = easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'tags',
                'type' => 'checkbox',
                'label' => '标签',
                'options' => [
                    ['label' => 'Red', 'value' => 'red'],
                    ['label' => 'Blue', 'value' => 'blue'],
                    ['label' => 'Green', 'value' => 'green'],
                ],
            ],
        ]),
    ]);
    $handle = Easy::doc($table);

    Easy::release($table)->publish($schema);
    $handle->create([
        'title' => 'alpha',
        'tenant_id' => 1,
        'status' => 1,
        'tags' => ['red', 'green'],
    ]);
    $handle->create([
        'title' => 'beta',
        'tenant_id' => 1,
        'status' => 1,
        'tags' => ['blue'],
    ]);
    $handle->create([
        'title' => 'gamma',
        'tenant_id' => 1,
        'status' => 1,
        'tags' => ['green'],
    ]);

    $result = $handle->lists([
        'filters' => [
            ['field' => 'tags', 'operator' => 'contains', 'value' => 'green'],
        ],
        'sorts' => [
            ['field' => 'id', 'direction' => 'asc'],
        ],
    ]);

    expect(array_column($result, 'title'))->toBe(['alpha', 'gamma']);
});

it('resolves relation display text for resource-backed select and link fields', function (): void {
    $categoryTable = easyRuntimeTable('category');
    $articleTable = easyRuntimeTable('article_relation');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
        'title_field' => 'title',
        'search_fields' => ['title'],
        'order' => ['id' => 'asc'],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'extends' => [
                    'type' => 'resource',
                    'table' => $categoryTable,
                    'value' => 'id',
                    'label' => 'title',
                    'filter' => [
                        'status' => 1,
                    ],
                ],
            ],
            [
                'name' => 'category_code',
                'type' => 'link',
                'label' => '分类编码',
                'extends' => [
                    'table' => $categoryTable,
                    'value' => 'id',
                    'label' => 'title',
                    'filter' => [
                        'status' => 1,
                    ],
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $category = Easy::doc($categoryTable)->create([
        'title' => '新闻中心',
        'status' => 1,
    ]);
    $secondaryCategory = Easy::doc($categoryTable)->create([
        'title' => '产品中心',
        'status' => 1,
    ]);

    $created = Easy::doc($articleTable)->create([
        'title' => 'relation article',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $category->id,
        'category_code' => $category->id,
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'relation article 2',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $secondaryCategory->id,
        'category_code' => $secondaryCategory->id,
    ]);

    $detail = Easy::doc($articleTable)->detail($created->id);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $listed = Easy::doc($articleTable)->lists();
    $relationQueryCount = collect(DB::getQueryLog())->filter(function (array $entry) use ($categoryTable): bool {
        return false !== strpos((string) ($entry['query'] ?? ''), $categoryTable);
    })->count();
    DB::disableQueryLog();

    expect($detail->__category_id_text)->toBe('新闻中心')
        ->and($detail->__category_code_text)->toBe('新闻中心')
        ->and(data_get($listed, '0.__category_id_text'))->toBe('新闻中心')
        ->and(data_get($listed, '0.__category_code_text'))->toBe('新闻中心')
        ->and(data_get($listed, '1.__category_id_text'))->toBe('产品中心')
        ->and(data_get($listed, '1.__category_code_text'))->toBe('产品中心')
        ->and($relationQueryCount)->toBe(1);
});

it('filters list results by relation append text fields', function (): void {
    $categoryTable = easyRuntimeTable('category_filter');
    $articleTable = easyRuntimeTable('article_relation_filter');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
        'title_field' => 'title',
        'search_fields' => ['title'],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'extends' => [
                    'type' => 'resource',
                    'table' => $categoryTable,
                    'value' => 'id',
                    'label' => 'title',
                    'filter' => [
                        'status' => 1,
                    ],
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $news = Easy::doc($categoryTable)->create([
        'title' => '新闻中心',
        'status' => 1,
    ]);
    $product = Easy::doc($categoryTable)->create([
        'title' => '产品中心',
        'status' => 1,
    ]);

    Easy::doc($articleTable)->create([
        'title' => 'news article',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $news->id,
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'product article',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $product->id,
    ]);

    $filtered = Easy::doc($articleTable)->lists([
        'filters' => [
            ['field' => '__category_id_text', 'operator' => 'like', 'value' => '%新闻%'],
        ],
    ]);

    expect($filtered)->toHaveCount(1)
        ->and(data_get($filtered, '0.title'))->toBe('news article')
        ->and(data_get($filtered, '0.__category_id_text'))->toBe('新闻中心');
});

it('sorts list results by relation append text fields', function (): void {
    $categoryTable = easyRuntimeTable('category_sort');
    $articleTable = easyRuntimeTable('article_relation_sort');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
        ],
        'title_field' => 'title',
        'search_fields' => ['title'],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'extends' => [
                    'type' => 'resource',
                    'table' => $categoryTable,
                    'value' => 'id',
                    'label' => 'title',
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $beta = Easy::doc($categoryTable)->create(['title' => 'Beta']);
    $alpha = Easy::doc($categoryTable)->create(['title' => 'Alpha']);
    $gamma = Easy::doc($categoryTable)->create(['title' => 'Gamma']);

    Easy::doc($articleTable)->create([
        'title' => 'article beta',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $beta->id,
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'article alpha',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $alpha->id,
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'article gamma',
        'tenant_id' => 1,
        'status' => 1,
        'category_id' => $gamma->id,
    ]);

    $ascending = Easy::doc($articleTable)->lists([
        'sorts' => [
            ['field' => '__category_id_text', 'direction' => 'asc'],
        ],
    ]);
    $descending = Easy::doc($articleTable)->lists([
        'sorts' => [
            ['field' => '__category_id_text', 'direction' => 'desc'],
        ],
    ]);

    expect(array_column($ascending, '__category_id_text'))->toBe(['Alpha', 'Beta', 'Gamma'])
        ->and(array_column($ascending, 'title'))->toBe(['article alpha', 'article beta', 'article gamma'])
        ->and(array_column($descending, '__category_id_text'))->toBe(['Gamma', 'Beta', 'Alpha'])
        ->and(array_column($descending, 'title'))->toBe(['article gamma', 'article beta', 'article alpha']);
});

it('supports normalized relation protocol aliases in runtime schemas', function (): void {
    $categoryTable = easyRuntimeTable('category_protocol');
    $articleTable = easyRuntimeTable('article_relation_protocol');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'relation' => [
                    'type' => 'belongs_to',
                    'resource' => $categoryTable,
                    'value_field' => 'id',
                    'label_field' => 'title',
                    'filters' => [
                        'status' => 1,
                    ],
                ],
            ],
            [
                'name' => 'category_link',
                'type' => 'link',
                'label' => '分类链接',
            ],
        ],
        'relations' => [
            'belongs_to' => [
                'category_link' => [
                    'resource' => $categoryTable,
                    'value_field' => 'id',
                    'label_field' => 'title',
                    'filters' => [
                        'status' => 1,
                    ],
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $category = Easy::doc($categoryTable)->create([
        'title' => '协议分类',
        'status' => 1,
    ]);

    $created = Easy::doc($articleTable)->create([
        'title' => 'protocol article',
        'tenant_id' => 1,
        'category_id' => $category->id,
        'category_link' => $category->id,
    ]);
    $detail = Easy::doc($articleTable)->detail($created->id);
    $schemaHandle = Easy::schema($articleSchema);
    $blueprint = $schemaHandle->blueprint();

    expect($detail->__category_id_text)->toBe('协议分类')
        ->and($detail->__category_link_text)->toBe('协议分类')
        ->and(data_get($blueprint, 'fields.2.relation.kind'))->toBe('belongsTo')
        ->and(data_get($blueprint, 'fields.2.relation.table'))->toBe($categoryTable)
        ->and(data_get($blueprint, 'fields.3.relation.kind'))->toBe('belongsTo')
        ->and(data_get($blueprint, 'fields.3.relation.table'))->toBe($categoryTable);
});

it('loads belongsTo relation objects through query parameters and chain with enhancements', function (): void {
    $categoryTable = easyRuntimeTable('category_with_object');
    $articleTable = easyRuntimeTable('article_with_object');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'relation' => [
                    'type' => 'belongs_to',
                    'resource' => $categoryTable,
                    'value_field' => 'id',
                    'label_field' => 'title',
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $category = Easy::doc($categoryTable)->create([
        'title' => '栏目分类',
        'status' => 1,
    ]);
    $created = Easy::doc($articleTable)->create([
        'title' => 'belongsTo article',
        'tenant_id' => 1,
        'category_id' => $category->id,
    ]);

    $plain = Easy::doc($articleTable)->detail($created->id);
    $detail = Easy::doc($articleTable)->with('category:id,title')->detail($created->id);
    $listed = Easy::doc($articleTable)->lists([
        'with' => ['category:id,title'],
    ]);
    $listedByField = Easy::doc($articleTable)->lists([
        'with' => ['category_id:id,title'],
    ]);
    $allRelations = Easy::doc($articleTable)->lists([
        'with_relations' => true,
    ]);
    $without = Easy::doc($articleTable)->lists([
        'with_relations' => true,
        'without' => ['category'],
    ]);

    expect($plain->category)->toBeNull()
        ->and(data_get($detail->category, 'id'))->toBe($category->id)
        ->and(data_get($detail->category, 'title'))->toBe('栏目分类')
        ->and(data_get($listed, '0.category.title'))->toBe('栏目分类')
        ->and(data_get($listedByField, '0.category.title'))->toBe('栏目分类')
        ->and(data_get($allRelations, '0.category.status'))->toBe(1)
        ->and(data_get($without, '0.category'))->toBeNull();
});

it('falls back to private relation output keys when belongsTo alias collides with real fields', function (): void {
    $categoryTable = easyRuntimeTable('category_with_collision');
    $articleTable = easyRuntimeTable('article_with_collision');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'category',
                'type' => 'text',
                'label' => '业务分类名',
                'length' => 100,
            ],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'relation' => [
                    'type' => 'belongs_to',
                    'resource' => $categoryTable,
                    'value_field' => 'id',
                    'label_field' => 'title',
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $category = Easy::doc($categoryTable)->create([
        'title' => '冲突分类',
    ]);
    $created = Easy::doc($articleTable)->create([
        'title' => 'collision article',
        'tenant_id' => 1,
        'category' => '主表原始值',
        'category_id' => $category->id,
    ]);

    $detail = Easy::doc($articleTable)->with('category:id,title')->detail($created->id);

    expect($detail->category)->toBe('主表原始值')
        ->and(data_get($detail->__relation_category, 'title'))->toBe('冲突分类');
});

it('creates loads updates and clears hasOne child records through normalized relation protocol', function (): void {
    $seoTable = easyRuntimeTable('article_seo');
    $articleTable = easyRuntimeTable('article_has_one');

    $seoSchema = [
        'title' => 'Runtime '.$seoTable,
        'module' => 'App',
        'name' => $seoTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'article_id',
                'type' => 'number',
                'label' => '文章ID',
                'is_required' => 1,
            ],
            [
                'name' => 'summary',
                'type' => 'text',
                'label' => 'SEO描述',
                'is_required' => 1,
                'length' => 255,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'seo',
                'type' => 'table',
                'label' => 'SEO配置',
                'relation' => [
                    'type' => 'has_one',
                    'resource' => $seoTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($seoTable)->publish($seoSchema);
    Easy::release($articleTable)->publish($articleSchema);

    $created = Easy::doc($articleTable)->create([
        'title' => 'has one article',
        'tenant_id' => 1,
        'seo' => [
            'summary' => '第一版描述',
            'status' => 1,
        ],
    ]);

    $plainDetail = Easy::doc($articleTable)->detail($created->id);
    $detail = Easy::doc($articleTable)->with('seo:id,summary')->detail($created->id);
    $listed = Easy::doc($articleTable)->lists([
        'with_relations' => true,
    ]);

    expect($created->seo)->toBeNull()
        ->and($plainDetail->seo)->toBeNull()
        ->and(data_get($detail->seo, 'summary'))->toBe('第一版描述')
        ->and(data_get($listed, '0.seo.article_id'))->toBe($created->id)
        ->and(DB::table($seoTable)->where('article_id', $created->id)->count())->toBe(1);

    $seoId = DB::table($seoTable)->where('article_id', $created->id)->value('id');

    Easy::doc($articleTable)->update($created->id, [
        'title' => 'has one article updated',
    ]);

    expect(DB::table($seoTable)->where('article_id', $created->id)->count())->toBe(1);

    Easy::doc($articleTable)->update($created->id, [
        'seo' => [
            'id' => $seoId,
            'summary' => '第二版描述',
            'status' => 1,
        ],
    ]);

    $updated = Easy::doc($articleTable)->with('seo:id,summary')->detail($created->id);

    expect(data_get($updated->seo, 'id'))->toBe($seoId)
        ->and(data_get($updated->seo, 'summary'))->toBe('第二版描述')
        ->and(DB::table($seoTable)->where('article_id', $created->id)->count())->toBe(1);

    Easy::doc($articleTable)->update($created->id, [
        'seo' => [
            'summary' => '替换版描述',
            'status' => 1,
        ],
    ]);

    $replaced = Easy::doc($articleTable)->with('seo:id,summary')->detail($created->id);

    expect(data_get($replaced->seo, 'id'))->not->toBe($seoId)
        ->and(data_get($replaced->seo, 'summary'))->toBe('替换版描述')
        ->and(DB::table($seoTable)->where('article_id', $created->id)->count())->toBe(1);

    Easy::doc($articleTable)->update($created->id, [
        'seo' => null,
    ]);

    $cleared = Easy::doc($articleTable)->with('seo:id,summary')->detail($created->id);
    $without = Easy::doc($articleTable)->lists([
        'with_relations' => true,
        'without' => ['seo'],
    ]);

    expect(DB::table($seoTable)->where('article_id', $created->id)->count())->toBe(0)
        ->and($cleared->seo)->toBeNull()
        ->and(data_get($without, '0.seo'))->toBeNull();
});

it('creates and loads hasMany child records through normalized relation protocol', function (): void {
    $commentTable = easyRuntimeTable('article_comments');
    $articleTable = easyRuntimeTable('article_has_many');

    $commentSchema = [
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'article_id',
                'type' => 'number',
                'label' => '文章ID',
                'is_required' => 1,
            ],
            [
                'name' => 'content',
                'type' => 'text',
                'label' => '评论内容',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'comments',
                'type' => 'table',
                'label' => '评论列表',
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $commentTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($commentTable)->publish($commentSchema);
    Easy::release($articleTable)->publish($articleSchema);

    $created = Easy::doc($articleTable)->create([
        'title' => 'has many article',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '第一条评论', 'status' => 1],
            ['content' => '第二条评论', 'status' => 1],
        ],
    ]);
    $plainDetail = Easy::doc($articleTable)->detail($created->id);
    $detail = Easy::doc($articleTable)
        ->with('comments:id,content')
        ->detail($created->id)
    ;
    $listed = Easy::doc($articleTable)->lists([
        'with' => ['comments:id,content'],
    ]);

    expect($created->comments)->toBeNull()
        ->and($plainDetail->comments)->toBeNull()
        ->and(data_get($detail->comments, '0.id'))->toBeInt()
        ->and(data_get($detail->comments, '0.article_id'))->toBeNull()
        ->and(data_get($detail->comments, '0.content'))->toBe('第一条评论')
        ->and(data_get($detail->comments, '1.content'))->toBe('第二条评论')
        ->and(data_get($listed, '0.comments.1.content'))->toBe('第二条评论')
        ->and(DB::table($commentTable)->count())->toBe(2);
});

it('syncs hasMany child records on update and preserves them when relation payload is omitted', function (): void {
    $commentTable = easyRuntimeTable('article_comments_update');
    $articleTable = easyRuntimeTable('article_has_many_update');

    $commentSchema = [
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'article_id',
                'type' => 'number',
                'label' => '文章ID',
                'is_required' => 1,
            ],
            [
                'name' => 'content',
                'type' => 'text',
                'label' => '评论内容',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'comments',
                'type' => 'table',
                'label' => '评论列表',
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $commentTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($commentTable)->publish($commentSchema);
    Easy::release($articleTable)->publish($articleSchema);

    $created = Easy::doc($articleTable)->create([
        'title' => 'sync article',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '保留评论', 'status' => 1],
            ['content' => '待删除评论', 'status' => 1],
        ],
    ]);
    $initialComments = DB::table($commentTable)
        ->where('article_id', $created->id)
        ->orderBy('id')
        ->get(['id', 'content'])
    ;

    Easy::doc($articleTable)->update($created->id, [
        'title' => 'sync article updated',
    ]);

    expect(DB::table($commentTable)->where('article_id', $created->id)->count())->toBe(2);

    Easy::doc($articleTable)->update($created->id, [
        'comments' => [
            [
                'id' => data_get($initialComments, '0.id'),
                'content' => '已更新评论',
                'status' => 1,
            ],
            [
                'content' => '新增评论',
                'status' => 1,
            ],
        ],
    ]);

    $listed = Easy::doc($articleTable)->lists([
        'filters' => [
            ['field' => 'id', 'operator' => '=', 'value' => $created->id],
        ],
        'with' => ['comments:id,content'],
    ]);
    $detail = Easy::doc($articleTable)->with('comments:id,content')->detail($created->id);

    expect(DB::table($commentTable)->where('article_id', $created->id)->count())->toBe(2)
        ->and(DB::table($commentTable)->where('article_id', $created->id)->where('content', '待删除评论')->count())->toBe(0)
        ->and(collect(data_get($listed, '0.comments', []))->pluck('content')->sort()->values()->all())->toBe(['已更新评论', '新增评论'])
        ->and(collect($detail->comments)->pluck('content')->sort()->values()->all())->toBe(['已更新评论', '新增评论']);

    Easy::doc($articleTable)->update($created->id, [
        'comments' => [],
    ]);

    $cleared = Easy::doc($articleTable)->with('comments:id,content')->detail($created->id);

    expect(DB::table($commentTable)->where('article_id', $created->id)->count())->toBe(0)
        ->and($cleared->comments)->toBe([]);
});

it('keeps query relation loading parameters available alongside chain enhancements', function (): void {
    $commentTable = easyRuntimeTable('article_comments_query_relations');
    $articleTable = easyRuntimeTable('article_query_relations');

    $commentSchema = [
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'article_id',
                'type' => 'number',
                'label' => '文章ID',
                'is_required' => 1,
            ],
            [
                'name' => 'content',
                'type' => 'text',
                'label' => '评论内容',
                'is_required' => 1,
                'length' => 100,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '文章标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'default' => 0,
            ],
            [
                'name' => 'comments',
                'type' => 'table',
                'label' => '评论列表',
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $commentTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($commentTable)->publish($commentSchema);
    Easy::release($articleTable)->publish($articleSchema);

    Easy::doc($articleTable)->create([
        'title' => 'query relation article',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '评论A'],
            ['content' => '评论B'],
        ],
    ]);

    $loadedAll = Easy::doc($articleTable)->lists([
        'with_relations' => true,
    ]);
    $selected = Easy::doc($articleTable)->lists([
        'with' => ['comments:id,content'],
    ]);
    $without = Easy::doc($articleTable)->lists([
        'with_relations' => true,
        'without' => ['comments'],
    ]);

    expect(data_get($loadedAll, '0.comments.0.article_id'))->toBeInt()
        ->and(collect(data_get($selected, '0.comments', []))->pluck('content')->sort()->values()->all())->toBe(['评论A', '评论B'])
        ->and(data_get($selected, '0.comments.0.article_id'))->toBeNull()
        ->and(data_get($without, '0.comments'))->toBeNull();
});

it('filters list results by belongsTo relation fields', function (): void {
    $categoryTable = easyRuntimeTable('category_relation_field');
    $articleTable = easyRuntimeTable('article_relation_field');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            [
                'name' => 'title',
                'type' => 'text',
                'label' => '分类标题',
                'is_required' => 1,
                'length' => 100,
            ],
            [
                'name' => 'status',
                'type' => 'switch',
                'label' => '状态',
                'default' => 1,
            ],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'is_required' => 1, 'length' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'is_required' => 1, 'default' => 0],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'relation' => [
                    'type' => 'belongs_to',
                    'resource' => $categoryTable,
                    'value_field' => 'id',
                    'label_field' => 'title',
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $enabled = Easy::doc($categoryTable)->create(['title' => '启用分类', 'status' => 1]);
    $disabled = Easy::doc($categoryTable)->create(['title' => '禁用分类', 'status' => 0]);

    Easy::doc($articleTable)->create(['title' => 'article enabled', 'tenant_id' => 1, 'category_id' => $enabled->id]);
    Easy::doc($articleTable)->create(['title' => 'article disabled', 'tenant_id' => 1, 'category_id' => $disabled->id]);

    $filtered = Easy::doc($articleTable)->lists([
        'filters' => [
            ['field' => 'category.status', 'operator' => '=', 'value' => 1],
        ],
        'with' => ['category:id,title,status'],
    ]);

    expect($filtered)->toHaveCount(1)
        ->and(data_get($filtered, '0.title'))->toBe('article enabled')
        ->and(data_get($filtered, '0.category.status'))->toBe(1);
});

it('filters list results by hasOne relation fields', function (): void {
    $seoTable = easyRuntimeTable('article_seo_filter');
    $articleTable = easyRuntimeTable('article_has_one_filter');

    $seoSchema = [
        'title' => 'Runtime '.$seoTable,
        'module' => 'App',
        'name' => $seoTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'is_required' => 1],
            ['name' => 'summary', 'type' => 'text', 'label' => 'SEO描述', 'is_required' => 1, 'length' => 255],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'is_required' => 1, 'length' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'is_required' => 1, 'default' => 0],
            [
                'name' => 'seo',
                'type' => 'table',
                'label' => 'SEO配置',
                'relation' => [
                    'type' => 'has_one',
                    'resource' => $seoTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($seoTable)->publish($seoSchema);
    Easy::release($articleTable)->publish($articleSchema);

    Easy::doc($articleTable)->create([
        'title' => 'article alpha',
        'tenant_id' => 1,
        'seo' => ['summary' => 'alpha summary'],
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'article beta',
        'tenant_id' => 1,
        'seo' => ['summary' => 'beta summary'],
    ]);

    $filtered = Easy::doc($articleTable)->lists([
        'filters' => [
            ['field' => 'seo.summary', 'operator' => 'like', 'value' => '%beta%'],
        ],
        'with' => ['seo:id,summary'],
    ]);

    expect($filtered)->toHaveCount(1)
        ->and(data_get($filtered, '0.title'))->toBe('article beta')
        ->and(data_get($filtered, '0.seo.summary'))->toBe('beta summary');
});

it('filters list results by hasMany relation fields', function (): void {
    $commentTable = easyRuntimeTable('article_comments_filter');
    $articleTable = easyRuntimeTable('article_has_many_filter');

    $commentSchema = [
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'is_required' => 1],
            ['name' => 'content', 'type' => 'text', 'label' => '评论内容', 'is_required' => 1, 'length' => 100],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'is_required' => 1, 'length' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'is_required' => 1, 'default' => 0],
            [
                'name' => 'comments',
                'type' => 'table',
                'label' => '评论列表',
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $commentTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($commentTable)->publish($commentSchema);
    Easy::release($articleTable)->publish($articleSchema);

    Easy::doc($articleTable)->create([
        'title' => 'article first',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '普通评论'],
        ],
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'article second',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '目标评论'],
            ['content' => '其他评论'],
        ],
    ]);

    $filtered = Easy::doc($articleTable)->lists([
        'filters' => [
            ['field' => 'comments.content', 'operator' => 'like', 'value' => '%目标%'],
        ],
        'with' => ['comments:id,content'],
    ]);

    expect($filtered)->toHaveCount(1)
        ->and(data_get($filtered, '0.title'))->toBe('article second')
        ->and(collect(data_get($filtered, '0.comments', []))->pluck('content')->contains('目标评论'))->toBeTrue();
});

it('sorts list results by belongsTo relation fields', function (): void {
    $categoryTable = easyRuntimeTable('category_relation_sort_field');
    $articleTable = easyRuntimeTable('article_relation_sort_field');

    $categorySchema = [
        'title' => 'Runtime '.$categoryTable,
        'module' => 'App',
        'name' => $categoryTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '分类标题', 'is_required' => 1, 'length' => 100],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'is_required' => 1, 'length' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'is_required' => 1, 'default' => 0],
            [
                'name' => 'category_id',
                'type' => 'select',
                'label' => '所属分类',
                'relation' => [
                    'type' => 'belongs_to',
                    'resource' => $categoryTable,
                    'value_field' => 'id',
                    'label_field' => 'title',
                ],
            ],
        ],
    ];

    Easy::release($categoryTable)->publish($categorySchema);
    Easy::release($articleTable)->publish($articleSchema);

    $beta = Easy::doc($categoryTable)->create(['title' => 'Beta']);
    $alpha = Easy::doc($categoryTable)->create(['title' => 'Alpha']);
    $gamma = Easy::doc($categoryTable)->create(['title' => 'Gamma']);

    Easy::doc($articleTable)->create(['title' => 'article beta', 'tenant_id' => 1, 'category_id' => $beta->id]);
    Easy::doc($articleTable)->create(['title' => 'article alpha', 'tenant_id' => 1, 'category_id' => $alpha->id]);
    Easy::doc($articleTable)->create(['title' => 'article gamma', 'tenant_id' => 1, 'category_id' => $gamma->id]);

    $ascending = Easy::doc($articleTable)->lists([
        'sorts' => [
            ['field' => 'category.title', 'direction' => 'asc'],
        ],
        'with' => ['category:id,title'],
    ]);
    $descending = Easy::doc($articleTable)->lists([
        'sorts' => [
            ['field' => 'category.title', 'direction' => 'desc'],
        ],
        'with' => ['category:id,title'],
    ]);

    expect(array_column($ascending, 'title'))->toBe(['article alpha', 'article beta', 'article gamma'])
        ->and(array_column($descending, 'title'))->toBe(['article gamma', 'article beta', 'article alpha']);
});

it('sorts list results by hasOne relation fields', function (): void {
    $seoTable = easyRuntimeTable('article_seo_sort_field');
    $articleTable = easyRuntimeTable('article_has_one_sort_field');

    $seoSchema = [
        'title' => 'Runtime '.$seoTable,
        'module' => 'App',
        'name' => $seoTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'is_required' => 1],
            ['name' => 'summary', 'type' => 'text', 'label' => 'SEO描述', 'is_required' => 1, 'length' => 255],
        ],
    ];
    $articleSchema = [
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'is_required' => 1, 'length' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'is_required' => 1, 'default' => 0],
            [
                'name' => 'seo',
                'type' => 'table',
                'label' => 'SEO配置',
                'relation' => [
                    'type' => 'has_one',
                    'resource' => $seoTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                ],
            ],
        ],
    ];

    Easy::release($seoTable)->publish($seoSchema);
    Easy::release($articleTable)->publish($articleSchema);

    Easy::doc($articleTable)->create([
        'title' => 'article beta',
        'tenant_id' => 1,
        'seo' => ['summary' => 'Beta'],
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'article alpha',
        'tenant_id' => 1,
        'seo' => ['summary' => 'Alpha'],
    ]);
    Easy::doc($articleTable)->create([
        'title' => 'article gamma',
        'tenant_id' => 1,
        'seo' => ['summary' => 'Gamma'],
    ]);

    $ascending = Easy::doc($articleTable)->lists([
        'sorts' => [
            ['field' => 'seo.summary', 'direction' => 'asc'],
        ],
        'with' => ['seo:id,summary'],
    ]);
    $descending = Easy::doc($articleTable)->lists([
        'sorts' => [
            ['field' => 'seo.summary', 'direction' => 'desc'],
        ],
        'with' => ['seo:id,summary'],
    ]);

    expect(array_column($ascending, 'title'))->toBe(['article alpha', 'article beta', 'article gamma'])
        ->and(array_column($descending, 'title'))->toBe(['article gamma', 'article beta', 'article alpha']);
});

it('supports aggregate queries for grouped statistics', function (): void {
    $table = easyRuntimeTable('aggregate');
    $schema = easyRuntimeSchema($table, [
        'search_fields' => ['title'],
        'order' => ['id' => 'desc'],
    ]);
    $handle = Easy::doc($table);

    Easy::release($table)->publish($schema);
    $handle->create([
        'title' => 'alpha',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'alphabet',
        'tenant_id' => 2,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'beta',
        'tenant_id' => 5,
        'status' => 0,
    ]);

    $grouped = $handle->aggregate([
        'groups' => ['status'],
        'aggregates' => [
            ['type' => 'count', 'as' => 'total'],
            ['type' => 'sum', 'field' => 'tenant_id', 'as' => 'tenant_sum'],
        ],
        'sorts' => [
            ['field' => 'status', 'direction' => 'desc'],
        ],
    ]);
    $keywordOnly = $handle->aggregate([
        'keyword' => 'alpha',
        'aggregates' => [
            ['type' => 'count', 'as' => 'matched_total'],
        ],
    ]);

    expect($grouped)->toBe([
        ['status' => 1, 'total' => 2, 'tenant_sum' => 3],
        ['status' => 0, 'total' => 1, 'tenant_sum' => 5],
    ])->and($keywordOnly)->toBe([
        ['matched_total' => 2],
    ]);
});

it('runs chart definitions through the aggregate engine', function (): void {
    $table = easyRuntimeTable('charts');
    $schema = easyRuntimeSchema($table, [
        'charts' => [
            [
                'name' => 'status_summary',
                'title' => '状态汇总',
                'type' => 'pie',
                'groups' => ['status'],
                'aggregates' => [
                    ['type' => 'count', 'as' => 'total'],
                ],
            ],
            [
                'title' => 'Alpha Count',
                'query' => [
                    'keyword' => 'alpha',
                    'aggregates' => [
                        ['type' => 'count', 'as' => 'matched_total'],
                    ],
                ],
            ],
        ],
    ]);
    $handle = Easy::doc($table);

    Easy::release($table)->publish($schema);
    $handle->create([
        'title' => 'alpha',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'alphabet',
        'tenant_id' => 2,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'beta',
        'tenant_id' => 3,
        'status' => 0,
    ]);

    $charts = Easy::charts($table);
    $definitions = $charts->definitions();
    $statusSummary = $charts->run('status_summary', [
        'sorts' => [
            ['field' => 'status', 'direction' => 'desc'],
        ],
    ]);
    $alphaCount = $charts->run('alpha_count');

    expect(array_column($definitions, 'name'))->toBe(['status_summary', 'alpha_count'])
        ->and($statusSummary)->toBe([
            ['status' => 1, 'total' => 2],
            ['status' => 0, 'total' => 1],
        ])->and($alphaCount)->toBe([
            ['matched_total' => 2],
        ]);
});

it('normalizes frontend-friendly chart schema conventions', function (): void {
    $table = easyRuntimeTable('chart_schema');
    $schema = easyRuntimeSchema($table, [
        'search_fields' => ['title'],
        'charts' => [
            [
                'title' => 'Status Overview',
                'dimension' => [
                    'field' => 'status',
                    'label' => '状态',
                ],
                'metrics' => [
                    [
                        'type' => 'count',
                        'label' => '总数',
                    ],
                    [
                        'type' => 'sum',
                        'field' => 'tenant_id',
                        'label' => '租户和值',
                    ],
                ],
            ],
            [
                'title' => 'Status Overview',
                'query' => [
                    'keyword' => 'alpha',
                    'aggregates' => [
                        ['type' => 'count'],
                    ],
                ],
            ],
        ],
    ]);
    $handle = Easy::doc($table);

    Easy::release($table)->publish($schema);
    $handle->create([
        'title' => 'alpha',
        'tenant_id' => 1,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'alphabet',
        'tenant_id' => 2,
        'status' => 1,
    ]);
    $handle->create([
        'title' => 'beta',
        'tenant_id' => 3,
        'status' => 0,
    ]);

    $charts = Easy::charts($table);
    $definitions = $charts->definitions();
    $overview = $charts->run('status_overview', [
        'sorts' => [
            ['field' => 'status', 'direction' => 'desc'],
        ],
    ]);
    $alphaOnly = $charts->run('status_overview_2');
    $dataset = $charts->dataset('status_overview', [
        'sorts' => [
            ['field' => 'status', 'direction' => 'desc'],
        ],
    ]);

    expect($definitions[0]['type'])->toBe('bar')
        ->and($definitions[0]['dimensions'])->toBe([
            ['field' => 'status', 'label' => '状态'],
        ])->and($definitions[0]['metrics'])->toBe([
            ['type' => 'count', 'field' => null, 'as' => 'total', 'label' => '总数', 'index' => 0],
            ['type' => 'sum', 'field' => 'tenant_id', 'as' => 'sum_tenant_id', 'label' => '租户和值', 'index' => 1],
        ])->and(array_column($definitions, 'name'))->toBe(['status_overview', 'status_overview_2'])
        ->and($overview)->toBe([
            ['status' => 1, 'total' => 2, 'sum_tenant_id' => 3],
            ['status' => 0, 'total' => 1, 'sum_tenant_id' => 3],
        ])->and($alphaOnly)->toBe([
            ['total' => 2],
        ])->and($dataset['summary'])->toBe([
            'total' => 2,
            'sum_tenant_id' => 3,
        ])->and($dataset['categories'])->toBe([
            [
                'key' => '1',
                'label' => '1',
                'value' => 1,
                'values' => ['status' => 1],
            ],
            [
                'key' => '0',
                'label' => '0',
                'value' => 0,
                'values' => ['status' => 0],
            ],
        ])->and($dataset['series'])->toBe([
            [
                'key' => 'total',
                'name' => '总数',
                'type' => 'count',
                'field' => null,
                'data' => [2, 1],
            ],
            [
                'key' => 'sum_tenant_id',
                'name' => '租户和值',
                'type' => 'sum',
                'field' => 'tenant_id',
                'data' => [3, 3],
            ],
        ]);
});

it('explains publish plan changes for add rename change and drop operations', function (): void {
    $table = easyRuntimeTable('plan_explain');
    $schemaV1 = easyRuntimeSchema($table, [
        'fields' => array_merge(easyRuntimeSchema($table)['fields'], [
            [
                'name' => 'legacy_code',
                'type' => 'text',
                'label' => '旧编码',
                'length' => 30,
            ],
        ]),
    ]);
    $schemaV2 = easyRuntimeSchema($table, [
        'fields' => [
            [
                'name' => 'headline',
                'type' => 'text',
                'label' => '标题',
                'is_required' => 1,
                'is_unique' => 1,
                'length' => 150,
                'rename_from' => 'title',
            ],
            [
                'name' => 'tenant_id',
                'type' => 'number',
                'label' => '租户',
                'is_required' => 1,
                'extends' => [
                    'max' => 999999,
                ],
            ],
            [
                'name' => 'status',
                'type' => 'text',
                'label' => '状态文本',
                'length' => 20,
            ],
            [
                'name' => 'excerpt',
                'type' => 'text',
                'label' => '摘要',
                'length' => 120,
            ],
        ],
    ]);
    $handle = Easy::release($table);

    $handle->publish($schemaV1);
    $plan = $handle->plan($schemaV2);
    $explanation = $plan->explanation();
    $changedFields = collect((array) data_get($explanation, 'fields.change', []))->keyBy('name')->all();

    expect($plan->operations()['rename_fields'])->toBe(['title' => 'headline'])
        ->and($plan->operations()['add_fields'])->toContain('excerpt')
        ->and($plan->operations()['drop_fields'])->toContain('legacy_code')
        ->and(data_get($explanation, 'summary.rename_count'))->toBe(1)
        ->and(data_get($explanation, 'summary.add_count'))->toBe(1)
        ->and(data_get($explanation, 'summary.drop_count'))->toBe(1)
        ->and(data_get($explanation, 'summary.add_unique_count'))->toBe(1)
        ->and(data_get($explanation, 'summary.destructive'))->toBeTrue()
        ->and(data_get($explanation, 'fields.rename.0.from'))->toBe('title')
        ->and(data_get($explanation, 'fields.rename.0.to'))->toBe('headline')
        ->and(data_get($explanation, 'fields.rename.0.field.mapping.storage.column_definition'))->toBe('varchar(150)')
        ->and(data_get($explanation, 'fields.add.0.name'))->toBe('excerpt')
        ->and(data_get($explanation, 'fields.drop.0.name'))->toBe('legacy_code')
        ->and(data_get($explanation, 'fields.drop.0.risks.0.code'))->toBe('drop_field')
        ->and(array_column((array) data_get($changedFields, 'status.changes', []), 'path'))->toContain('type', 'mapping.storage.column_definition')
        ->and(data_get($changedFields, 'status.destructive'))->toBeTrue()
        ->and(array_column((array) data_get($explanation, 'risks', []), 'code'))->toContain('drop_field', 'storage_changed', 'add_unique')
        ->and($plan->toArray())->toHaveKeys(['operations', 'summary', 'explanation', 'destructive', 'empty']);
});

it('validates schema before plan draft and publish', function (): void {
    $table = easyRuntimeTable('schema_guard');
    $invalidSchema = easyRuntimeSchema($table, [
        'search_fields' => ['missing_field'],
    ]);
    $reservedFieldSchema = easyRuntimeSchema($table, [
        'fields' => [
            ['name' => '__status_text', 'type' => 'text', 'label' => '非法字段'],
        ],
    ]);
    $appendConflictSchema = easyRuntimeSchema($table, [
        'fields' => [
            ['name' => 'status', 'type' => 'select', 'label' => '状态', 'options' => [['label' => '启用', 'value' => 1]]],
            ['name' => 'status_text', 'type' => 'text', 'label' => '业务字段'],
            ['name' => 'summary', 'type' => 'radio', 'label' => '摘要', 'options' => [['label' => '是', 'value' => 1]], 'extends' => [
                'append_name' => '__status_text',
            ]],
        ],
    ]);
    $handle = Easy::release($table);

    expect(function () use ($handle, $invalidSchema) {
        return $handle->plan($invalidSchema);
    })
        ->toThrow(\InvalidArgumentException::class, 'Schema search field [missing_field] does not exist.')
        ->and(function () use ($handle, $reservedFieldSchema) {
            return $handle->plan($reservedFieldSchema);
        })
        ->toThrow(\InvalidArgumentException::class, 'Schema field [__status_text] uses reserved prefix [__].')
        ->and(function () use ($handle, $appendConflictSchema) {
            return $handle->plan($appendConflictSchema);
        })
        ->toThrow(\InvalidArgumentException::class, 'Schema append field [__status_text] is duplicated.')
        ->and(function () use ($handle, $invalidSchema) {
            return $handle->saveDraft($invalidSchema);
        })
        ->toThrow(\InvalidArgumentException::class, 'Schema search field [missing_field] does not exist.')
        ->and(function () use ($handle, $invalidSchema) {
            return $handle->publish($invalidSchema);
        })
        ->toThrow(\InvalidArgumentException::class, 'Schema search field [missing_field] does not exist.')
        ->and(Schema::hasTable($table))->toBeFalse();
});

it('distinguishes layout nodes from real fields in frontend schema payload', function (): void {
    $table = easyRuntimeTable('layout_nodes');
    $schema = easyRuntimeSchema($table);
    $schema['fields'] = [
        [
            'type' => 'section',
            'label' => '基础信息',
            'children' => [
                [
                    'name' => 'title',
                    'type' => 'text',
                    'label' => '标题',
                    'is_required' => 1,
                    'length' => 100,
                ],
                [
                    'type' => 'grid',
                    'columns' => [
                        [
                            'children' => [
                                [
                                    'name' => 'tenant_id',
                                    'type' => 'number',
                                    'label' => '租户',
                                    'default' => 0,
                                ],
                            ],
                        ],
                        [
                            'children' => [
                                [
                                    'name' => 'status',
                                    'type' => 'radio',
                                    'label' => '状态',
                                    'options' => [
                                        ['label' => '启用', 'value' => 1],
                                        ['label' => '禁用', 'value' => 0],
                                    ],
                                    'default' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'type' => 'divider',
            'label' => '分隔线',
        ],
    ];
    $schema['search_fields'] = ['title'];
    $schema['charts'] = [
        [
            'title' => 'Status Summary',
            'dimension' => 'status',
            'metrics' => [
                ['type' => 'count', 'label' => '总数'],
            ],
        ],
    ];
    $releaseHandle = Easy::release($table);
    $schemaHandle = Easy::schema($table);

    $releaseHandle->publish($schema);
    $raw = $schemaHandle->raw()->toArray();

    expect(Schema::hasColumn($table, 'title'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'tenant_id'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'status'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'section'))->toBeFalse()
        ->and(array_column($raw['fields'], 'name'))->toBe(['title', 'tenant_id', 'status'])
        ->and(data_get($raw, 'layout.nodes.0.type'))->toBe('section')
        ->and(data_get($raw, 'layout.nodes.1.type'))->toBe('divider');
});

it('supports frontend builder schema aliases and mixed layout payloads', function (): void {
    $table = easyRuntimeTable('builder_payload');
    $schema = [
        'title' => '栏目管理',
        'name' => $table,
        'module' => 'cms',
        'table' => [
            'selection' => false,
            'tree' => true,
            'columns' => ['title', 'type', 'alias'],
        ],
        'form' => [
            'title' => false,
            'footer' => false,
        ],
        'fields' => [
            [
                'type' => 'row',
                'gutter' => 20,
                'children' => [
                    [
                        'type' => 'col',
                        'col' => 16,
                        'children' => [
                            [
                                'name' => 'type',
                                'type' => 'select',
                                'label' => '栏目类型',
                                'required' => true,
                                'options' => [
                                    ['label' => '列表栏目', 'value' => 'list'],
                                    ['label' => '单页栏目', 'value' => 'page'],
                                ],
                                'def' => 'list',
                            ],
                            [
                                'name' => 'parent_ids',
                                'type' => 'cascader',
                                'label' => '父级栏目',
                                'pl' => '不选择则为顶级栏目',
                            ],
                            [
                                'name' => 'title',
                                'type' => 'text',
                                'label' => '栏目标题',
                                'required' => true,
                                'maxlength' => 100,
                            ],
                            [
                                'name' => 'alias',
                                'type' => 'text',
                                'label' => '栏目别名',
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
                    [
                        'type' => 'col',
                        'col' => 8,
                        'children' => [
                            [
                                'name' => 'cover',
                                'type' => 'resource',
                                'label' => '栏目图片',
                                'help' => '建议尺寸：300x200',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'divider',
                'content' => '显示控制',
            ],
            [
                'type' => 'row',
                'children' => [
                    [
                        'type' => 'switch',
                        'name' => 'status',
                        'label' => '栏目状态',
                        'def' => 1,
                    ],
                ],
            ],
        ],
        'search_fields' => ['title'],
    ];
    $docHandle = Easy::doc($schema);
    $schemaHandle = Easy::schema($schema);
    $releaseHandle = Easy::release($schema);

    $releaseHandle->publish($schema);

    $created = $docHandle->create([
        'type' => 'list',
        'parent_ids' => [1, 2],
        'title' => '新闻栏目',
        'alias' => 'news',
        'cover' => '/uploads/category.png',
        'status' => 1,
    ]);
    $raw = $schemaHandle->raw()->toArray();
    $blueprint = $schemaHandle->blueprint();
    $fieldMappings = $schemaHandle->fieldMappings();

    expect($created)->not->toBeNull()
        ->and($created->title)->toBe('新闻栏目')
        ->and(Schema::hasColumn($table, 'type'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'parent_ids'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'alias'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'cover'))->toBeTrue()
        ->and(Schema::hasColumn($table, 'status'))->toBeTrue()
        ->and($schemaHandle->definition()->name())->toBe($table)
        ->and(data_get($raw, 'name'))->toBe($table)
        ->and(array_column($raw['fields'], 'name'))->toBe(['type', 'parent_ids', 'title', 'alias', 'cover', 'status'])
        ->and(data_get($raw, 'fields.0.is_required'))->toBe(1)
        ->and(data_get($raw, 'fields.1.type'))->toBe('cascader')
        ->and(data_get($raw, 'fields.4.type'))->toBe('resource')
        ->and(data_get($raw, 'fields.5.type'))->toBe('switch')
        ->and(data_get($raw, 'layout.nodes.0.type'))->toBe('row')
        ->and(data_get($raw, 'table.tree'))->toBeTrue()
        ->and(data_get($raw, 'form.footer'))->toBeFalse()
        ->and(data_get($blueprint, 'resource.name'))->toBe($table)
        ->and(data_get($blueprint, 'resource.module'))->toBe('cms')
        ->and(data_get($blueprint, 'resource.title'))->toBe('栏目管理')
        ->and(data_get($blueprint, 'views.table.tree'))->toBeTrue()
        ->and(data_get($blueprint, 'views.form.footer'))->toBeFalse()
        ->and(data_get($blueprint, 'layout.nodes.0.type'))->toBe('row')
        ->and(array_column($blueprint['fields'], 'name'))->toBe(['type', 'parent_ids', 'title', 'alias', 'cover', 'status'])
        ->and(data_get($blueprint, 'fields.0.default'))->toBe('list')
        ->and(data_get($blueprint, 'fields.0.required'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.1.type'))->toBe('cascader')
        ->and(data_get($blueprint, 'fields.1.placeholder'))->toBe('不选择则为顶级栏目')
        ->and(data_get($blueprint, 'fields.2.rules'))->toContain('required', 'max:100')
        ->and(data_get($blueprint, 'fields.3.rules'))->toContain('min:2', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/')
        ->and(data_get($blueprint, 'fields.4.comment'))->toBe('建议尺寸：300x200')
        ->and(data_get($blueprint, 'fields.5.display.form.visible'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.5.editable'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.2.mapping.component.group'))->toBe('text')
        ->and(data_get($blueprint, 'fields.2.mapping.storage.column_definition'))->toBe('varchar(100)')
        ->and(data_get($blueprint, 'fields.2.mapping.storage.runtime_cast'))->toBe('string')
        ->and(data_get($blueprint, 'fields.2.mapping.validation.rules'))->toContain('required', 'max:100')
        ->and(data_get($blueprint, 'fields.2.mapping.query.filterable'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.2.mapping.query.sortable'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.1.mapping.storage.column_definition'))->toBe('json')
        ->and(data_get($blueprint, 'fields.1.mapping.storage.runtime_cast'))->toBe('array')
        ->and(data_get($blueprint, 'fields.5.mapping.storage.column_definition'))->toBe('tinyint unsigned')
        ->and(data_get($blueprint, 'fields.5.type'))->toBe('switch')
        ->and(data_get($blueprint, 'fields.5.options.0.value'))->toBe(1)
        ->and(data_get($fieldMappings, 'title.storage.column_definition'))->toBe('varchar(100)')
        ->and(data_get($fieldMappings, 'parent_ids.storage.runtime_cast'))->toBe('array')
        ->and(data_get($fieldMappings, 'status.component.type'))->toBe('switch');
});

it('maps frontend builder rules to runtime validation constraints', function (): void {
    $table = easyRuntimeTable('builder_rules');
    $schema = [
        'title' => '栏目管理',
        'name' => $table,
        'module' => 'cms',
        'fields' => [
            [
                'type' => 'row',
                'children' => [
                    [
                        'type' => 'col',
                        'children' => [
                            [
                                'name' => 'title',
                                'type' => 'text',
                                'label' => '栏目标题',
                                'required' => true,
                                'maxlength' => 10,
                            ],
                            [
                                'name' => 'alias',
                                'type' => 'text',
                                'label' => '栏目别名',
                                'rules' => [
                                    [
                                        'min' => 2,
                                        'max' => 10,
                                    ],
                                    [
                                        'pattern' => '^[a-zA-Z0-9_-]+$',
                                    ],
                                ],
                            ],
                            [
                                'name' => 'weight',
                                'type' => 'number',
                                'label' => '排序',
                                'min' => 0,
                                'max' => 5,
                                'def' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $docHandle = Easy::doc($schema);
    $schemaHandle = Easy::schema($schema);
    $releaseHandle = Easy::release($schema);

    $releaseHandle->publish($schema);
    $mapping = $schemaHandle->fieldMappings();

    expect(function () use ($docHandle) {
        return $docHandle->create([
            'title' => '这是一个超过十个字符的标题',
            'alias' => 'ok_alias',
            'weight' => 1,
        ]);
    })->toThrow(EasyValidateException::class)
        ->and(function () use ($docHandle) {
            return $docHandle->create([
                'title' => '有效标题',
                'alias' => '!',
                'weight' => 1,
            ]);
        })->toThrow(EasyValidateException::class)
        ->and(function () use ($docHandle) {
            return $docHandle->create([
                'title' => '有效标题',
                'alias' => 'alias_10',
                'weight' => 8,
            ]);
        })->toThrow(EasyValidateException::class);

    $created = $docHandle->create([
        'title' => '有效标题',
        'alias' => 'alias_10',
        'weight' => 3,
    ]);

    expect($created)->not->toBeNull()
        ->and($created->alias)->toBe('alias_10')
        ->and($created->weight)->toBe(3)
        ->and(data_get($mapping, 'weight.storage.column_definition'))->toBe('tinyint unsigned')
        ->and(data_get($mapping, 'weight.storage.runtime_cast'))->toBe('int')
        ->and(data_get($mapping, 'weight.validation.rules'))->toContain('numeric', 'min:0', 'max:5');
});

it('maps builder display states and custom validation messages into blueprint and runtime validation', function (): void {
    $table = easyRuntimeTable('builder_display');
    $schema = [
        'title' => '展示控制',
        'name' => $table,
        'module' => 'cms',
        'fields' => [
            [
                'type' => 'row',
                'children' => [
                    [
                        'type' => 'col',
                        'children' => [
                            [
                                'name' => 'alias',
                                'type' => 'text',
                                'label' => '栏目别名',
                                'readonly' => true,
                                'scenes' => [
                                    'form' => ['visible' => true],
                                    'table' => ['visible' => false],
                                ],
                                'rules' => [
                                    [
                                        'required' => true,
                                        'message' => '栏目别名不能为空',
                                    ],
                                    [
                                        'pattern' => '^[a-zA-Z0-9_-]+$',
                                        'message' => '栏目别名格式错误',
                                    ],
                                ],
                            ],
                            [
                                'name' => 'secret_note',
                                'type' => 'text',
                                'label' => '隐藏备注',
                                'hidden' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $docHandle = Easy::doc($schema);
    $schemaHandle = Easy::schema($schema);
    $releaseHandle = Easy::release($schema);

    $releaseHandle->publish($schema);

    expect(function () use ($docHandle) {
        return $docHandle->create([
            'alias' => '!',
            'secret_note' => 'internal',
        ]);
    })->toThrow(EasyValidateException::class, '栏目别名格式错误')
        ->and(function () use ($docHandle) {
            return $docHandle->create([
                'secret_note' => 'internal',
            ]);
        })->toThrow(EasyValidateException::class, '栏目别名不能为空');

    $blueprint = $schemaHandle->blueprint();

    expect(data_get($blueprint, 'fields.0.rule_messages.required'))->toBe('栏目别名不能为空')
        ->and(data_get($blueprint, 'fields.0.rule_messages.regex'))->toBe('栏目别名格式错误')
        ->and(data_get($blueprint, 'fields.0.display.form.visible'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.0.display.table.visible'))->toBeFalse()
        ->and(data_get($blueprint, 'fields.0.readonly'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.0.editable'))->toBeFalse()
        ->and(data_get($blueprint, 'fields.1.hidden'))->toBeTrue()
        ->and(data_get($blueprint, 'fields.1.display.form.visible'))->toBeFalse()
        ->and(data_get($blueprint, 'fields.1.display.table.visible'))->toBeFalse();
});
