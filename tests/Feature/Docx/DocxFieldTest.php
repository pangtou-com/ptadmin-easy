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

beforeEach(function (): void {
    app()->setBasePath(config('test_path'));
    $this->tabelName = 'docx';
});

it('【docxField】getComponent', function (): void {
    \PTAdmin\Easy\Easy::schema($this->tabelName)->create();
    $docxField = \PTAdmin\Easy\Easy::docx($this->tabelName);
    $docxField->document()->store([
        'title' => '测试',
        'table_name' => 'docx',
        'content' => '测试',
        'cover' => 'https://www.pangtou.com/logo.png',
        'module' => 'adsad',
        'status' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $data = $docxField->document()->first();
    $docxField = new \PTAdmin\Easy\Engine\Docx\DocxField($data, $docxField);
    $component = $docxField->getComponent();
    expect($component)->toBeInstanceOf(\PTAdmin\Easy\Contracts\IComponent::class);
});

it('【docxField】isRelation', function (): void {
    \PTAdmin\Easy\Easy::schema($this->tabelName)->create();
    $docx = \PTAdmin\Easy\Easy::docx($this->tabelName);
    $field = $docx->getField("parent_table_name");
    $component = $field->isRelation();
    dd($component);
});
