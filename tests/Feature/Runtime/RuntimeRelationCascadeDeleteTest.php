<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Exceptions\EasyException;

beforeEach(function (): void {
    migrateEasyRuntimeTables();
});

it('删除主记录时会级联删除 hasOne 和 hasMany 子记录', function (): void {
    $seoTable = easyRuntimeTable('cascade_seo');
    $commentTable = easyRuntimeTable('cascade_comments');
    $articleTable = easyRuntimeTable('cascade_article');

    Easy::release($seoTable)->publish([
        'title' => 'Runtime '.$seoTable,
        'module' => 'App',
        'name' => $seoTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'required' => true],
            ['name' => 'summary', 'type' => 'text', 'label' => 'SEO描述', 'required' => true, 'maxlength' => 255],
        ],
    ]);

    Easy::release($commentTable)->publish([
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'required' => true],
            ['name' => 'content', 'type' => 'text', 'label' => '评论内容', 'required' => true, 'maxlength' => 100],
        ],
    ]);

    Easy::release($articleTable)->publish([
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'required' => true, 'maxlength' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'required' => true],
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
    ]);

    $article = Easy::doc($articleTable)->create([
        'title' => 'cascade article',
        'tenant_id' => 1,
        'seo' => ['summary' => 'SEO 内容'],
        'comments' => [
            ['content' => '第一条评论'],
            ['content' => '第二条评论'],
        ],
    ]);

    expect(DB::table($seoTable)->count())->toBe(1)
        ->and(DB::table($commentTable)->count())->toBe(2);

    $deleted = Easy::doc($articleTable)->delete($article->id);

    expect($deleted)->toBeTrue()
        ->and(DB::table($articleTable)->count())->toBe(0)
        ->and(DB::table($seoTable)->count())->toBe(0)
        ->and(DB::table($commentTable)->count())->toBe(0);
});

it('删除启用回收站的主记录时会级联回收子记录', function (): void {
    $commentTable = easyRuntimeTable('cascade_recycle_comments');
    $articleTable = easyRuntimeTable('cascade_recycle_article');

    Easy::release($commentTable)->publish([
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 1,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'required' => true],
            ['name' => 'content', 'type' => 'text', 'label' => '评论内容', 'required' => true, 'maxlength' => 100],
        ],
    ]);

    Easy::release($articleTable)->publish([
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 1,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'required' => true, 'maxlength' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'required' => true],
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
    ]);

    $article = Easy::doc($articleTable)->create([
        'title' => 'recycle article',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '保留轨迹评论'],
        ],
    ]);

    $commentId = (int) DB::table($commentTable)->where('article_id', $article->id)->value('id');

    expect(Easy::doc($articleTable)->delete($article->id))->toBeTrue()
        ->and((int) DB::table($articleTable)->where('id', $article->id)->value('deleted_at'))->toBeGreaterThan(0)
        ->and((int) DB::table($commentTable)->where('id', $commentId)->value('deleted_at'))->toBeGreaterThan(0)
        ->and(Easy::doc($articleTable)->detail($article->id))->toBeNull();
});

it('restrict 删除策略会阻止删除存在子记录的主记录', function (): void {
    $commentTable = easyRuntimeTable('restrict_comments');
    $articleTable = easyRuntimeTable('restrict_article');

    Easy::release($commentTable)->publish([
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID', 'required' => true],
            ['name' => 'content', 'type' => 'text', 'label' => '评论内容', 'required' => true, 'maxlength' => 100],
        ],
    ]);

    Easy::release($articleTable)->publish([
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'required' => true, 'maxlength' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'required' => true],
            [
                'name' => 'comments',
                'type' => 'table',
                'label' => '评论列表',
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $commentTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                    'on_delete' => 'restrict',
                ],
            ],
        ],
    ]);

    $article = Easy::doc($articleTable)->create([
        'title' => 'restrict article',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '限制删除评论'],
        ],
    ]);

    expect(function () use ($articleTable, $article): void {
        Easy::doc($articleTable)->delete($article->id);
    })->toThrow(EasyException::class, __('ptadmin-easy::messages.errors.relation_restrict', [
        'field' => 'comments',
    ]));

    expect(DB::table($articleTable)->count())->toBe(1)
        ->and(DB::table($commentTable)->count())->toBe(1);
});

it('set_null 删除策略会将子记录外键置空', function (): void {
    $commentTable = easyRuntimeTable('set_null_comments');
    $articleTable = easyRuntimeTable('set_null_article');

    Easy::release($commentTable)->publish([
        'title' => 'Runtime '.$commentTable,
        'module' => 'App',
        'name' => $commentTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'article_id', 'type' => 'number', 'label' => '文章ID'],
            ['name' => 'content', 'type' => 'text', 'label' => '评论内容', 'required' => true, 'maxlength' => 100],
        ],
    ]);

    Easy::release($articleTable)->publish([
        'title' => 'Runtime '.$articleTable,
        'module' => 'App',
        'name' => $articleTable,
        'allow_recycle' => 0,
        'track_changes' => 0,
        'fields' => [
            ['name' => 'title', 'type' => 'text', 'label' => '文章标题', 'required' => true, 'maxlength' => 100],
            ['name' => 'tenant_id', 'type' => 'number', 'label' => '租户', 'required' => true],
            [
                'name' => 'comments',
                'type' => 'table',
                'label' => '评论列表',
                'relation' => [
                    'type' => 'has_many',
                    'resource' => $commentTable,
                    'foreign_key' => 'article_id',
                    'local_key' => 'id',
                    'on_delete' => 'set_null',
                ],
            ],
        ],
    ]);

    $article = Easy::doc($articleTable)->create([
        'title' => 'set null article',
        'tenant_id' => 1,
        'comments' => [
            ['content' => '保留评论'],
        ],
    ]);

    $commentId = (int) DB::table($commentTable)->where('article_id', $article->id)->value('id');

    expect(Easy::doc($articleTable)->delete($article->id))->toBeTrue()
        ->and(DB::table($articleTable)->count())->toBe(0)
        ->and(DB::table($commentTable)->where('id', $commentId)->value('article_id'))->toBeNull()
        ->and(DB::table($commentTable)->count())->toBe(1);
});
