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

namespace PTAdmin\Easy\Engine;

use Illuminate\Support\Str;
use PTAdmin\Easy\Components\Component;
use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Contracts\IEasyManager;
use PTAdmin\Easy\Engine\Docx\Docx;
use PTAdmin\Easy\Engine\Docx\DocxNameParser;
use PTAdmin\Easy\Engine\Model\Document;
use PTAdmin\Easy\Engine\Schema\Schema;
use PTAdmin\Easy\Exceptions\EasyException;

final class EasyManager implements IEasyManager
{
    private const TARGET = [
        'schema' => Schema::class,
        'component' => Component::class,
    ];

    /** @var IDocx[] 已解析的docx集合. */
    private static $docx = [];

    public function __call($name, $arguments)
    {
        $class = self::TARGET[$name] ?? null;
        if (null !== $class && class_exists($class)) {
            return (new \ReflectionClass($class))->newInstance(...$arguments);
        }

        throw new EasyException("未定义的方法：{$name}");
    }
    /**
     * 加载文档对象.
     *
     * @param string|array $docx
     * @param string $module
     *
     * @return IDocx
     */
    public function docx($docx, string $module = ''): IDocx
    {
        if (is_array($docx)) {
            if (!isset($docx['table_name'])) {
                throw new EasyException('Table name is required.');
            }
            $name = $docx['table_name'];
        } else {
            $name = $this->getDocxName($docx, $module);
        }
        if (isset(self::$docx[$name])) {
            return self::$docx[$name];
        }
        return self::$docx[$name] = app(IDocx::class, ['docx' => $docx, 'module' => $module]);
    }

    public function document(string $docx, string $module = ''): Document
    {
        return new Document($this->docx($docx, $module));
    }

    /**
     * 文档是否已存在.
     *
     * @param string $docx
     *
     * @return bool
     */
    public function hasDocx(string $docx): bool
    {
        // 在已加载的数据中查找文档
        if (isset(self::$docx[$docx])) {
            return true;
        }
        // 读取数据
        return false;
    }

    /**
     * 是否为开发模式.
     *
     * @return bool
     */
    public function isDevelop(): bool
    {
        return true === (bool) config('app.debug');
    }

    /**
     * 兼容多种写法：
     * 1. 'demo::docx' 所属demo模块下的文档
     * 2. 'demo.docx' app 默认模块下有文件层级的文档
     * 3. 'demo' app 默认模块下的文档.
     *
     * @param $docx
     * @param string $module
     *
     * @return mixed|string
     */
    private function getDocxName($docx, string $module = '')
    {
        if (Str::contains($docx, ['.', '::'])) {
            $docx = DocxNameParser::handle($docx, $module)->getDocxName();
        }

        return $docx;
    }
}
