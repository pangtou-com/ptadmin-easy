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

namespace PTAdmin\Easy\Engine\Model;

use PTAdmin\Easy\Contracts\IDocx;
use PTAdmin\Easy\Engine\Model\Traits\AttributesTrait;
use PTAdmin\Easy\Engine\Model\Traits\BaseTrait;
use PTAdmin\Easy\Engine\Model\Traits\CreateTrait;

/**
 * @method self where($column, $operator = null, $value = null, $boolean = 'and')
 * @method self orWhere($column, $operator = null, $value = null)
 * @method self whereNot($column, $operator = null, $value = null, $boolean = 'and')
 * @method self orWhereNot($column, $operator = null, $value = null)
 * @method self whereColumn($first, $operator = null, $second = null, $boolean = 'and')
 * @method self orWhereColumn($first, $operator = null, $second = null)
 * @method self whereRaw($sql, $bindings = [], $boolean = 'and')
 * @method self orWhereRaw($sql, $bindings = [])
 * @method self whereIn($column, $values, $boolean = 'and', $not = false)
 * @method self orWhereIn($column, $values)
 * @method self whereNotIn($column, $values, $boolean = 'and')
 * @method self orWhereNotIn($column, $values)
 * @method self whereNull($columns, $boolean = 'and', $not = false)
 * @method self orWhereNull($column)
 * @method self whereNotNull($columns, $boolean = 'and')
 * @method self whereBetween($column, iterable $values, $boolean = 'and', $not = false)
 * @method self whereBetweenColumns($column, array $values, $boolean = 'and', $not = false)
 * @method self orWhereBetween($column, iterable $values)
 * @method self orWhereBetweenColumns($column, array $values)
 * @method self whereNotBetween($column, iterable $values, $boolean = 'and')
 * @method self whereNotBetweenColumns($column, array $values, $boolean = 'and')
 * @method self orWhereNotBetween($column, iterable $values)
 * @method self orWhereNotNull($column)
 * @method self whereDate($column, $operator, $value = null, $boolean = 'and')
 * @method self orWhereDate($column, $operator, $value = null)
 * @method self whereTime($column, $operator, $value = null, $boolean = 'and')
 * @method self orWhereTime($column, $operator, $value = null)
 * @method self mergeWheres($wheres, $bindings)
 * @method self groupBy(...$groups)
 * @method self groupByRaw($sql, array $bindings = [])
 * @method self having($column, $operator = null, $value = null, $boolean = 'and')
 * @method self orHaving($column, $operator = null, $value = null)
 * @method self orderBy($column, $direction = 'asc')
 * @method self orderByDesc($column)
 * @method self select($columns = ['*'])
 * @method self selectRaw($expression, array $bindings = [])
 * @method self join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method self joinWhere($table, $first, $operator, $second, $type = 'inner')
 * @method self joinSub($query, $as, $first, $operator = null, $second = null, $type = 'inner', $where = false)
 * @method self leftJoin($table, $first, $operator = null, $second = null)
 * @method self leftJoinWhere($table, $first, $operator, $second)
 * @method self leftJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method self rightJoin($table, $first, $operator = null, $second = null)
 * @method self rightJoinWhere($table, $first, $operator, $second)
 * @method self rightJoinSub($query, $as, $first, $operator = null, $second = null)
 * @method self crossJoin($table, $first = null, $operator = null, $second = null)
 */
class Document
{
    use AttributesTrait;
    use BaseTrait;
    use CreateTrait;

    /** @var IDocx 文档管理对象 */
    protected $docx;
    /** @var mixed 文档自定义控制器 */
    protected $control;
    /** @var EasyModel|EasyModel[] */
    protected $model;

    /** @var \Illuminate\Database\Eloquent\Builder */
    protected $query;

    public function __construct(IDocx $docx)
    {
        $this->docx = $docx;
    }

    public function __call($name, $arguments)
    {
        if (method_exists($this->query(), $name)) {
            $this->model = null;
            \call_user_func_array([$this->query(), $name], $arguments);
        }

        return $this;
    }

    public function docx(): IDocx
    {
        return $this->docx;
    }

    /**
     * 获取模型对象.
     *
     * @return EasyModel
     */
    public function model(): EasyModel
    {
        if (null === $this->model) {
            $this->model = $this->newModel();
        }

        return $this->model;
    }

    /**
     * 获取查询对象.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        if (null === $this->query) {
            $this->query = $this->newModel()->newQuery();
        }

        return $this->query;
    }

    /**
     * 创建模型对象.
     *
     * @return EasyModel
     */
    public function newModel(): EasyModel
    {
        if ($this->docx->allowRecycle()) {
            return EasyDeleteModel::make($this->docx, $this);
        }

        return EasyModel::make($this->docx, $this);
    }

    /**
     * 创建查询对象.
     */
    public function newQuery(): self
    {
        $this->query = null;
        $this->query();

        return $this;
    }

    /**
     * 设置自定义控制器.
     *
     * @param $control
     *
     * @return $this
     */
    public function setControl($control): self
    {
        $this->control = $control;

        return $this;
    }

    public function getControl()
    {
        if (null === $this->control) {
            $this->control = $this->docx->getControl();
        }
        if (\is_string($this->control) && '' !== $this->control) {
            $this->control = app($this->control, ['docx' => $this->docx, 'document' => $this]);
        }

        return $this->control;
    }

    public function trigger($event, $params = [])
    {
        $control = $this->getControl();
        if (null === $control) {
            return null;
        }
        if (method_exists($control, $event)) {
            return \call_user_func_array([$control, $event], $params);
        }

        return null;
    }
}
