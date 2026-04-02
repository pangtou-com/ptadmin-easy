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

use Illuminate\Support\Facades\Route;
use PTAdmin\Easy\Controller;

// 文档管理模块
Route::get('/docx', [Controller\DocxController::class, 'index']);
Route::post('/docx', [Controller\DocxController::class, 'store']);
Route::put('/docx/{id}', [Controller\DocxController::class, 'store']);
Route::delete('/docx/{id}', [Controller\DocxController::class, 'delete']);
Route::get('/docx/{table_name}', [Controller\DocxController::class, 'detail']);
