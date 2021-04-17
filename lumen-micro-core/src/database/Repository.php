<?php

namespace Aha\LumenMicroCore\Database;

use Illuminate\Database\Eloquent\Builder;
// use Illuminate\Database\Eloquent\Collection;
use Aha\LumenMicroCore\Traits\Database\EngineEloquentBuilderTrait;
use DB;

abstract class Repository implements BaseRepositoryInterface
{
    use EngineEloquentBuilderTrait;

    protected $model;
    protected $sortProperty = null;
    // 0 = ASC, 1 = DESC
    protected $sortDirection = 0;
    protected static $columns = ['*'];
    protected $query;

    abstract protected function getModel();

    final public function __construct()
    {
        $this->model = $this->getModel();
    }

    /**
     * Get instance
     * @param
     * @return instance of Model
     */
    public function instance()
    {
        return $this->model;
    }

    /**
     * set columns for select clause
     * @param
     * @return instance of Model
     */
    public function setColumns(array $columns = ['*'])
    {
        static::$columns = $columns;
    }

    /**
     * set columns for select clause
     * @param
     * @return instance of Model
     */
    public function find($attributes = [])
    {
        // $attributes = [
        //     'select' => [],
        //     'where' => [],
        //     'sort' => [],  //['id' => 'asc']
        //     'limit' => 10,
        //     'page' => 1,
        //     'groupBy' => [],
        //     'having' => [],
        // ];
        $query = $this->createQueryBuilder();
        $this->applyResourceOptions($query, $attributes);
        return $query->get();
    }

    public function findOne($attributes = [])
    {
        unset($attributes['limit']);
        unset($attributes['page']);
        $query = $this->createQueryBuilder();
        $this->applyResourceOptions($query, $attributes);
        return $query->first();
    }

    /**
     * Get total resources
     * @param  string $column
     * @param  array $values
     * @param  array $options
     * @return Collection
     */
    public function count(array $attributes)
    {
        unset($attributes['limit']);
        unset($attributes['page']);
        unset($attributes['select']);

        $query = $this->createQueryBuilder();
        $this->applyResourceOptions($query, $attributes);
        return $query->count();
    }

     /**
     * Delete a resource by its primary key
     * @param  mixed $id , $physical = true or false
     * @return void
     */
    public function delete($clauses, $physical = false)
    {
        $query = $this->createQueryBuilder();
        $query->where($clauses);
        if (!$physical) {
            $query->where('del_flg', 0);
            return $query->update(["del_flg" => 1]);
        } else {
            return $query->delete();
        }
    }

    /**
     * create new record
     * @param  array $data
     * @return Collection
     */
    public function create(array $data)
    {
        $model = $this->model;

        $model->fill($data);
        $model->save();

        return $model;
    }

    /**
     * update record
     * @param  array $data
     * @return Collection
     */
    public function updateById($id, array $data)
    {
        $query = $this->createQueryBuilder();
        return $query->where($this->getPrimaryKey($query), $id)
              ->update($data);
    }

    public function update($whereClause, $data)
    {
        $query = $this->createQueryBuilder();
        $query->where($whereClause);
        return $query->update($data);
    }


    /**
     * Get all resources
     * @param  array $options
     * @return Collection
     */
    public function get(array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        return $query->get();
    }
    
    /**
     * Get a resource by its primary key
     * @param  mixed $id
     * @param  array $options
     * @return Collection
     */
    public function getById($id, array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        return $query->find($id, static::$columns);
    }

    /**
     * Get all resources ordered by recentness
     * @param  array $options
     * @return Collection
     */
    public function getRecent(array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->orderBy($this->getCreatedAtColumn(), 'DESC');
        return $query->get(static::$columns);
    }

    /**
     * Get all resources by a where clause ordered by recentness
     * @param  string $column
     * @param  mixed $value
     * @param  array $options
     * @return Collection
     */
    public function getRecentWhere($column, $value, array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->orderBy($this->getCreatedAtColumn(), 'DESC');
        return $query->get(static::$columns);
    }

    /**
     * Get latest resource
     * @param  array $options
     * @return Collection
     */
    public function getLatest(array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->orderBy($this->getCreatedAtColumn(), 'DESC');
        return $query->first(static::$columns);
    }

    /**
     * Get latest resource by a where clause
     * @param  string $column
     * @param  mixed $value
     * @param  array $options
     * @return Collection
     */
    public function getLatestWhere($column, $value, array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->orderBy($this->getCreatedAtColumn(), 'DESC');
        return $query->first(static::$columns);
    }
    /**
     * Get resources by multiple where clauses
     * @param  array $clauses
     * @param  array $options
     * @deprecated
     * @return Collection
     */
    public function getWhere(array $clauses, array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->where($clauses);
        // echo "<pre>";print_r( $query->toSql() );echo "</pre>" ;die;
        return $query->get(static::$columns);
    }

    /**
     * Get resources where a column value exists in array
     * @param  string $column
     * @param  array $values
     * @param  array $options
     * @return Collection
     */
    public function getWhereIn($column, array $values, array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->whereIn($column, $values);
        return $query->get(static::$columns);
    }

    //
    public function sqlFoundRows()
    {
        $total = DB::select(DB::raw('SELECT FOUND_ROWS() as total'));
        return isset($total[0]) ? $total[0]->total : 0;
    }

    /**
     * Create query
     * @param  array $clauses
     * @param  array $options
     * @deprecated
     * @return Collection
     */
    public function builder(array $clauses, array $options = [])
    {
        $query = $this->createBaseBuilder($options);
        $query->where($clauses);
        return $query;
    }

    /**
     * return object of DB
     * @param  array $options
     * @return Collection
     */
    public function getQuery()
    {
        return $this->query;
    }

     /**
     *
     * @param  array $options
     * @return Collection
     */
    public function execute($columns = [])
    {
        return !empty($columns) ? $this->query->get($columns) : $this->query->get();
    }

    /**
     * Creates a new query builder with Optimus options set
     * @param  array $options
     * @return Builder
     */
    protected function createBaseBuilder(array $options = [])
    {
        $query = $this->createQueryBuilder();
        $this->applyResourceOptions($query, $options, $this->mapping);

        if (empty($options['sort'])) {
            $this->defaultSort($query, $options);
        }
        return $query;
    }

    /**
     * Creates a new query builder
     * @return Builder
     */
    protected function createQueryBuilder()
    {
        return $this->model->newQuery();
    }

    /**
     * Get primary key name of the underlying model
     * @param  Builder $query
     * @return string
     */
    protected function getPrimaryKey(Builder $query)
    {
        return $query->getModel()->getKeyName();
    }

    /**
     * Order query by the specified sorting property
     * @param  Builder $query
     * @param  array $options
     * @return void
     */
    protected function defaultSort(Builder $query, array $options = [])
    {
        if (isset($this->sortProperty)) {
            $direction = $this->sortDirection === 1 ? 'DESC' : 'ASC';
            $query->orderBy($this->sortProperty, $direction);
        }
    }

    /**
     * Get the name of the "created at" column.
     * More info to https://laravel.com/docs/5.4/eloquent#defining-models
     * @return string
     */
    protected function getCreatedAtColumn()
    {
        $model = $this->model;
        return ($model::CREATED_AT) ? $model::CREATED_AT : 'created_at';
    }

    /**
     * Creates a new query builder with Optimus options set, add table name to column
     * @param  array $clauses
     * @return Builder
     */
    protected function createWhere(Builder $query, $clauses = [], $whereStatement = 'where')
    {
        $table = $this->model->getTable();
        foreach ($clauses as $column => $value) {
            if (is_int($column)) {
                if (strpos($value[0], '.') === false) {
                    $value[0] = sprintf('%s.%s', $table, $value[0]);
                }
                call_user_func_array([$query, $whereStatement], [
                    [$value]
                ]);
            } else {
                if (strpos($column, '.') === false) {
                    $column = sprintf('%s.%s', $table, $column);
                }
                call_user_func_array([$query, $whereStatement], [
                    $column,
                    $value
                ]);
            }
        }
    }
}
