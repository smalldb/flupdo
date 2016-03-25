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

namespace Flupdo\Flupdo;

/**
 * Extend PDO class with query builder starting methods. These methods are
 * simple factory & proxy to FlupdoBuilder.
 */
class Flupdo extends \PDO implements IFlupdo
{

	/**
	 * Log all queries as they are executed
	 */
	private $log_query;

	/**
	 * Explain each query to log.
	 */
	private $log_explain;


	/**
	 * Sphinx does not like parenthesis in WHERE
	 */
	private $no_parenthesis_in_conditions = false;


	/**
	 * Returns fresh instance of Flupdo query builder.
	 */
	function __call($method, $args)
	{
		$class = __NAMESPACE__.'\\'.ucfirst($method).'Builder';
		if (!class_exists($class)) {
			throw new \BadMethodCallException('Undefined method "'.$method.'".');
		}
		$builder = new $class($this, $this->log_query, $this->log_explain, $this->no_parenthesis_in_conditions);
		if (!empty($args)) {
			$builder->__call($method, $args);
		}
		return $builder;
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


	/**
	 * Creates instance of this class using configuration specified in array.
	 *
	 * $config is array containing these keys:
	 *
	 *   - dsn
	 *   - username
	 *   - password
	 *
	 * Or:
	 *
	 *   - driver
	 *   - database
	 *   - host
	 *   - username
	 *   - password
	 *
	 * See [PDO](http://www.php.net/manual/en/class.pdo.php) documentation for details.
	 */
	public static function createInstanceFromConfig($config)
	{
		$driver      = isset($config['driver'])      ? $config['driver']      : null;
		$host        = isset($config['host'])        ? $config['host']        : null;
		$port        = isset($config['port'])        ? $config['port']        : null;
		$database    = isset($config['database'])    ? $config['database']    : null;
		$username    = isset($config['username'])    ? $config['username']    : null;
		$password    = isset($config['password'])    ? $config['password']    : null;
		$log_query   = isset($config['log_query'])   ? $config['log_query']   : false;
		$log_explain = isset($config['log_explain']) ? $config['log_explain'] : false;

		if (isset($config['dsn'])) {
			$n = new self($config['dsn'], $username, $password, array(
					self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
				));
		} else if ($driver == 'mysql') {
			$n = new self("mysql:dbname=$database;host=$host;".($port !== null ? "port=$port;":"")."charset=UTF8",
				$username, $password,
				array(
					self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
				));
			$n->exec("SET NAMES 'UTF8'");
			try {
				$n->exec("SET time_zone = '".date_default_timezone_get()."'");
			}
			catch (\Exception $ex) {
				error_log('Failed to sync timezone in MySQL with PHP: '.$ex->getMessage());
			}
		} else if ($driver == 'sphinx') {
			$n = new self("mysql:dbname=$database;host=$host;port=$port;charset=UTF8",
				$username, $password,
				array(
					self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
				));
			$n->exec("SET NAMES 'UTF8'");
			$n->no_parenthesis_in_conditions = true;
		} else if ($driver == 'sqlite') {
			$n = new self("sqlite:$database",
				null, null, array(
					self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
				));
		} else if ($host !== null) {
			$n = new self("$driver:dbname=$database;host=$host;charset=UTF8",
				$username, $password, array(
					self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
				));
		} else {
			$n = new self("$driver:dbname=$database;charset=UTF8",
				$username, $password, array(
					self::ATTR_ERRMODE => self::ERRMODE_EXCEPTION,
				));
		}

		if ($n === null) {
			// This should not happen
			throw new \Exception('Not implemented.');
		}

		$n->log_explain = $log_explain;
		$n->log_query   = $log_query;

		return $n;
	}

}

