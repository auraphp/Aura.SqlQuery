# Aura.Sql_Query

Provides query builders for MySQL, Postgres, SQLite, and Microsoft SQL Server.
These builders are independent of any particular database connection library,
although [PDO](http://php.net/PDO) in general is recommended.

## Foreword

### Requirements

This library requires PHP 5.3 or later, and has no userland dependencies.

### Installation

This library is installable and autoloadable via Composer with the following
`require` element in your `composer.json` file:

    "require": {
        "aura/sql-query": "2.*@dev"
    }
    
Alternatively, download or clone this repository, then require or include its
_autoload.php_ file.

### Tests

[![Build Status](https://travis-ci.org/auraphp/Aura.Sql_Query.png?branch=develop-2)](https://travis-ci.org/auraphp/Aura.Sql_Query)

This library has 100% code coverage with [PHPUnit][]. To run the tests at the
command line, go to the _tests_ directory and issue `phpunit`.

[phpunit]: http://phpunit.de/manual/

### PSR Compliance

This library attempts to comply with [PSR-1][], [PSR-2][], and [PSR-4][]. If
you notice compliance oversights, please send a patch via pull request.

[PSR-1]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-1-basic-coding-standard.md
[PSR-2]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-2-coding-style-guide.md
[PSR-4]: https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-4-autoloader.md

### Community

To ask questions, provide feedback, or otherwise communicate with the Aura community, please join our [Google Group](http://groups.google.com/group/auraphp), follow [@auraphp on Twitter](http://twitter.com/auraphp), or chat with us on #auraphp on Freenode.


## Getting Started

First, instantiate a _QueryFactory_ with a database type:

```php
<?php
use Aura\Sql_Query\QueryFactory;

$query_factory = new QueryFactory('sqlite');
?>
```

You can then use the factory to create query objects:

```php
<?php
$select = $query_factory->newSelect();
$insert = $query_factory->newInsert();
$update = $query_factory->newUpdate();
$delete = $query_factory->newDelete();
?>
```

The query objects do not execute queries against a database. When you are done
building the query, you will need to pass it to a database connection of your
choice. In the examples below, we will use [PDO](http://php.net/pdo) for the
database connection, but any database library that uses named placeholders and
bound values should work just as well (e.g. the [Aura.Sql][] _ExtendedPdo_
class).

[Aura.Sql]: https://github.com/auraphp/Aura.Sql/tree/develop-2

## Identifier Quoting

In most cases, the query objects will quote identifiers for you. For example,
under the common _Select_ object with double-quotes for identifiers:

```php
<?php
$select->cols(['foo', 'bar AS barbar'])
       ->from('table1')
       ->from('table2')
       ->where('table2.zim = 99');

echo $select->__toString();
// SELECT
//     "foo",
//     "bar" AS "barbar"
// FROM
//     "table1",
//     "table2"
// WHERE
//     "table2"."zim" = 99

?>
```

If you discover that a partially-qualified identifier has not been auto-quoted
for you, change it to a fully-qualified identifer (e.g., from `col_name` to
`table_name.col_name`).

## Common Query Objects

Although you must specify a database type when instantiating a _QueryFactory_,
you can tell the factory to return "common" query objects instead of database-
specific ones.  This will make only the common query methods available, which
helps with writing database-portable applications. To do so, pass the constant
`QueryFactory::COMMON` as the second constructor parameter.

```php
<?php
use Aura\Sql_Query\QueryFactory;

// return Common, not SQLite-specific, query objects
$query_factory = new QueryFactory('sqlite', QueryFactory::COMMON);
?>
```

> N.b. You still need to pass a database type so that identifiers can be
> quoted appropriately.

All query objects implement the "Common" methods.

### SELECT

Build a _Select_ query using the following methods. They do not need to
be called in any particular order, and may be called multiple times.

```php
<?php
$select = $query_factory->newSelect();

$select
    ->distinct()                    // SELECT DISTINCT
    ->cols([                        // select these columns
        'id',
        'name AS namecol',
        'COUNT(foo) AS foo_count',
    ])
    ->from('foo AS f')              // FROM these tables
    ->fromSubselect(                // FROM sub-select AS my_sub
        'SELECT ...',
        'my_sub'
    )
    ->join(                         // JOIN ...
        'LEFT',                     // left/inner/natural/etc
        'doom AS d'                 // this table name
        'foo.id = d.foo_id'         // ON these conditions
    )
    ->joinSubSelect(                // JOIN to a sub-select
        'INNER',                    // left/inner/natural/etc
        'SELECT ...',               // the subselect to join on
        'subjoin'                   // AS this name
        'sub.id = foo.id'           // ON these conditions
    )
    ->where('bar > :bar')           // AND WHERE these conditions
    ->where('zim = ?', 'zim_val')   // bind 'zim_val' to the ? placeholder
    ->orWhere('baz < :baz')         // OR WHERE these conditions
    ->groupBy(['dib'])              // GROUP BY these columns
    ->having('foo = :foo')          // AND HAVING these conditions
    ->having('bar > ?', 'bar_val')  // bind 'bar_val' to the ? placeholder
    ->orHaving('baz < :baz')        // OR HAVING these conditions
    ->orderBy(['baz']);             // ORDER BY these columns
    ->limit(10)                     // LIMIT 10
    ->offset(40)                    // OFFSET 40
    ->forUpdate()                   // FOR UPDATE
    ->union()                       // UNION with a followup SELECT
    ->unionAll()                    // UNION ALL with a followup SELECT
    ->bindValue('foo', 'foo_val')   // bind one value to a placeholder
    ->bindValues([                  // bind these values to named placeholders
        'bar' => 'bar_val',
        'baz' => 'baz_val',
    ]);
?>
```

Once you have built the query, pass it to the database connection of your
choice as a string, and send the bound values along with it.

```php
<?php
// a PDO connection
$pdo = new PDO(...);

// prepare the statment
$sth = $pdo->prepare($select->__toString());

// bind the values and execute
$sth->execute($select->getBindValues());

// get the results back as an associative array
$result = $sth->fetch(PDO::FETCH_ASSOC);
?>
```

### INSERT

Build an _Insert_ query using the following methods. They do not need to
be called in any particular order, and may be called multiple times. This
builds a single insert; you cannot do a multiple insert with this object.

```php
<?php
$insert = $query_factory->newInsert();

$insert
    ->into('foo')                   // INTO this table
    ->cols([                        // insert these as "(col) VALUES (:col)"
        'bar',
        'baz',
    ])
    ->set('id', 'NULL')             // insert raw values for this column
    ->bindValue('foo', 'foo_val')   // bind one value to a placeholder
    ->bindValues([                  // bind these values
        'bar' => 'foo',
        'baz' => 'zim',
    ]);
?>
```

The `cols()` method allows you to pass an array of key-value pairs where the
key is the column name and the value is a bind value (not a raw value):

```php
<?php
$insert = $query_factory->newInsert();

$insert->into('foo')            // insert into this table
    ->cols([                    // insert these columns and bind these values
        'foo' => 'foo_value',
        'bar' => 'bar_value',
        'baz' => 'baz_value',
    ]);
?>
```

Once you have built the query, pass it to the database connection of your
choice as a string, and send the bound values along with it.

```php
<?php
// the PDO connection
$pdo = new PDO(...);

// prepare the statement
$sth = $pdo->prepare($insert->__toString())

// execute with bound values
$sth->execute($insert->getBindValues());

// get the last insert ID
$name = $insert->getLastInsertIdName('id');
$id = $pdo->lastInsertId($name);
?>
```

### UPDATE

Build an _UPDATE_ query using the following methods. They do not need to
be called in any particular order, and may be called multiple times.

```php
<?php
$update = $query_factory->newUpdate();

$update
    ->table('foo')                  // update this table
    ->cols([                        // these cols as "SET bar = :bar"
        'bar',
        'baz',
    ])
    ->set('date', 'NOW()')          // set this col to a raw value
    ->where('zim = :zim')           // AND WHERE these conditions
    ->where('gir = ?', 'doom')      // bind this value to the condition
    ->orWhere('gir = :gir')         // OR WHERE these conditions
    ->bindValue('bar', 'bar_val')   // bind one value to a placeholder
    ->bindValues([                  // bind these values to the query
        'baz' => 99,
        'zim' => 'dib',
        'gir' => 'doom',
    ]);
?>
```

The `cols()` method allows you to pass an array of key-value pairs where the
key is the column name and the value is a bind value (not a raw value):

```php
<?php
$update = $query_factory->newUpdate();

$update->table('foo')           // update this table
    ->cols([                    // update these columns and bind these values
        'foo' => 'foo_value',
        'bar' => 'bar_value',
        'baz' => 'baz_value',
    ]);
?>
```

Once you have built the query, pass it to the database connection of your
choice as a string, and send the bound values along with it.

```php
<?php
// the PDO connection
$pdo = new PDO(...);

// prepare the statement
$sth = $pdo->prepare($update->__toString())

// execute with bound values
$sth->execute($update->getBindValues());
?>
```

### DELETE

Build a _DELETE_ query using the following methods. They do not need to
be called in any particular order, and may be called multiple times.

```php
<?php
$delete = $query_factory->newDelete();

$delete
    ->from('foo')                   // FROM this table
    ->where('zim = :zim')           // AND WHERE these conditions
    ->where('gir = ?', 'doom')      // bind this value to the condition
    ->orWhere('gir = :gir');        // OR WHERE these conditions
    ->bindValue('bar', 'bar_val',   // bind one value to a placeholder
    ->bindValues([                  // bind these values to the query
        'baz' => 99,
        'zim' => 'dib',
        'gir' => 'doom',
    ]);
?>
```

Once you have built the query, pass it to the database connection of your
choice as a string, and send the bound values along with it.

```php
<?php
// the PDO connection
$pdo = new PDO(...);

// prepare the statement
$sth = $pdo->prepare($delete->__toString())

// execute with bound values
$sth->execute($delete->getBindValues());
?>
```

## MySQL Query Objects ('mysql')

These 'mysql' query objects have additional MySQL-specific methods:

- SELECT
    - `calcFoundRows()` to add or remove `SQL_CALC_FOUND_ROWS` flag
    - `cache()` to add or remove `SQL_CACHE` flag
    - `noCache()` to add or remove `SQL_NO_CACHE` flag
    - `bigResult()` to add or remove `BIG_RESULT` flag
    - `smallResult()` to add or remove `SMALL_RESULT` flag
    - `bufferResult()` to add or remove `BUFFER_RESULT` flag
    - `highPriority()` to add or remove `HIGH_PRIORITY` flag
    - `straightJoin()` to add or remove `STRAIGHT_JOIN` flag

- INSERT
    - `highPriority()` to add or remove `HIGH_PRIORITY` flag
    - `lowPriority()` to add or remove `LOW_PRIORITY` flag
    - `ignore()` to add or remove `IGNORE` flag
    - `delayed()` to add or remove `DELAYED` flag

- UPDATE
    - `lowPriority()` to add or remove `LOW_PRIORITY` flag
    - `ignore()` to add or remove `IGNORE` flag
    - `where()` and `orWhere()` to add WHERE conditions flag
    - `orderBy()` to add an ORDER BY clause flag
    - `limit()` to set a LIMIT count

- DELETE
    - `lowPriority()` to add or remove `LOW_PRIORITY` flag
    - `ignore()` to add or remove `IGNORE` flag
    - `quick()` to add or remove `QUICK` flag
    - `orderBy()` to add an ORDER BY clause
    - `limit()` to set a LIMIT count
    
## PostgreSQL Query Objects ('pgsql')

These 'pgsql' query objects have additional PostgreSQL-specific methods:

- INSERT
    - `returning()` to add a `RETURNING` clause

- UPDATE
    - `returning()` to add a `RETURNING` clause

- DELETE
    - `returning()` to add a `RETURNING` clause


## SQLite Query Objects ('sqlite')

These 'sqlite' query objects have additional SQLite-specific methods:

- INSERT
    - `orAbort()` to add or remove an `OR ABORT` flag
    - `orFail()` to add or remove an `OR FAIL` flag
    - `orIgnore()` to add or remove an `OR IGNORE` flag
    - `orReplace()` to add or remove an `OR REPLACE` flag
    - `orRollback()` to add or remove an `OR ROLLBACK` flag

- UPDATE
    - `orAbort()` to add or remove an `OR ABORT` flag
    - `orFail()` to add or remove an `OR FAIL` flag
    - `orIgnore()` to add or remove an `OR IGNORE` flag
    - `orReplace()` to add or remove an `OR REPLACE` flag
    - `orRollback()` to add or remove an `OR ROLLBACK` flag
    - `orderBy()` to add an ORDER BY clause
    - `limit()` to set a LIMIT count
    - `offset()` to set an OFFSET count

- DELETE
    - `orAbort()` to add or remove an `OR ABORT` flag
    - `orFail()` to add or remove an `OR FAIL` flag
    - `orIgnore()` to add or remove an `OR IGNORE` flag
    - `orReplace()` to add or remove an `OR REPLACE` flag
    - `orRollback()` to add or remove an `OR ROLLBACK` flag
    - `orderBy()` to add an ORDER BY clause
    - `limit()` to set a LIMIT count
    - `offset()` to set an OFFSET count

## Microsoft SQL Query Objects ('sqlsrv')

The 'sqlsrv' query objects have no additional methods specific to Microsoft SQL Server.

In general, `limit()` and `offset()` with Microsoft SQL Server are best
combined with `orderBy()`. The `limit()` and `offset()` methods on the
Microsoft SQL Server query objects will generate sqlsrv-specific variations of
`LIMIT ... OFFSET`:

- If only a `LIMIT` is present, it will be translated as a `TOP` clause.

- If both `LIMIT` and `OFFSET` are present, it will be translated as an
  `OFFSET ... ROWS FETCH NEXT ... ROWS ONLY` clause. In this case there *must*
  be an `ORDER BY` clause, as the limiting clause is a sub-clause of `ORDER
  BY`.
