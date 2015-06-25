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
 * Flupdo Builder for INSERT statement
 *
 * -- http://dev.mysql.com/doc/refman/5.5/en/insert.html
 *
 *     INSERT [LOW_PRIORITY | DELAYED | HIGH_PRIORITY] [IGNORE]
 *      [INTO] tbl_name [(col_name,...)]
 *      {VALUES | VALUE} ({expr | DEFAULT},...),(...),...
 *      [ ON DUPLICATE KEY UPDATE
 *        col_name=expr
 *          [, col_name=expr] ... ]
 *
 * -- OR --
 *
 *     INSERT [LOW_PRIORITY | DELAYED | HIGH_PRIORITY] [IGNORE]
 *      [INTO] tbl_name
 *      SET col_name={expr | DEFAULT}, ...
 *      [ ON DUPLICATE KEY UPDATE
 *        col_name=expr
 *         [, col_name=expr] ... ]
 *
 * -- OR --
 *
 *     INSERT [LOW_PRIORITY | HIGH_PRIORITY] [IGNORE]
 *      [INTO] tbl_name [(col_name,...)]
 *      SELECT ...
 *      [ ON DUPLICATE KEY UPDATE
 *        col_name=expr
 *          [, col_name=expr] ... ]
 */

class InsertBuilder extends FlupdoBuilder
{
	/**
	 * @copydoc FlupdoBuilder\$methods
	 */
	protected static $methods = array(
		// Header
		'headerComment'		=> array('replace',	'-- HEADER'),
		'insert'		=> array('add',		'INSERT'),
		'into'			=> array('replace',	'INTO'),

		// Flags
		'lowPriority'		=> array('setFlag',	'PRIORITY',		'LOW_PRIORITY'),
		'delayed'		=> array('setFlag',	'PRIORITY',		'DELAYED'),
		'highPriority'		=> array('setFlag',	'PRIORITY',		'HIGH_PRIORITY'),
		'ignore'		=> array('setFlag',	'IGNORE',		'IGNORE'),

		// Conditions & Values
		'values'		=> array('add',		'VALUES'),
		'set'			=> array('add',		'SET'),

		// INSERT ... SELECT
		'select'		=> array('add',		'SELECT'),

		// Select flags
		'all'			=> array('setFlag',	'DISTINCT',		'ALL'),
		'distinct'		=> array('setFlag',	'DISTINCT',		'DISTINCT'),
		'distinctRow'		=> array('setFlag',	'DISTINCT',		'DISTINCTROW'),
		'straightJoin'		=> array('setFlag',	'STRAIGHT_JOIN',	'STRAIGHT_JOIN'),

		// Select From and joins
		'from'			=> array('replace',	'FROM'),
		'join'			=> array('addJoin',	'JOIN',			'JOIN'),
		'innerJoin'		=> array('addJoin',	'JOIN',			'INNER JOIN'),
		'crossJoin'		=> array('addJoin',	'JOIN',			'CROSS JOIN'),
		'straightJoin'		=> array('addJoin',	'JOIN',			'STRAIGHT_JOIN'),
		'leftJoin'		=> array('addJoin',	'JOIN',			'LEFT JOIN'),
		'rightJoin'		=> array('addJoin',	'JOIN',			'RIGHT JOIN'),
		'leftOuterJoin'		=> array('addJoin',	'JOIN',			'LEFT OUTER JOIN'),
		'rightOuterJoin'	=> array('addJoin',	'JOIN',			'RIGHT OUTER JOIN'),
		'naturalLeftJoin'	=> array('addJoin',	'JOIN',			'NATURAL LEFT JOIN'),
		'naturalRightJoin'	=> array('addJoin',	'JOIN',			'NATURAL RIGHT JOIN'),
		'naturalLeftOuterJoin'	=> array('addJoin',	'JOIN',			'NATURAL LEFT OUTER JOIN'),
		'naturalRightOuterJoin'	=> array('addJoin',	'JOIN',			'NATURAL RIGHT OUTER JOIN'),

		// Select Conditions
		'where'			=> array('add',		'WHERE'),
		'groupBy'		=> array('add',		'GROUP BY'),
		'having'		=> array('add',		'HAVING'),
		'orderBy'		=> array('add',		'ORDER BY'),
		'limit'			=> array('replace',	'LIMIT'),
		'offset'		=> array('replace',	'OFFSET'),

		// Update on duplicate
		'onDuplicateKeyUpdate'	=> array('add',		'ON DUPLICATE KEY UPDATE'),

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
		$this->sqlStatementFlags('INSERT', array(
				'PRIORITY',
				'IGNORE'
			), self::INDENT | self::LABEL);
		$this->sqlList('INTO', self::LABEL | self::EOL);
		$this->sqlList('INSERT', self::INDENT | self::BRACKETS | self::EOL);

		if (isset($this->buffers['SELECT'])) {
			// INSERT ... SELECT
			$this->sqlStatementFlags('SELECT', array(
					'DISTINCT',
					'HIGH_PRIORITY',
					'STRAIGHT_JOIN',
				), self::INDENT | self::LABEL);
			if (isset($this->buffers['SELECT_FIRST'])) {
				$this->sqlList('SELECT_FIRST', self::COMMA | self::EOL);
				$this->sqlList('SELECT', self::SUB_INDENT | self::EOL);
			} else {
				$this->sqlList('SELECT', self::EOL);
			}
			$this->sqlList('FROM', self::INDENT | self::LABEL | self::EOL);
			$this->sqlJoins('JOIN');
			$this->sqlConditions('WHERE');
			$this->sqlList('GROUP BY', self::INDENT | self::LABEL | self::EOL);
			$this->sqlConditions('HAVING');
			$this->sqlList('ORDER BY', self::INDENT | self::LABEL | self::EOL);
			if (isset($this->buffers['LIMIT'])) {
				$this->sqlList('LIMIT', self::INDENT | self::LABEL | self::EOL);
				$this->sqlList('OFFSET', self::INDENT | self::LABEL | self::EOL);
			}
		} else if (isset($this->buffers['VALUES'])) {
			// INSERT ... VALUES
			$this->sqlValuesList('VALUES');
		} else {
			// INSERT ... SET
			$this->sqlList('SET', self::INDENT | self::LABEL | self::EOL);
		}

		$this->sqlList('ON DUPLICATE KEY UPDATE', self::INDENT | self::LABEL | self::EOL);

		$this->sqlComment('-- FOOTER');

		return $this->sqlFinish();
	}

}

