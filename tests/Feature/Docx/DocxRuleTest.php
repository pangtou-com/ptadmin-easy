<?php

namespace Docx;


use Illuminate\Validation\Rules\Unique;
use PTAdmin\Easy\Easy;

beforeEach(function (): void {
    $this->modName = 'docx';
    $this->tabelName = 'docx';
    app()->setBasePath(config('test_path'));
});

it('【docx】get rules', function (): void {
    // 必填、唯一、长度限制
    // Easy::schema("rule.docx_rules")->create();
    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'fields' => [
            ["name" => "title", "label" => "标题", "is_required" => 1, "max" => 255]
        ]
    ]);
    list($rules, $arr) = $docx->getRules(null);


    expect($rules)->toEqualCanonicalizing([
        'title' => ['required', 'max:255']
    ]);
});

it('【docx】get unique rules', function (): void {
    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'fields' => [
            ["name" => "title", "label" => "标题", "is_unique" => 1]
        ]
    ]);

    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'allow_' => 0,
        'fields' => [
            ["name" => "title", "label" => "标题", "is_unique" => 1]
        ]
    ]);

    list($rules, $arr) = $docx->getRules(null);
    dd($rules,$arr);
    list($rule, $arr) = $docx->getRules(1);
    expect($rules['title'][0])->toBeInstanceOf(Unique::class);

    dd((\ReflectionClass::export($rules['title'][0]))->__toString());
    expect($rule['title'][0])->toBeInstanceOf(Unique::class);

});

it('【docx】get text rules', function (): void {
    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'fields' => [
            ["name" => "title", "label" => "标题", "is_required" => 1],
            ["name" => "title1", "label" => "标题", "is_required" => 0, "length" => 20],
        ]
    ]);

    list($rules, $arr) = $docx->getRules(null);
    expect($rules)->toBeArray()->toHaveKey('title')->toEqualCanonicalizing([
        'title' => ['required', 'max:255'],
        'title1' => ['max:20'],
    ]);
});

it('【docx】get number rules', function (): void {
    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'fields' => [
            ["name" => "weight", "type" => "number", "label" => "权重", "min" => 0, "max" => 255, "step" => 1, "default" => 99],
        ]
    ]);

    list($rules, $arr) = $docx->getRules(null);
    expect($rules)->toBeArray()->toHaveKey('weight')->toEqualCanonicalizing([
        'weight' => ['min: 0', 'max:255']
    ]);

});

it('【docx】get files rules', function (): void {
    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'fields' => [
            ["name" => "test_file", "type" => "files", "label" => "文件上传测试",  "extends" => [
                "limit" => 1
            ]],
            ["name" => "test_file1", "type" => "files", "label" => "文件上传测试", "extends" => [
                "mimes" => "image/*",
                "size" => 1024 * 1024 * 2,
                "limit" => 5,
            ]],
        ]
    ]);
    // url = "http://127.0.0.1:8080/store/fine.png?id=";

    list($rules, $arr) = $docx->getRules(null);

    expect($rules)->toBeArray()->toHaveKey('test_file')->toEqualCanonicalizing([
        "test_file" => ["array", "max:1"],
        "test_file1" => ["array", "max:5"],
        "test_file1.*" => ["mimes:image/*", "max:".(1024 * 1024 * 2)]
    ]);

});

it('【docx】get select rules', function (): void {
    $config = app()->get('config');
    $config->set(
        'constant',
        [
            "status" => [
                ['label' => '删除', 'value' => 2],
                ['label' => '启用', 'value' => 1],
                ['label' => '禁用', 'value' => 0],
            ]
        ],
    );
    $configDocx = Easy::docx([
        'table_name' => 'config_select',
        'module' => 'docx',
        'fields' => [
            ["name" => "status", "type" => "select", "label" => "状态", "default" => 0,"extends" => [
                    "type" => "config",
                    "key" => "constant.status",
                    "intro" => "这个配置来源与laravel的系统配置信息，配置格式与options一致"
                ]
            ],
        ]
    ]);
    $contentDocx = Easy::docx([
        'table_name' => 'content_select',
        'module' => 'docx',
        'fields' => [
            ["name" => "status", "type" => "select", "label" => "状态", "default" => 0,"extends" => [
                    "type" => "textarea",
                    "content" => "aa=d\nb=e\nc=f",
                    "intro" => "这个配置来源与laravel的系统配置信息，配置格式与options一致"
                ]
            ],
        ]
    ]);
    $docx = Easy::docx([
        'table_name' => 'select',
        'module' => 'docx',
        'fields' => [
            ["name" => "status", "type" => "select", "label" => "状态", "options" => [
                ["label" => "启用", "value" => 1],
                ["label" => "禁用", "value" => 0],
            ]],
        ]
    ]);
    $multipleDocx = Easy::docx([
        'table_name' => 'multiple_select',
        'module' => 'docx',
        'fields' => [
            ["name" => "status", "type" => "checkbox", "label" => "状态", "multiple" => 1, "options" => [
                ["label" => "启用", "value" => 1],
                ["label" => "禁用", "value" => 0],
            ]],
        ]
    ]);

    list($configRules, $configArr) = $configDocx->getRules(null);
    list($contentRules, $contentArr) = $contentDocx->getRules(null);
    list($rules, $arr) = $docx->getRules(null);
    list($multipleRules, $multipleArr) = $multipleDocx->getRules(null);

    expect($configRules)->toBeArray()->toHaveKey('status')->toEqualCanonicalizing([
        "status" => ["in:2,1,0"]
    ])->and($contentRules)->toBeArray()->toHaveKey('status')->toEqualCanonicalizing([
        "status" => ["in:aa,b,c"]
    ])->and($rules)->toBeArray()->toHaveKey('status')->toEqualCanonicalizing([
        "status" => ["in:1,0"]
    ])->and($multipleRules)->toBeArray()->toHaveKey('status')->toEqualCanonicalizing([
        "status" => ["in:1,0"]
    ]);
});

it('【docx】get date rules', function (): void {
    $docx = Easy::docx([
        'table_name' => 'docx_rules',
        'module' => 'docx',
        'fields' => [
            [
                "name" => "test_at",
                "label" => "日期",
                "type" => "date",
            ],
        ]
    ]);
    list($rules, $arr) = $docx->getRules(null);
    expect($rules)->toBeArray()->toHaveKey("test_at")->toEqualCanonicalizing([
        "test_at" => ["date"]
    ]);

});