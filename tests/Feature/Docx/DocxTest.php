<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

use PTAdmin\Easy\Easy;
use PTAdmin\Easy\Engine\Model\Document;

beforeEach(function (): void {
    $this->modName = 'docx';
    $this->tabelName = 'docx';
    app()->setBasePath(config('test_path'));
});

it('【docx】get table', function (): void {
    $config = app()->get('config');
    $config->set('database.connections.testing.prefix', 'pt_');
    $config->set('database.prefix', 'pt_');
    $prefix = config('database.prefix', '');

    $docx = Easy::docx($this->tabelName);
    $this->assertSame($prefix, 'pt_');
    $this->assertSame($docx->getTable(), $prefix.$this->tabelName);
});

it('【docx】get attributes', function (): void {
    $docx = Easy::docx($this->tabelName);
    $field = [
        'title', 'table_name', 'parent_table_name', 'module', 'intro', 'title_field', 'cover_field', 'color',
        'icon', 'cover', 'migrate_hash', 'weight', 'quick_entry', 'read_only', 'is_publish', 'is_single', 'is_tree',
        'is_table', 'allow_import', 'allow_export', 'allow_recycle', 'allow_copy', 'allow_rename', 'track_changes', 'status',
    ];
    $attributes = $docx->getAttributes();
    $attributesKey = array_keys($attributes);
    expect($attributesKey)->toEqualCanonicalizing($field);
});

it('【docx】get fillable', function (): void {
    $fillableHasBanDocx = Easy::docx('docx_test_has_ban_fillable');
    $fillableHasBan = $fillableHasBanDocx->getFillable();
    expect($fillableHasBan)->toBeArray()
        ->and($fillableHasBan)->toEqualCanonicalizing(['title']);
});

it('【docx】get comment', function (): void {
    $docx = Easy::docx('docx_not_has_comment');
    $docxHasComment = Easy::docx('docx_has_comment');
    expect($docx->getComment())->toBe('文档管理')
        ->and($docxHasComment->getComment())->toBe('这里是文档介绍');
});

it('【docx】get raw table', function (): void {
    $docx = Easy::docx($this->tabelName);
    $rawTable = $docx->getRawTable();
    $this->assertSame($rawTable, $this->tabelName);
});

it('【docx】get primary key', function (): void {
    $docx = Easy::docx($this->tabelName);
    $primaryKey = $docx->getPrimaryKey();
    $this->assertSame($primaryKey, 'id');
});

it('【docx】get title field', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalTitleField = $docxNormal->getTitleField();
    $tryTitleField = $docxTry->getTitleField();
    expect($normalTitleField)->toBe('title')
        ->and($tryTitleField)->toBe('title_field_test');
});

it('【docx】get field cover', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalTitleField = $docxNormal->getFieldCover();
    $tryTitleField = $docxTry->getFieldCover();
    expect($normalTitleField)->toBe('cover')
        ->and($tryTitleField)->toBe('cover_field_test');
});

it('【docx】get fields', function (): void {
    $docx = Easy::docx($this->tabelName);
    $fields = $docx->getFields();
    $this->assertIsArray($fields);
});

it('【docx】get field', function (): void {
    $fieldName = 'title';
    $docx = Easy::docx($this->tabelName);
    $field = $docx->getField($fieldName);
    $nullTest = $docx->getField('not_exists');
    expect($field)->not->toBeNull()
        ->and($nullTest)->toBeNull();
});

it('【docx】ger export fields', function (): void {
    $preset = ['title' => '文档名称'];
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalExportFields = $docxNormal->getExportFields();
    $tryExportFields = $docxTry->getExportFields();

    expect($normalExportFields)->toEqualCanonicalizing([])
        ->and($tryExportFields)->toEqualCanonicalizing($preset);
});

it('【docx】get search fields', function (): void {
    $preset = ['title'];
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalSearchFields = $docxNormal->getSearchFields();
    $trySearchFields = $docxTry->getSearchFields();
    expect($normalSearchFields)->toEqualCanonicalizing([])
        ->and($trySearchFields)->toEqualCanonicalizing($preset);
});

it('【docx】get order fields', function (): void {
    $preset = ['title' => 'desc', 'id' => 'desc'];
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalOrderFields = $docxNormal->getOrderFields();
    $tryOrderFields = $docxTry->getOrderFields();
    expect($normalOrderFields)->toEqualCanonicalizing([])
        ->and($tryOrderFields)->toEqualCanonicalizing($preset);
});

it('【docx】document', function (): void {
    $docx = Easy::docx($this->tabelName);
    $document = $docx->document();
    $this->assertInstanceOf(Document::class, $document);
});

it('【docx】allow import', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalAllowImport = $docxNormal->allowImport();
    $tryAllowImport = $docxTry->allowImport();
    expect($normalAllowImport)->toBeBool()
        ->and($tryAllowImport)->toBeBool();
});

it('【docx】allow export', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalAllowExport = $docxNormal->allowExport();
    $tryAllowExport = $docxTry->allowExport();
    expect($normalAllowExport)->toBeBool()
        ->and($tryAllowExport)->toBeBool();
});

it('【docx】allow copy', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalAllowCopy = $docxNormal->allowCopy();
    $tryAllowCopy = $docxTry->allowCopy();
    expect($normalAllowCopy)->toBeBool()
        ->and($tryAllowCopy)->toBeBool();
});

it('【docx】allow recycle', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalAllowRecycle = $docxNormal->allowRecycle();
    $tryAllowRecycle = $docxTry->allowRecycle();
    expect($normalAllowRecycle)->toBeBool()
        ->and($tryAllowRecycle)->toBeBool();
});

it('【docx】track changes', function (): void {
    $testNormal = 'test_normal';
    $testTry = 'test_try';
    $docxNormal = Easy::docx($testNormal);
    $docxTry = Easy::docx($testTry);
    $normalTrackChanges = $docxNormal->trackChanges();
    $tryTrackChanges = $docxTry->trackChanges();
    expect($normalTrackChanges)->toBeBool()
        ->and($tryTrackChanges)->toBeBool();
});

it('【docx】get appends value', function (): void {
    $config = app()->get('config');
    $config->set('constant.status', [
        ['label' => '已启用', 'value' => 1],
        ['label' => '未启用', 'value' => 0],
    ]);

    Easy::schema('docx_append')->create();
    $docx = Easy::docx('docx_append');
    $docx->document()->store([
        'title' => '测试',
        'table_name' => 'docx',
        'quick_entry' => 1,
        'read_only' => 2,
        'is_publish' => 0,
        'is_single' => 1,
        'allow_rename' => 1,
        'track_changes' => 0,
        'status' => 1,
    ]);
    $data = $docx->document()->first()->toArray();
    expect($data)->toBeArray()
        ->and(array_keys($data))->toEqualCanonicalizing([
            'parent_table_name_text', 'quick_entry_text', 'read_only_text', 'is_publish_text', 'is_single_text', 'is_tree_text',
            'is_table_text', 'allow_import_text', 'allow_export_text', 'allow_recycle_text', 'allow_copy_text', 'allow_rename_text',
            'track_changes_text', 'status_text',
        ])->and($data['status_text'])->toBe('已启用')
        ->and($data['quick_entry'])->toBe('quick_entry');
});

it('【docx】get relations', function (): void {
});
