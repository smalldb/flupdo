Flupdo
=======

Flupdo is dumb SQL query builder written in PHP. It is designed to be simple,
deterministic, transparent and easy to use. Flupdo contains no smart logic
which is usually unpredictable when small mistakes occur.

Flupdo supports (or should support) 100% of MySQL syntax. Sqlite and SphinxQL
is also supported, but not well tested. PostgreSQL support is planned,
probably only minor tweaks will be required, so it may be usable already.

Flupdo is built on top of PDO. It does not replace fetch methods nor any other
PDO API, it only adds few builder methods which create query builder object, so
full compatibility with PDO is preserved. When query is executed, ordinary
PDOStatement instance is returned.

Flupdo supports both positional and named placeholders, but they cannot be
mixed. Positional placeholders are useful when their values are passed to
the query builder together with SQL statements (see *Basic usage* section).
Named placeholders are to be used with prepared query, where values are passed
all at once (see *Prepared query* section).

Flupdo is tested using PHPT, the tool used by PHP itself. See `test` directory.

Flupdo is documented using Doxygen -- `make doc` will do the trick.

Flupdo does not provide smart constructions and helpers to build common
queries. Such features are out of Flupdo's scope. Flupdo is intended to be
a basic tool used by such helpers and smart query builders to glue queries
together. So if you want something smarter, build it on top of Flupdo. For
example, Smalldb is such framework.

For more details see the project website: https://smalldb.org/



Basic usage
-----------

All builder methods accept SQL fragment as the first argument and parameters as
any additional arguments. For example:

    $q->where('id = ?', $id);

It will add condition `(id = ?)` to WHERE clause and append $id to parameter
list. So the whole query will look like this:

    SELECT ...
    WHERE ... AND (id = ?)

To execute query you can use query() or exec() method. They have same semantics
as PDO::query() and PDO::exec(), but they do not accept any arguments since
the query is already contained in query builder. For example, to execute the
query above use this:

    $pdostmt = $q->query();

And then result can be fetched as usual:

    print_r($pdostmt->fetchAll(PDO::FETCH_ASSOC));


Prepared query
--------------

When query should be prepared for multiple use, it is better to use named
placeholders:

    $pdostmt = $q->insert()->into('users')
            ->set('name = :name')
            ->set('password = :password')
            ->prepare();

And then it can be easily executed over and over:

    $flupdo->beginTransaction();
    foreach ($users as $u) {
        $pdostmt->execute(array('name' => $u['name'], 'password' => $u['password']));
    }
    $flupdo->commit();


Subquery
--------

A short subquery may be passed verbatim to any builder method:

    $q->select('(SELECT MAX(`id`) + 1 FROM users) AS `next_id`');

More complex queries should be built by Flupdo and passed to builder method
using array as the first argument:

    $q2 = $flupdo->select('MAX(`id`) + 1')->from('users');
    $q->select(array($q2, 'AS `next_id`'));

Both of these examples will produce the same SELECT statement.

*Warning:* Positional placeholders must not be used before subquery:

    $q->where(array($subquery, ' > ?'), $n); // Good
    $q->where(array('n + ? IN ', $subquery), $c); // Bad


Builder methods
---------------

Builder methods are of the same name as SQL keywords converted to small camelCase:

  * select(), where(), from(), join(), leftJoin(), innerJoin(), orderBy(), groupBy(), having(), ...
  * distinct(), highPriority(), sqlNoCache(), ...

There are also commenting methods headerComment() and footerComment() available
in all query types. They add simple comment before/after the SQL query. These
may be useful to mark position of the query in the code, so the query is easy to
find from slow query log:

    $q->footerComment(__FILE__.':'.__LINE__);


Complex Joins
-------------

Note that joins are considered standalone clauses, not part of FROM clause.
This mean that complex joins are not possible, but simple chain of many joins
is easy. To build complex FROM clause, specify it verbatim in single from()
call:

    $q->from('t1, (t2, t3), t4');

This also means, that this code:

    $q->from('t1');
    $q->join('t2 ...');
    $q->from('t3');

... will produce this SQL query:

    SELECT ...
    FROM t1, t3
    JOIN t2 ...

This behavior is intentional, since it simplifies building of complex filtering
queries.


Internal Logic
--------------

Flupdo's internal logic is very simple, yet powerful. It consists only of few
buffers, where builder method calls are stored. Just before query execution, a
final query is compiled by arranging content of these buffers in correct order
(depending on query), using correct conjunctions and keywords.

The first builder method call (on Flupdo instance) determines type of the query
and returns instance of FlupdoBuilder subclass. It is not possible to change
type of the query later.

Flupdo does not parse nor understands the SQL fragments. It only uses
string-level concatenation. If more complex behavior is needed, feel free to
write additional helper methods or higher-level query builder on top of Flupdo.


Examples
--------

These examples are available as tests in `test` directory.

Initialization:

    $flupdo = new Flupdo($dsn, $username, $password); // Same as PDO.

Simple select:

    $q = $flupdo->select('n AS TheNumber')
            ->select('n + 1')
            ->distinct()
            ->select('n + 2')
            ->headerComment('Simple select')
            ->from('numbers')
            ->where('n > ?', 5)
            ->where('n < ?', 200)
            ->orderBy('n DESC');

Result query exactly as produced by Flupdo (including whitespace):

        -- Simple select
        SELECT DISTINCT n AS TheNumber,
                n + 1,
                n + 2
        FROM numbers
        WHERE (n > ?)
                AND (n < ?)
        ORDER BY n DESC

More complex example with subselect:

    $q = $flupdo->select('n')
            ->from('numbers')
            ->where(array('n >', $flupdo->select('MIN(n) + ?', 2)->from('numbers')))
            ->where('n < ?', 100)
            ->orderBy('n DESC');

Result query exactly as produced by Flupdo (including whitespace):

        SELECT n
        FROM numbers
        WHERE (n > (
                        SELECT MIN(n) + ?
                        FROM numbers
                ))
                AND (n < ?)
        ORDER BY n DESC


Documentation
-------------

See https://smalldb.org/doc/flupdo/master/


License
-------

The most of the code is published under Apache 2.0 license. See [LICENSE](doc/license.md) file for details.


Contribution guidelines
-----------------------

Project's primary repository is hosted at https://git.frozen-doe.net/smalldb/flupdo, 
feel free to submit issues or create merge requests there.

