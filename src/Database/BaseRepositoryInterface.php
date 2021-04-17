<?php
namespace Aha\LumenMicroCore\Database;

interface BaseRepositoryInterface {
	public function find(array $attributes);
	public function findOne(array $attributes);
	public function count(array $attributes);
    public function create(array $data);
    public function update($whereClause, $data);
    public function delete(array $clauses, $physical);

	// old function
	public function get(array $options);
	public function getById($id, array $options);
	public function getRecent(array $options);
	public function getRecentWhere($column, $value, array $options);
	public function getLatest(array $options);
	public function getLatestWhere($column, $value, array $options);
	public function getWhere(array $clauses, array $options);
	public function getWhereIn($column, array $values, array $options);
    public function updateById($id, array $data);
    public function sqlFoundRows();
	public function builder(array $clauses, array $options);
	// get model query
	public function getQuery();
	public function execute($columns = []);
}