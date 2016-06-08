<?php
/*
 * Copyright (c) 2015, Josef Kufner  <josef@kufner.cz>
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
 * Proxy to instantiate Flupdo lazily and to deffer creating database
 * connection. Connection is done on the first method call.
 */
class LazyFlupdoProxy implements IFlupdo
{

	private $flupdo = null;
	private $factory_method = null;
	private $factory_args = null;


	/**
	 * Deffer constructor call until neccessary.
	 */
	public function __construct(/* ... */)
	{
		$this->factory_method = '__construct';
		$this->factory_args = func_get_args();
	}


	/**
	 * Deffer factory call until neccessary.
	 */
	public static function createInstanceFromConfig(/* ... */)
	{
		$proxy = new self();
		$proxy->factory_method = 'createInstanceFromConfig';
		$proxy->factory_args = func_get_args();
		return $proxy;
	}


	/**
	 * Now is the time.
	 */
	private function kickIt()
	{
		if ($this->factory_method == '__construct') {
			$a = $this->factory_args;
			switch (count($this->factory_args)) {
				case  0: return new self();
				case  1: return new self($a[0]);
				case  2: return new self($a[0], $a[1]);
				case  3: return new self($a[0], $a[1], $a[2]);
				default: return new self($a[0], $a[1], $a[2], $a[3]);	// PDO constructor takes only up to 4 args anyway
			}
		} else {
			return call_user_func_array(array(__NAMESPACE__.'\\Flupdo', $this->factory_method), $this->factory_args);
		}
	}


	/**
	 * Forward all calls to wrapped Flupdo.
	 */
	function __call($method, $args)
	{
		if ($this->flupdo === null) {
			$this->flupdo = $this->kickIt();
		}
		return $this->flupdo->__call($method, $args);
	}

}

