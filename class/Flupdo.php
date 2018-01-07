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
 * Extend PDO class with query builder starting methods. These methods are
 * simple factory & proxy to FlupdoBuilder.
 */
class Flupdo extends \PDO implements IFlupdo
{
	use FlupdoTrait;

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

		$n->flupdo_log_explain = $log_explain;
		$n->flupdo_log_query   = $log_query;

		return $n;
	}

}

