<?php
/*
 * Copyright (c) 2015, Josef Kufner  <jk@frozen-doe.net>
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
 * Flupdo Builder for any other statement. This allows to use of subselects
 * with custom constructions around them.
 *
 * Examples:
 *
 *     $flupdo->rawQuery(array($q1, 'UNION', $q2));
 *
 *     $flupdo->rawQuery($q1)
 *         ->rawQuery('UNION')
 *         ->rawQuery($q2);
 *     
 *     $flupdo->rawQuery(array('CREATE TEMPORARY TABLE t AS (', $q, ')'));
 *
 * Where `$q`, `$q1`, `$q2` are results of `$flupdo->select()` or something
 * like that.
 *
 */

class RawQueryBuilder extends FlupdoBuilder
{

	/**
	 * @copydoc FlupdoBuilder\$methods
	 */
	protected static $methods = array(
		// Header
		'headerComment'		=> array('replace',	'-- HEADER'),

		// The query
		'rawQuery'		=> array('add',		'RAW_QUERY'),

		// Footer
		'footerComment'		=> array('replace',	'-- FOOTER'),
	);


	/**
	 * @copydoc FlupdoBuilder\compileQuery()
	 */
	protected function compileQuery()
	{
		$this->sqlStart();

		$this->sqlComment('-- HEADER');
		$this->sqlList('RAW_QUERY', self::INDENT | self::NO_SEPARATOR | self::EOL);
		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

