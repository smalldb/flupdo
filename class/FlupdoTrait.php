<?php
/*
 * Copyright (c) 2018, Josef Kufner  <jk@frozen-doe.net>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */


namespace Smalldb\Flupdo;


trait FlupdoTrait
{

	/**
	 * Log all queries as they are executed
	 */
	protected $flupdo_log_query;

	/**
	 * Explain each query to log.
	 */
	protected $flupdo_log_explain;

	/**
	 * @var \PDO
	 */
	protected $pdo = null;


	/**
	 * Sphinx does not like parenthesis in WHERE
	 */
	private $no_parenthesis_in_conditions = false;


	function select(...$args): SelectBuilder
	{
		$b = (new SelectBuilder($this->pdo ?? $this, $this->flupdo_log_query, $this->flupdo_log_explain, $this->no_parenthesis_in_conditions));
		if (!empty($args)) {
			$b->select(...$args);
		}
		return $b;
	}

	function insert(...$args): InsertBuilder
	{
		$b = (new InsertBuilder($this->pdo ?? $this, $this->flupdo_log_query, $this->flupdo_log_explain, $this->no_parenthesis_in_conditions));
		if (!empty($args)) {
			$b->insert(...$args);
		}
		return $b;
	}

	function update(...$args): UpdateBuilder
	{
		$b = (new UpdateBuilder($this->pdo ?? $this, $this->flupdo_log_query, $this->flupdo_log_explain, $this->no_parenthesis_in_conditions));
		if (!empty($args)) {
			$b->update(...$args);
		}
		return $b;
	}

	function delete(...$args): DeleteBuilder
	{
		$b = (new DeleteBuilder($this->pdo ?? $this, $this->flupdo_log_query, $this->flupdo_log_explain, $this->no_parenthesis_in_conditions));
		if (!empty($args)) {
			$b->delete(...$args);
		}
		return $b;
	}

	function replace(...$args): ReplaceBuilder
	{
		$b = (new ReplaceBuilder($this->pdo ?? $this, $this->flupdo_log_query, $this->flupdo_log_explain, $this->no_parenthesis_in_conditions));
		if (!empty($args)) {
			$b->replace(...$args);
		}
		return $b;
	}


	/**
	 * Quote identifier for use in SQL query (i.e. table name, column name), preserve dot.
	 *
	 * This is a copy of FlupdoBuilder::quoteIdent().
	 */
	public function quoteIdent($ident)
	{
		if (is_array($ident)) {
			return array_map(function($ident) { return '`' . str_replace(array('`', '.'), array('``', '`.`'), $ident) . '`'; }, $ident);
		} else {
			return '`' . str_replace(array('`', '.'), array('``', '`.`'), $ident) . '`';
		}
	}


	/**
	 * Returns object marking raw SQL statement.
	 */
	public function rawSql($sql)
	{
		return new FlupdoRawSql($sql);
	}

}
