<?php

namespace Aha\LumenMicroCore\Traits\Database;

use DB;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait EngineEloquentBuilderTrait
{
    public $queryBuilder;

    private $mapping = [];

// ###########################DEFAULT MAPPING#####################################
/*
    $attributes = [
        'select' => [],
        'where' => [],
        'sort' => [],  //['id' => 'asc']
        'limit' => 10,
        'page' => 1
    ];
/*
    /*
    * SELECT: select clause
    *  - [ID] => TABLE.ID
    *  - [*.ID] => ID
    *  - [ID] ~ Columns['id' => 'c.id'] => c.id
    *
    */
// #################################################################################


    /**
     * Apply resource options to a query builder
     * @param  Builder $queryBuilder
     * @param  array $options
     * @return Builder
     */
    protected function applyResourceOptions(Builder $queryBuilder, array $options = [])
    {
        extract($options);

        if (!empty($select) && is_array($select)) {
            $queryBuilder->select($this->createColumnsMapping($select));
        } 

        if (isset($distinct)) {
            $queryBuilder->distinct();
        }

        if (isset($where) && is_array($where)) {
            $this->applyFilterGroups($queryBuilder, $where);
        }

        //create joins
        if (isset($joins) && is_array($joins)) {
            $this->applyJoin($queryBuilder, $joins);
        }

        $_joins = isset($joins) ? $joins : [];
        if (isset($sort) && is_array($sort)) {
            $this->applySorting($queryBuilder, $sort, $_joins);
        }

        if (!empty($limit)) {
            $queryBuilder->limit($limit);
        }

        // page
        if (isset($page) && $page >= 0) {
            $page = ($page == 1 || $page == 0) ? 0 :  $page -1;
            if (!isset($limit)) {
                $limit = env('LIMIT_ITEM_PER_PAGE', 20);
                $queryBuilder->limit($limit);
            }
            $queryBuilder->skip($page * $limit);
        }

        if (isset($groupBy) && is_array($groupBy)) {
            $this->applyGroupby($queryBuilder, $groupBy, $_joins);
        }

        return $queryBuilder;
    }

    /**
     * @param Builder $queryBuilder
     * @param array $filterGroups
     * @param array $previouslyJoined
     * @return array
     */
    protected function applyFilterGroups(Builder $queryBuilder, array $filters = [], array $previouslyJoined = [])
    {
        $joins = [];
        $whereMapping = !empty($this->mapping['where']) ? $this->mapping['where'] : [];
        $columns = [];
        $queryBuilder->where(function (Builder $query) use ($filters, $whereMapping, $columns) {

            foreach ($filters as $column => $items) {
                $col = $column;
                $operator = 'eq';

                if (is_string($items) || is_numeric($items)) {
                    $val = $items;
                    $operator = isset($whereMapping[$column]['operator']) ? $whereMapping[$column]['operator'] : $operator;
                } elseif (is_array($items)) {
                    $firtItem = isset($items[0]) ? $items[0] : null;
                    $secondItem = isset($items[1]) ? $items[1] : null;
                    $thirdItem = isset($items[2]) ? $items[2] : null;
                    $val = $secondItem ?? $secondItem;
                    // if ($secondItem) {
                        switch ($firtItem) {
                            case '=': 
                                $operator = 'eq';
                                break; 
                            case '>': 
                                $operator = 'gt';
                                break;
                            case '>=': 
                                $operator = 'gte';
                                break;
                            case '<': 
                                $operator = 'lt';
                                break;
                            case '<=': 
                                $operator = 'lte';
                                break;
                            case '!=': 
                                $operator = '!=';
                                break;
                            case 'nin':
                                $val = array_values($secondItem);
                                $operator = 'nin';
                                break;  
                            case 'between':
                                if ($secondItem && $thirdItem) {
                                    $operator = 'bt';
                                    $val = [$secondItem, $thirdItem];
                                } elseif ($secondItem && !$thirdItem) {
                                    $operator = 'gte';
                                    $val = $secondItem;
                                } elseif (!$secondItem && $thirdItem) {
                                    $operator = 'lte';
                                    $val = $thirdItem;
                                }
                                break;
                            case 'contains':
                                $operator = 'ct';
                                break; 
                            case 'startsWith':
                                $operator = 'sw';
                                break;
                            case 'endsWith':
                                $operator = 'ew';
                                break;
                            default: 
                                $val = array_values($items);
                                $operator = 'in';
                        }
                    // }

                  

                    // else if (!empty($item['from']) && !empty($item['to'])) {
                    //     $val = [];
                    //     if (!empty($item['from'])) {
                    //         $val[] = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $item['from'])));
                    //     }
                    //     if (isset($item['to'])) {
                    //         $val[] = date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $item['to'])));
                    //     }

                    //     $operator = isset($whereMapping[$column]['operator']) ? $whereMapping[$column]['operator'] : $operator;
                    // } elseif (!empty($item['from'])) {
                    //     $val = date('Y-m-d', strtotime(str_replace('/', '-', $item['from'])));
                    //     $operator = 'lte';
                    // } elseif (!empty($item['to'])) {
                    //     $val = date('Y-m-d', strtotime(str_replace('/', '-', $item['to'])));
                    //     $operator = 'gte';
                    // } elseif (is_array($item)) {
                    //     $val = array_values($item);
                    //     $operator = 'in';
                    // } else {
                    //     $val = $item;
                    // }
                } else {
                    $val = $items;
                }

                $filter = [
                    'key' => $col,
                    'value' => $val,
                    'operator' => $operator
                ];

                $this->applyCondition($query, $filter);
            }
        });

        foreach (array_diff($joins, $previouslyJoined) as $join) {
            $this->joinRelatedModelIfExists($queryBuilder, $join);
        }

        return $joins;
    }

    /**
     * @param Builder $queryBuilder
     * @param array $filter
     * @param bool|false $or
     * @param array $joins
     */
    protected function applyCondition(Builder $queryBuilder, array $filter)
    {
        $or = false;
        $not = null;

        // $value, $not, $key, $operator
        extract($filter);

        $dbType = $queryBuilder->getConnection()->getDriverName();

        $table = $queryBuilder->getModel()->getTable();

        if ($value === 'null' || $value === '') {
            $method = $not ? 'WhereNotNull' : 'WhereNull';
            call_user_func([$queryBuilder, $method], sprintf('%s.%s', $table, $key));
        } else {
            
            $method = filter_var($or, FILTER_VALIDATE_BOOLEAN) ? 'orWhere' : 'where';
            $clauseOperator = null;
            $databaseField = null;
            switch ($operator) {
                case 'ct':
                case 'sw':
                case 'ew':
                    $valueString = [
                        'ct' => '%' . $value . '%', // contains
                        'ew' => '%' . $value, // ends with
                        'sw' => $value . '%' // starts with
                    ];
                    $clauseOperator = ($not ? 'NOT' : '') . (($dbType === 'postgres') ? 'ILIKE' : 'LIKE');
                    $value = $valueString[$operator];
                    break;
                case 'gt':
                    $clauseOperator = $not ? '<' : '>';
                    break;
                case 'gte':
                    $clauseOperator = $not ? '<' : '>=';
                    break;
                case 'lte':
                    $clauseOperator = $not ? '>' : '<=';
                    break;
                case 'lt':
                    $clauseOperator = $not ? '>' : '<';
                    break;
                case 'in':
                    if ($or === true) {
                        $method = 'orWhereIn';
                    } else {
                        $method = 'whereIn';
                    }
                    $clauseOperator = false;
                    break; 
                    // not in
                case 'nin':
                    if ($or === true) {
                        $method = 'orWhereNotIn';
                    } else {
                        $method = 'whereNotIn';
                    }
                    
                    $clauseOperator = false;
                    break;
                case 'bt':
                    if ($or === true) {
                        $method = $not === true ? 'orWhereNotBetween' : 'orWhereBetween';
                    } else {
                        $method = $not === true ? 'whereNotBetween' : 'whereBetween';
                    }
                    
                    $clauseOperator = false;
                    break;
                case '!=':
                    $clauseOperator = '<>';
                    break;
                case 'eq':
                default:
                    $clauseOperator = $not ? '!=' : '=';
                    break;
            }

            // If we do not assign database field, the customer filter method
            // will fail when we execute it with parameters such as CAST(%s AS TEXT)
            // key needs to be reserved

            /*if (is_null($databaseField)) {
                $databaseField = sprintf('%s.%s', $table, $key);
            }*/

            $databaseField = $this->createColumnsMapping($key);

            $customFilterMethod = $this->hasCustomMethod('filter', $key);
            
            if ($customFilterMethod) {
                call_user_func_array([$this, $customFilterMethod], [
                    $queryBuilder,
                    $method,
                    $clauseOperator,
                    $value,
                    $clauseOperator // @deprecated. Here for backwards compatibility
                ]);

                // column to join.
                // trying to join within a nested where will get the join ignored.
                $joins[] = $key;
            } else {
                // In operations do not have an operator
                if (in_array($operator, ['in', 'bt', 'nin'])) {
                    call_user_func_array([$queryBuilder, $method], [
                        $databaseField, $value
                    ]);
                } else {
                    call_user_func_array([$queryBuilder, $method], [
                        $databaseField, $clauseOperator, $value
                    ]);
                }
            }
        }
    }

    /**
     * @param Builder $queryBuilder
     * @param array $sorting
     * @param array $previouslyJoined
     * @return array
     */
    protected function applySorting(Builder $queryBuilder, array $sorting, array $joins = [])
    {
        foreach ($sorting as $column => $rule) {
            if (!empty($joins)) {
                $column = $this->createColumnsMapping($column);
            } 
            $queryBuilder->orderBy($column, $rule);
        }
    }

    /**
     * @param Builder $queryBuilder
     * @param array $sorting
     * @param array $previouslyJoined
     * @return array
     */
    protected function applyGroupby(Builder $queryBuilder, array $groupBy, array $joins = [])
    {
        if (!empty($joins)) {
            $groupBy = $this->createColumnsMapping($groupBy);
        } 
        $queryBuilder->groupBy($groupBy);
    }

    /**
     * @param Builder $queryBuilder
     * @param array $joins
     * @return array
     */

    protected function applyJoin(Builder $queryBuilder, array $joins)
    {
        $table = $queryBuilder->getModel()->getTable();
        foreach ($joins as $join) {
            $type = 'join';
            if (!empty($join['type']) && $join['type'] == 'left') {
                $type = 'leftJoin';
            } elseif (!empty($join['type']) && $join['type'] == 'inner') {
                $type = 'join';
            }

            $joinTable = $join['table'];
            $joinTableAlias = $joinTable;
            if (strpos($joinTable, '=>') !== false) {
                $arr = explode('=>', $joinTable);

                $joinTable = DB::raw(sprintf('%s AS ' . $arr[1], $arr[0]));
                $joinTableAlias = $arr[1];
            }

            $foreignKey = isset($join['foreignKey']) ? $join['foreignKey'] : $table . '.id';
            $localKey = isset($join['localKey']) ? $join['localKey'] : $joinTableAlias . '.id';

            if (!empty($join['clause'])) {
                //process clause
                $this->onJoin($queryBuilder, $type, $joinTable, $join['clause']);
            } else {
                $queryBuilder->$type($joinTable, $foreignKey, '=', $localKey);
            }
        }
    }

    private function onJoin(Builder $queryBuilder, $type, $joinTable, array $clause)
    {
        $queryBuilder->$type($joinTable, function ($j) use ($clause) {
            $j->on($clause['on'][0], '=', $clause['on'][1]);

            if (!empty($clause['where'])) {
                $where = $clause['where'];

                if (is_string($where[0])) {$this->
                    $this->createAndJoinWhere($j, $where[0], $where[1], $where[2]);
                } else {
                    foreach ($where as $whereItem) {
                        $this->createAndJoinWhere($j, $whereItem[0], $whereItem[1], $whereItem[2]);
                    }
                }
            }

            if (!empty($clause['orOn'])) {
                $where = $clause['orOn'];
                if (is_string($where[0])) {
                    $j->orOn($where[0], $where[1], $where[2]);
                } else {
                    foreach ($where as $whereItem) {
                        $j->orOn($whereItem[0], $whereItem[1], $whereItem[2]);
                    }
                }
            }
        });
    }

    private function createAndJoinWhere(&$j, $col, $operator, $value)
    {
        $operator = strtolower(trim($operator));
        switch ($operator) {
            case 'in' :
                $j->whereIn($col, $value);
                break;
            default :
                $j->where($col, $operator, $value);
                break;
        };
    }

    /**
     * @param $type
     * @param $key
     * @return bool|string
     */
    private function hasCustomMethod($type, $key)
    {
        $methodName = sprintf('%s%s', $type, Str::studly($key));
        if (method_exists($this, $methodName)) {
            return $methodName;
        }

        return false;
    }

    /*
     * @params:
     *      $columns: string or array
     *      $mapping: array, (columns index in $this->mapping)
     * */
    private function createColumnsMapping($columns = [])
    {
        $table = $this->getModel()->getTable();
        if (is_string($columns) || is_numeric($columns)) {
            if (substr($columns, 0, 2) == '*.') {
                $return = substr($columns, 2);
            } elseif (strpos($columns, '.')) {
                $return = $columns;
            } else {
                $return = $table . '.' . $columns;
            }
        } elseif (is_array($columns)) {
            $return = array_map(function($column) use ($table) {
                if (!is_string($column) ||  strpos($column, '.')) {
                    return $column;
                } elseif (substr($column, 0, 2) == '*.') {
                    return substr($column, 2);
                } else {
                    return $table . '.' . $column;
                }
            }, $columns);
        }

        return $return;
    }
}
