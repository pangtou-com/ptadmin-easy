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

return [
    'cache_path' => base_path('/bootstrap/cache'),
    'schema' => [
        'repository' => null,
        'tables' => [
            'mods' => 'mods',
            'mod_fields' => 'mod_fields',
        ],
        'version' => [
            'table' => 'mod_versions',
            'resource_column' => 'name',
            'module_column' => 'module',
            'schema_column' => 'schema_json',
            'published_column' => 'is_current',
            'store' => null,
        ],
    ],
    'audit' => [
        'store' => null,
        'table' => 'audit_logs',
    ],
];
