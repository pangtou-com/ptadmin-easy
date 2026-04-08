<?php

declare(strict_types=1);

/**
 *  ============================================================================
 *  ******************************【PTAdmin/Easy】******************************
 *  ============================================================================
 *  Copyright (c) 2022-2025 【重庆胖头网络技术有限公司】，并保留所有权利。
 *  ============================================================================
 *  站点首页:  https://www.pangtou.com
 *  文档地址:  https://docs.pangtou.com
 *  联系邮箱:  vip@pangtou.com
 */

/**
 * 新增钩子.
 *
 * @param $event
 * @param $callback
 */
function add_hook($event, $callback): void
{
}

/**
 * 返回数据表名称.
 *
 * @param string $tableName
 *
 * @return string
 */
function get_resource_table(string $tableName): string
{
    $prefix = config('database.prefix', '');
    if ('' === $prefix) {
        return $tableName;
    }

    return 0 === strncmp($prefix, $tableName, strlen($prefix)) ? $tableName : $prefix.$tableName;
}

/**
 * 将表前缀替换为空的.
 *
 * @param mixed $tableName
 */
function table_to_prefix_empty($tableName): string
{
    $prefix = config('easy.db_prefix', '');
    if ('' === $prefix) {
        return $tableName;
    }

    return \Illuminate\Support\Str::replaceFirst($prefix, '', $tableName);
}

/**
 * 获取缓存文件.
 *
 * @return string
 */
function get_cache_file(): string
{
    $filename = config('easy.cache_file_name');
    $path = config('easy.cache_path');

    return $path.\DIRECTORY_SEPARATOR.$filename;
}
