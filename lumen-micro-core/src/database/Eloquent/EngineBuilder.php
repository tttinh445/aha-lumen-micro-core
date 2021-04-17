<?php
/**
 * ''
 *
 * Date: 5/27/18
 * Time: 12:34 PM
 */

namespace Aha\LumenMicroCore\Database\Eloquent;

use InvalidArgumentException;

use Carbon\Carbon;

class EngineBuilder
{
    /**
     * Defaults
     * @var array
     */
    protected $defaults = [

    ];

    public function __construct()
    {
        $this->defaults = [
            'limit' => env("LIMIT_ITEM_PER_PAGE", 20),
        ];
    }

    /**
     * Parse GET parameters into resource options
     * @return array
     */
    public function parseRequest($mapping = [])
    {
        $request = request();
        $data = $request->all();
        $this->defaults = array_merge([
            'where' => [],
            'page' => 1,
        ], $this->defaults);
        $page = (int) $request->get('page', $this->defaults['page']);
        $where = !empty($mapping) ? $this->buildWhereGroups($data, $mapping) : [];
        return [
            'where' => $where,
            'page' => $page,
            'limit' => config('common.itemPerPage')
        ];
    }

    /**
     * Parse GET parameters into resource options
     * @return array
     */
    public function parseResourceOptions($request = null)
    {
        if ($request === null) {
            $request = request();
        }

        $this->defaults = array_merge([
            'sort' => [],
            'limit' => null,
            'page' => null,
            'filters' => [],
            'total' => false,
            'modes' => []
        ], $this->defaults);

        $sort = $this->parseSort($request->get('sort', $this->defaults['sort']));
        $limit = (int) $request->get('limit', $this->defaults['limit']);
        $page = (int) $request->get('page', $this->defaults['page']);
        $total = $request->get('total', $this->defaults['total']);

        $filters = $this->parseFilterGroups($request->get('filters', $this->defaults['filters']));

        if ($page !== null && $limit === null) {
            throw new InvalidArgumentException('Cannot use page option without limit option');
        }

        return [
            'sort' => $sort,
            'limit' => $limit,
            'page' => $page,
            'filters' => $filters,
            'total' => $total,
            'modes' => []
        ];
    }

    /**
     * Parse data using architect
     * @param  mixed $data
     * @param  array  $options
     * @param  string $key
     * @return mixed
     */
    protected function parseData($data, array $options, $key = null)
    {
        // $architect = new Architect();

        // return $architect->parseData($data, $options['modes'], $key);
    }

    /**
     * Page sort
     * @param array $sort
     * @return array
     */
    protected function parseSort(array $sort) {
        $return = [];
        if ($sort && is_array($sort)) {
            foreach ($sort as $column => $item) {
                if (is_string($item) && in_array($item, ['asc', 'desc'])) {
                    $return[] = [$column, $item];
                }
            }
        }

        return $return;
    }


    /**
     * Parse filter group strings into filters
     * Filters are formatted as key:operator(value)
     * Example: name:eq(esben)
     * @param  array  $filter_groups
     * @return array
     */
    protected function parseFilterGroups($filter_groups)
    {
        $return = [];
        if (is_array($filter_groups)) {
            $return = $filter_groups;
        }

        return $return;
    }

    /**
     * Parse filter group strings into filters
     * Filters are formatted as key:operator(value)
     * Example: name:eq(esben)
     * @param  array  $filter_groups
     * @return array
     */
    private function buildWhereGroups($data, $mapping)
    {
        $return = [];
        if (!empty($mapping) && is_array($data) && !empty($data)) {
            $return = $this->buildWhere($data, $mapping);
        } else {
            $return = $data;
        }

        return $return;
    }

    private function buildWhere($data, $mapping)
    {
        $return = [];
        foreach ($mapping as $key => $operator) {
            // echo "$key";die;
            if (array_key_exists($key, $data) && $data[$key] != '') {
                switch ($operator) {
                    case '=':
                        $return[$key] = $data[$key];
                        break; 
                    case 'datetime':
                        $dt = $this->dateTimeBuilder($data[$key]);
                        $return[$key] = $dt !== false ? $dt : '190-01-01';
                        break; 
                    case 'between':
                        $arr = explode('...', $data[$key]);
                        $return[$key] = [$operator, $arr[0], isset($arr[1]) ? $arr[1] : ''];
                        break;
                    default:
                        $return[$key] = [$operator, $data[$key]];
                }
            }
        }
        return $return;
    }

    private function dateTimeBuilder($dateTime) 
    {
        $arr = explode('...', $dateTime);
        $dt1 = '';
        $dt2 = '';
        try {
            if (!empty($arr[0]) && !empty($arr[1])) {
                $dt1 = Carbon::createFromFormat('d/m/Y', $arr[0])->format('Y-m-d 00:00:00');
                $dt2 = Carbon::createFromFormat('d/m/Y', $arr[1])->format('Y-m-d 23:59:59');
            } elseif (!empty($arr[0])) {
                $dt1 = Carbon::createFromFormat('d/m/Y', $arr[0])->format('Y-m-d 00:00:00');
            } else {
                $dt2 = Carbon::createFromFormat('d/m/Y', $arr[1])->format('Y-m-d 23:59:59');
            }
            return ['between', $dt1, $dt2];
        } catch (\Throwable $th) {
            return false;
        }
    }


}
