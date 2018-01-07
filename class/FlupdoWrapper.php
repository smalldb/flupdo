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

use PDO;
use PDOStatement;


/**
 * Class FlupdoWrapper
 *
 * This wrapper can be used as both PDO and IFlupdo. Constructor is to be implemented by inherited classes.
 */
abstract class FlupdoWrapper extends PDO implements IFlupdo
{
	use FlupdoTrait;


	public function getPdo(): PDO
	{
		return $this->pdo;
	}


	public function beginTransaction()
	{
		return $this->pdo->beginTransaction();
	}

	public function commit(): bool
	{
		return $this->pdo->commit();
	}

	public function errorCode()
	{
		return $this->pdo->errorCode();
	}

	public function errorInfo(): array
	{
		return $this->pdo->errorInfo();
	}

	public function exec($statement): int
	{
		return $this->pdo->exec($statement);
	}

	public function getAttribute($attribute)
	{
		return $this->pdo->getAttribute($attribute);
	}

	public function inTransaction(): bool
	{
		return $this->pdo->inTransaction();
	}

	public function lastInsertId($name = null): string
	{
		return $this->pdo->lastInsertId($name);
	}

	public function prepare($statement, $driver_options = null): PDOStatement
	{
		return $this->pdo->prepare($statement, $driver_options);
	}

	public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, $arg3 = null, array $ctorargs = [])
	{
		return $this->pdo->query($statement, $mode, $arg3, $ctorargs);
	}

	public function quote($string, $parameter_type = PDO::PARAM_STR): string
	{
		return $this->pdo->quote($string, $parameter_type);
	}

	public function rollBack(): bool
	{
		return $this->pdo->rollBack();
	}

	public function setAttribute($attribute, $value): bool
	{
		return $this->pdo->setAttribute($attribute, $value);
	}


}
