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

namespace PTAdmin\Easy\Utils;

use Illuminate\Pagination\LengthAwarePaginator;

class ResponseVo
{
    private static $code = 0;
    private static $error_code = 10000;

    public static function success($data = null, $message = ''): \Illuminate\Http\JsonResponse
    {
        $message = '' === $message ? __('common.success') : $message;
        $result = ['code' => self::$code, 'message' => $message];

        if (null !== $data) {
            $result['data'] = $data;
        }

        return response()->json($result);
    }

    public static function fail($error = '', $code = 10000): \Illuminate\Http\JsonResponse
    {
        if (self::$code === (int) $code) {
            $code = self::$error_code + 1;
        }
        if (\is_array($error)) {
            $result = array_merge($error, ['code' => $code]);
        } else {
            $result = ['code' => $code, 'message' => $error];
        }

        return response()->json($result);
    }

    /**
     * 返回翻页列表.
     *
     * @param $data
     * @param string $message
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function pages($data = null, string $message = ''): \Illuminate\Http\JsonResponse
    {
        $result = ['code' => self::$code, 'message' => $message];
        if ($data instanceof LengthAwarePaginator) {
            $result['data']['total'] = $data->total();
            $result['data']['results'] = $data->items();
        } else {
            $result['data']['total'] = $data['total'] ?? 0;
            $result['data']['results'] = $data['data'] ?? [];
        }

        return response()->json($result);
    }
}
