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
            ["name" => "title", "label" => "标题", "is_required" => 1, "max" => 666]
        ]
    ]);

    list($rules, $arr) = $docx->getRules(null);
    expect($rules)->toEqualCanonicalizing([
        'title' => ['required', 'max:255']
    ]);
});

it('【docx】get number rules', function (): void {


});

it('【docx】get file rules', function (): void {


});

it('【docx】get select rules', function (): void {


});

it('【docx】get date rules', function (): void {


});