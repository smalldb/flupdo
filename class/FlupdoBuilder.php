<?php
/*
 * Copyright (c) 2013, Josef Kufner  <jk@frozen-doe.net>
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

/**
 * Query builder base class, which provides some magic to build queries.
 */
abstract class FlupdoBuilder
{
	/**
	 * PDO driver used to execute query and escape strings.
	 */
	public $pdo;

	/**
	 * Log all queries as they are executed
	 */
	protected $log_query;

	/**
	 * Explain each query to log.
	 */
	protected $log_explain;

	/**
	 * Sphinx does not like parenthesis in WHERE
	 */
	protected $no_parenthesis_in_conditions;

	/**
	 * Is it possible to explain this query?
	 */
	protected $can_explain = false;

	/**
	 * Indentation string.
	 */
	protected $indent = "\t";

	/**
	 * Second level indentation string.
	 */
	protected $sub_indent = "\t\t";

	/**
	 * Built query
	 */
	protected $query_sql = null;

	/**
	 * Parameters for prepared statement (to be bound before query is executed).
	 */
	protected $query_params = null;

	/**
	 * List of clauses used to composed result query. Shared constant data.
	 */
	protected static $clauses = array();

	/**
	 * List of methods used to fill the $buffers. Shared constant data.
	 */
	protected static $methods = array();

	/**
	 * Buffers containing SQL fragments.
	 */
	protected $buffers = array();


	/**
	 * @name Flags for helper methods.
	 * 
	 * Used by sqlList() and sqlStatementFlags().
	 * @{
	 */
	const INDENT		= 0x01;	///< List items should be indented.
	const LABEL		= 0x02;	///< SQL fragment has a label.
	const BRACKETS		= 0x04;	///< There are brackets around each item in the list.
	const NO_SEPARATOR	= 0x08;	///< No separator between list items
	const SUB_INDENT	= 0x20;	///< Indent more the first line.
	const COMMA		= 0x40;	///< Add comma after the SQL fragment.
	const EOL		= 0x80;	///< Add EOL after the SQL fragment.
	const ALL_DECORATIONS	= 0xFF;	///< Make it fancy!
	/** @} */


	/**
	 * Constructor.
	 */
	public function __construct($pdo, $log_query = false, $log_explain = false, $no_parenthesis_in_conditions = false)
	{
		$this->pdo = $pdo;
		$this->log_query = $log_query;
		$this->log_explain = $log_explain;
		$this->no_parenthesis_in_conditions = $no_parenthesis_in_conditions;
	}


	/**
	 * Call buffer-specific method to process arguments.
	 *
	 * If the first argument is null, corresponding buffer will be deleted.
	 */
	public function __call($method, $args)
	{
		//echo __CLASS__, "::", $method, " (", join(', ', array_map(function($x) { return var_export($x, true);}, $args)), ")\n";

		if (!isset(static::$methods[$method])) {
			throw new \BadMethodCallException('Undefined method "'.$method.'".');
		}

		if ($this->query_sql !== null) {
			throw new \RuntimeException('Query is already compiled.');
		}

		$method_cfg = static::$methods[$method];
		list($action, $buffer_id) = $method_cfg;
		$label = $method_cfg[2] ?? null;        // label is optional

		if (count($args) == 1 && $args[0] === null) {
			unset($this->buffers[$buffer_id]);
		} else {
			$this->$action($args, $buffer_id, $label);
		}

		$this->query_sql = null;

		return $this;
	}


	/**
	 * Quote `identifier`, preserve dot.
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
	 * Add SQL fragment to buffer.
	 */
	protected function add($args, $buffer_id)
	{
		$this->buffers[$buffer_id][] = $args;
	}


	/**
	 * Replace buffer content with SQL fragment.
	 */
	protected function replace($args, $buffer_id)
	{
		$this->buffers[$buffer_id] = array($args);
	}


	/**
	 * Set flag. Replace buffer with new label of this flag.
	 */
	protected function setFlag($args, $buffer_id, $label)
	{
		$this->buffers[$buffer_id] = $label;
	}


	/**
	 * Add join statement to buffer.
	 */
	protected function addJoin($args, $buffer_id, $label)
	{
		array_push($args, $label);
		$this->buffers[$buffer_id][] = $args;
	}


	/**
	 * Process all buffers and build SQL query. Side product is array of
	 * parameters (stored in $this->args) to bind with query.
	 *
	 * This function is called by FlupdoBuilder, do not call it directly.
	 *
	 * Example:
	 *
	 *     $this->sqlStart();
	 *     // ...
	 *     return $this->sqlFinish();
	 *
	 */
	abstract protected function compileQuery();


	/**
	 * Call compile function in a safe way.
	 */
	public final function compile()
	{
		try {
			$q = $this->compileQuery();
			if ($this->log_query) {
				error_log(sprintf("SQL Query (compile):\n%s", $this->query_sql));
			}
			return $q;
		}
		catch (\Exception $ex) {
			// Make sure unfinished query will not make it to the output.
			ob_end_clean();
			throw $ex;
		}
	}


	/**
	 * "Uncompile" the query. This will drop compiled query and allow
	 * additional modifications. Once query is compiled additional
	 * modifications are not allowed to detect programming errors, but
	 * sometimes it is useful to execute the query and then perform
	 * additional modifications before second execution.
	 */
	public function uncompile()
	{
		$this->query_sql = null;
	}


	/**
	 * Fluently dump query to error log.
	 */
	public function debugDump()
	{
		error_log("Query:\n".$this);
		return $this;
	}


	/**
	 * Get compiled SQL query, use only for debugging.
	 */
	public function getSqlQuery()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		return $this->query_sql;
	}


	/**
	 * Get parameters for compiled SQL query, use only for debugging.
	 */
	public function getSqlParams()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		return $this->query_params;
	}


	/**
	 * Quotes a string for use in a query.
	 *
	 * Proxy to PDO::quote().
	 */
	public function quote($value)
	{
		// PDO::quote() does not work as it should ...
		if ($value instanceof FlupdoRawSql) {
			return $value;
		} else if (is_bool($value)) {
			return $value ? 'TRUE' : 'FALSE';
		} else if (is_null($value)) {
			return 'NULL';
		} else if (is_int($value)) {
			return (string) $value;
		} else if (is_float($value)) {
			return sprintf('%F', $value);
		} else {
			// ignore locales when converting to string
			return $this->pdo->quote(strval($value), \PDO::PARAM_STR);
		}
	}


	/**
	 * Returns object marking raw SQL statement.
	 */
	public function rawSql($sql)
	{
		return new FlupdoRawSql($sql);
	}


	/**
	 * Builds and executes an SQL statement, returning the number of affected rows.
	 *
	 * Proxy to PDO::exec().
	 */
	public function exec()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}
		if ($this->log_query) {
			error_log(sprintf("SQL Query (exec):\n%s", $this->query_sql));
		}
		if (empty($this->query_params)) {
			$t = microtime(true);
			try {
				$r = $this->pdo->exec($this->query_sql);
				if ($this->log_query) {
					error_log(sprintf("SQL Query time: %F ms (exec).", (microtime(true) - $t) * 1000));
				}
			}
			catch (\PDOException $ex) {
				throw new FlupdoException($ex->getMessage(), $ex->getCode(), $ex, $this->query_sql, $this->query_params);
			}
			if ($r === FALSE) {
				throw new FlupdoException(vsprintf("SQLSTATE[%s]: Error %s: %s", $this->pdo->errorInfo()),
					$this->pdo->errorCode(), null, $this->query_sql, $this->query_params);
			}
			return $r;
		} else {
			$stmt = $this->query();
			return $stmt->rowCount();
		}
	}


	/**
	 * Builds, binds and executes an SQL statement, returning a result set
	 * as a PDOStatement object.
	 *
	 * Proxy to PDOStatement::prepare() & PDOStatement::bindValue() & PDOStatement::query().
	 * But if there is nothing to bind, PDO::query() is called instead.
	 */
	public function query()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		if (empty($this->query_params)) {
			$t = microtime(true);
			try {
				$result = $this->pdo->query($this->query_sql);
				if ($this->log_query) {
					error_log(sprintf("SQL Query time: %F ms (query), %d rows.", (microtime(true) - $t) * 1000, $result->rowCount()));
				}
			}
			catch (\PDOException $ex) {
				throw new FlupdoException($ex->getMessage(), $ex->getCode(), $ex, $this->query_sql, $this->query_params);
			}
			if (!$result) {
				throw new FlupdoException(vsprintf("SQLSTATE[%s]: Error %s: %s", $this->pdo->errorInfo()),
					$this->pdo->errorCode(), null, $this->query_sql, $this->query_params);
			}
			return $result;
		} else {
			$t = microtime(true);
			try {
				$stmt = $this->prepare();
			}
			catch (\PDOException $ex) {
				throw new FlupdoException($ex->getMessage(), $ex->getCode(), $ex, $this->query_sql, $this->query_params);
			}
			if ($stmt === FALSE) {
				throw new FlupdoException(vsprintf("SQLSTATE[%s]: Error %s: %s", $this->pdo->errorInfo()),
					$this->pdo->errorCode(), null, $this->query_sql, $this->query_params);
			}

			$i = 1;
			foreach ($this->query_params as $param) {
				if (is_bool($param)) {
					$stmt->bindValue($i, $param, \PDO::PARAM_BOOL);
				} else if (is_null($param)) {
					$stmt->bindValue($i, $param, \PDO::PARAM_NULL);
				} else if (is_int($param)) {
					$stmt->bindValue($i, $param, \PDO::PARAM_INT);
				} else {
					// ignore locales when converting to string
					$stmt->bindValue($i, strval($param), \PDO::PARAM_STR);
				}
				$i++;
			}

			try {
				$success = $stmt->execute();
			}
			catch (\PDOException $ex) {
				throw new FlupdoException($ex->getMessage(), $ex->getCode(), $ex, $this->query_sql, $this->query_params);
			}
			if ($success === FALSE) {
				throw new FlupdoException(vsprintf("SQLSTATE[%s]: Error %s: %s", $stmt->errorInfo()),
					$stmt->errorCode(), null, $this->query_sql, $this->query_params);
			}
			if ($this->log_query) {
				error_log(sprintf("SQL Query time: %F ms (prepare + execute), %d rows.", (microtime(true) - $t) * 1000, $stmt->rowCount()));
			}
			if ($this->can_explain && $this->log_explain) {
				$this->explain();
			}
			return $stmt;
		}
	}


	/**
	 * Explain the query and dump result to log.
	 */
	protected function explain()
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		$sql = "\n    EXPLAIN\n" . $this->interpolateQuery($this->query_sql, $this->query_params);

		$explain = $this->pdo->query($sql);

		$data = $explain->fetchAll(\PDO::FETCH_ASSOC);
		$col_len = array();
		$t = "\n";

		// Calculate column widths
		foreach ($data as $row) {
			foreach ($row as $k => $v) {
				$col_len[$k] = isset($col_len[$k]) ? max($col_len[$k], strlen($v)) : strlen($v);
			}
		}
		foreach ($col_len as $k => $len) {
			$col_len[$k] = max($len, strlen($k));
		}

		// Horizontal Line
		foreach ($col_len as $k => $len) {
			$t .= '+'.str_repeat('-', $len + 2);
		}
		$t .= "+\n";

		// Table header
		foreach ($col_len as $k => $len) {
			$t .= sprintf('| %-'.$len.'s ', $k);
		}
		$t .= "+\n";

		// Horizontal Line
		foreach ($col_len as $k => $len) {
			$t .= '+'.str_repeat('-', $len + 2);
		}
		$t .= "+\n";

		// Table body
		foreach ($data as $row) {
			foreach ($row as $k => $v) {
				$t .= sprintf('| %-'.$col_len[$k].'s ', $v);
			}
			$t .= "|\n";
		}

		// Horizontal Line
		foreach ($col_len as $k => $len) {
			$t .= '+'.str_repeat('-', $len + 2);
		}
		$t .= "+\n";

		// Log the table
		if (function_exists('debug_msg')) {
			debug_msg('Explain last query:%s', $t);
		}

		// Make sure EXPLAIN query is destroyed.
		$this->uncompile();
	}


	/**
	 * Replaces any parameter placeholders in a query with the value of that
	 * parameter. Useful for debugging. Assumes anonymous parameters from 
	 * $params are are in the same order as specified in $query
	 *
	 * @see http://stackoverflow.com/questions/210564/getting-raw-sql-query-string-from-pdo-prepared-statements
	 *
	 * Added `$pdo->quote()` to properly escape all values.
	 *
	 * @param string $query The sql query with parameter placeholders
	 * @param array $params The array of substitution parameters
	 * @return string The interpolated query
	 */
	private function interpolateQuery($query, $params)
	{
		$keys = array();

		# build a regular expression for each parameter
		foreach ($params as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
			} else {
				$keys[] = '/[?]/';
			}
		}

		$pdo = $this->pdo;
		$query = preg_replace($keys, array_map(function($a) use ($pdo) { return $pdo->quote($a); }, $params), $query, 1, $count);

		#trigger_error('replaced '.$count.' keys');

		return $query;
	}


	/**
	 * Builds and prepares a statement for execution, returns a statement object.
	 *
	 * Proxy to PDO::prepare().
	 */
	public function prepare($driver_options = array())
	{
		if ($this->query_sql === null) {
			$this->compile();
		}

		return $this->pdo->prepare($this->query_sql, $driver_options);
	}


	/**
	 * Proxy to PDO::lastInsertId().
	 */
	public function lastInsertId()
	{
		return $this->pdo->lastInsertId();
	}


	/**
	 * Fetch one row from result and close cursor.
	 *
	 * Returns what PDOStatement::fetch() would return.
	 */
	public function fetchSingleRow()
	{
		$result = $this->query();
		$row = $result->fetch(\PDO::FETCH_ASSOC);
		$result->closeCursor();
		return $row;
	}


	/**
	 * Fetch one row from result and close cursor.
	 *
	 * Returns what PDOStatement::fetchColumn(0) would return.
	 */
	public function fetchSingleValue()
	{
		$result = $this->query();
		$value = $result->fetchColumn(0);
		$result->closeCursor();
		return $value;
	}


	/**
	 * Fetch everything into array
	 *
	 * Returns what PDOStatement::fetchAll(PDO::FETCH_ASSOC) would return.
	 *
	 * If $key_column is set, the specified column will be used to index
	 * array items.
	 *
	 * @note Keep in mind that this is not effective for larger data sets.
	 * 	It is better to perform the query and iterate over result
	 * 	instead of loading it all at once. However, it may not be
	 * 	possible in all cases.
	 */
	public function fetchAll($key_column = null)
	{
		$result = $this->query();
		if ($key_column === null) {
			$list = $result->fetchAll(\PDO::FETCH_ASSOC);
		} else {
			$list = array();
			while(($item = $result->fetch(\PDO::FETCH_ASSOC))) {
				$list[$item[$key_column]] = $item;
			}
			return $list;
		}
		$result->closeCursor();
		return $list;
	}


	/**
	 * Get SQL query as a string.
	 */
	public function __toString()
	{
		try {
			if ($this->query_sql === null) {
				$this->compile();
			}
		}
		catch (\Exception $ex) {
			// __toString() cannot throw an exception, so we will
			// log it and die, fatal error would be triggered anyway.
			error_log(__METHOD__.': '.$ex);
			die();
		}
		return $this->query_sql;
	}


	/**
	 * Start SQL generating. Uses output buffering to concatenate the query.
	 */
	protected function sqlStart()
	{
		$this->query_params = array();
		ob_start();
	}


	/**
	 * Finish SQL generating. Picks up the query from output buffer.
	 */
	protected function sqlFinish()
	{
		$this->query_sql = ob_get_clean();

		// Flatten parameters before bind
		if (!empty($this->query_params)) {
			$this->query_params = call_user_func_array('array_merge', $this->query_params);
		}
		return $this;
	}


	/**
	 * Add SQL with parameters. Parameters are stored in groups, merge to
	 * one array is done at the end (using single array_merge call).
	 */
	protected function sqlBuffer($buf)
	{
		if (empty($buf)) {
			return;
		}

		$sql = array_shift($buf);

		if (is_array($sql)) {
			$first = true;
			foreach ($sql as $fragment) {
				if ($first) {
					$first = false;
				} else {
					echo ' ';
				}
				if ($fragment instanceof self) {
					$fragment->indent = $this->sub_indent."\t";
					$fragment->compile();
					echo "(\n", $fragment->query_sql, $this->sub_indent, ")";
					$this->query_params[] = $fragment->query_params;
				} else {
					echo $fragment;
				}
			}
		} else if ($sql instanceof self) {
			$sql->indent = $this->sub_indent."\t";
			$sql->compile();
			echo "(\n", $sql->query_sql, $sql->sub_indent, ")";
			$this->query_params[] = $sql->query_params;
		} else {
			echo $sql;
		}

		if (!empty($buf)) {
			$this->query_params[] = $buf;
		}
	}


	/**
	 * Generate raw SQL fragment.
	 */
	protected function sqlRawBuffer($buf)
	{
		if (is_array($buf[0])) {
			echo join("\n", $buf[0]);
		} else {
			echo $buf[0];
		}
	}


	/**
	 * Generate SQL comment.
	 */
	protected function sqlComment($buffer_id)
	{
		if (isset($this->buffers[$buffer_id])) {
			foreach ($this->buffers[$buffer_id] as $buf) {
				echo $this->indent, '-- ', str_replace(array("\r", "\n"), array('', "\n".$this->indent.'-- '), $this->sqlRawBuffer($buf)), "\n";
			}
		}
	}


	/**
	 * Generate flag fragment.
	 */
	protected function sqlFlag($buffer_id)
	{
		if (isset($this->buffers[$buffer_id])) {
			if (isset($this->buffers[$flag_buf])) {
				echo ' ', $this->buffers[$flag_buf];
			}
		}
	}


	/**
	 * Generate SQL fragment made of flags.
	 */
	protected function sqlStatementFlags($buffer_id, $flag_buffer_ids, $decorations)
	{
		$first = false;

		if ($decorations & self::INDENT) {
			echo $this->indent;
			$first = true;
		}

		if ($decorations & self::LABEL) {
			if ($first) {
				$first = false;
			} else {
				echo ' ';
			}
			echo $buffer_id;
			$first = false;
		}

		foreach ($flag_buffer_ids as $flag_buf) {
			if (isset($this->buffers[$flag_buf])) {
				if ($first) {
					$first = false;
				} else {
					echo ' ';
				}
				echo $this->buffers[$flag_buf];
			}
		}

		if ($decorations & self::COMMA) {
			echo ",";
		}
		if ($decorations & self::EOL) {
			echo "\n";
		}
	}


	/**
	 * Generate SQL fragment made of list.
	 */
	protected function sqlList($buffer_id, $decorations)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			if ($decorations & (self::INDENT | self::SUB_INDENT)) {
				if ($decorations & (self::BRACKETS | self::SUB_INDENT)) {
					echo $this->sub_indent;
				} else {
					echo $this->indent;
				}
			} else if ($decorations & (self::LABEL | self::BRACKETS)) {
				echo ' ';
			}
			if ($decorations & self::LABEL) {
				echo $buffer_id;
			}
			if ($decorations & self::BRACKETS) {
				echo '(';
			}
			foreach ($this->buffers[$buffer_id] as $buf) {
				if ($decorations & self::NO_SEPARATOR) {
					if ($first) {
						$first = false;
					} else {
						echo "\n", $this->sub_indent;
					}
				} else if ($decorations & self::BRACKETS) {
					if ($first) {
						$first = false;
					} else {
						echo ", ";
					}
				} else {
					if ($first) {
						$first = false;
						echo ' ';
					} else {
						echo ",\n", $this->sub_indent;
					}
				}
				$this->sqlBuffer($buf);
			}
			if ($decorations & self::BRACKETS) {
				echo ')';
			}
			if ($decorations & self::COMMA) {
				echo ",";
			}
			if ($decorations & self::EOL) {
				echo "\n";
			}
		}
	}


	/**
	 * Generate SQL fragment made of list values.
	 */
	protected function sqlValuesList($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id, "\n";
			foreach ($this->buffers[$buffer_id] as $buf) {
				if (count($buf) == 1) {
					// One argument -- insert values from array
					foreach ($buf[0] as $row) {
						if ($first) {
							$first = false;
							echo $this->sub_indent, '(';
						} else {
							echo "),\n", $this->sub_indent, '(';
						}

						echo join(', ', array_map(array($this, 'quote'), $row)); // FIXME: bind values
					}
				} else {
					throw new \Exception('Not implemented yet.');
				}
			}
			echo ')';
			echo "\n";
		}
	}


	/**
	 * Generate SQL fragment made of joins.
	 */
	protected function sqlJoins($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			foreach ($this->buffers[$buffer_id] as $buf) {
				$join = array_pop($buf);
				echo $this->indent, $join, " ", $this->sqlBuffer($buf), "\n";
			}
		}
	}


	/**
	 * Generate SQL fragment made of conditions in AND statement.
	 */
	protected function sqlConditions($buffer_id)
	{
		$first = true;

		if (isset($this->buffers[$buffer_id])) {
			echo $this->indent, $buffer_id;
			if ($this->no_parenthesis_in_conditions) {
				foreach ($this->buffers[$buffer_id] as $buf) {
					if ($first) {
						$first = false;
						echo ' ';
					} else {
						echo $this->sub_indent, "AND ";
					}
					echo $this->sqlBuffer($buf), "\n";
				}
			} else {
				foreach ($this->buffers[$buffer_id] as $buf) {
					if ($first) {
						$first = false;
						echo ' (';
					} else {
						echo $this->sub_indent, "AND (";
					}
					echo $this->sqlBuffer($buf), ")\n";
				}
			}
		}
	}


	/**
	 * Generate documentation for methods defined in $methods array.
	 */
	public static function generateDoxygenDocumentation()
	{
		foreach (static::$methods as $methodName => $m) {
			@ list($realMethod, $buffer, $label) = $m;
			echo "/**\n";
			echo "@memberof ", get_called_class(), "\n";
			if ($realMethod == 'setFlag') {
				echo "@fn $methodName()\n";
			} else {
				echo "@fn $methodName(\$sql_statement, ...)\n";
				echo "@param \$sql_statement SQL statement (a fragment of SQL query) with placeholders.\n";
				echo "@param ... Values of placeholders (when positional placeholders are used).\n";
			}
			echo "@public\n";
			if ($realMethod == 'setFlag') {
				echo "@brief Sets content of buffer `$buffer` to `$label`.\n";
			} else if (substr($realMethod, 0, 3) == 'add') {
				if ($label) {
					echo "@brief Appends `\$sql_statement` prefixed with `$label` to buffer `$buffer`.\n";
				} else {
					echo "@brief Appends `\$sql_statement` to buffer `$buffer`.\n";
				}
			} else {
				if ($label) {
					echo "@brief Replaces content of buffer `$buffer` with `\$sql_statement` prefixed with `$label`.\n";
				} else {
					echo "@brief Replaces content of buffer `$buffer` with `\$sql_statement`.\n";
				}
			}
			echo "\n";
			echo "@note This method is generated from FlupdoBuilder::\$methods array. See compileQuery() method for buffer usage.\n";
			echo "*/\n";
			echo "\n";
		}
	}

}

