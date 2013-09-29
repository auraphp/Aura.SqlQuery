# Aura.Sql_Query

Provides query builders for MySQL, Postgres, SQLite, and Microsoft SQL Server.
These builders are independent of any particular database connection library,
although [PDO](http://php.net/PDO) in general is recommended.

## Foreword

### Requirements

This library requires PHP 5.4 or later, and has no userland dependencies.

### Installation

This library is installable and autoloadable via Composer with the following
`require` element in your `composer.json` file:

    "require": {
        "aura/sql-query": "dev-develop-2"
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


## Getting Started

First, instantiate a _QueryFactory_:

```php
<?php
use Aura\Sql_Query\QueryFactory;

$query_factory = new QueryFactory;
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

By default, the _QueryFactory_ will return query objects that are compatible
with MySQL, PostgreSQL, and SQLite.  Only the methods available to all three
of these open-source database systems will be available. If you want query
objects that implement functionality specific to one database, pass its
type as a param to the _QueryFactory_:

```php
<?php
use Aura\Sql_Query\QueryFactory;

// have the query factory create PostgreSQL query objects
$query_factory = new QueryFactory('pgsql'); // mysql, pgsql, sqlite, sqlsrv
?>
```

The query objects do not execute queries against a database. When you are done
building the query, you will need to pass it to a database connection of your
choice.  In the examples below, we will use the [Aura.Sql][] _ExtendedPdo_
object for the database connection, but any database library that uses named
placeholders and bound values should work just as well.

[Aura.Sql]: https://github.com/auraphp/Aura.Sql/tree/develop-2

## Common Queries

The "common" query objects implement a shared subset of MySQL, PostgreSQL, and
SQLite functionality. Methods called on the "common" query objects should work
with any of those three open-source databases, but functionality specific to
any one of them will not be available through the object methods.

### SELECT

Build a common _Select_ query using the following methods. They do not need to
be called in any particular order, and may be called multiple times.

```php
<?php
$select
    ->distinct()                    // SELECT DISTINCT
    ->cols([                        // select these columns
        'id',
        'name AS namecol',
        'COUNT(foo) AS foo_count',
    ])
    ->from(['foo AS f'])            // FROM these tables
    ->join(                         // JOIN ...
        'LEFT',                     // left/inner/natural/etc
        'doom AS d'                 // this table name
        'foo.id = d.foo_id'         // ON these conditions
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
    ->bindValues([                  // bind these value to named placeholders
        'foo' => 'foo_val',
        'bar' => 'bar_val',
        'baz' => 'baz_val',
    ]);
?>

> N.b. The example is to show off all the methods, and does not necessarily
> represent a syntactically valid SELECT statement.

Once you have built the query, pass it to the database connection of your
choice as a string, and send the bound values along with it.


```php
<?php
use Aura\Sql\ExtendedPdo;

$pdo = new ExtendedPdo(...);
$result = $pdo->fetchAll($select->__toString(), $select->getBindValues());
?>
```

Insert
------

To get a new `Insert` object, invoke the `newInsert()` method on the 
_QueryFactory_ object.
You can then modify the `Insert` object and pass it to the `query()` method.

```php
<?php
// create a new Insert object
$insert = $query_factory->newInsert();

// INSERT INTO foo (bar, baz, date) VALUES (:bar, :baz, NOW());
$insert->into('foo')
       ->cols(['bar', 'baz'])
       ->set('date', 'NOW()');

$bind = [
    'bar' => null,
    'baz' => 'zim',
];

$stmt = $connection->query($insert, $bind);
```

Update
------

To get a new `Update` object, invoke the `newUpdate()` method on the 
_QueryFactory_ object.
You can then modify the `Update` object and pass it to the `query()` method.

```php
<?php
// create a new Update object
$update = $query_factory->newUpdate();

// UPDATE foo SET bar = :bar, baz = :baz, date = NOW() WHERE zim = :zim OR gir = :gir
$update->table('foo')
       ->cols(['bar', 'baz'])
       ->set('date', 'NOW()')
       ->where('zim = :zim')
       ->orWhere('gir = :gir');

$bind = [
    'bar' => 'barbar',
    'baz' => 99,
    'zim' => 'dib',
    'gir' => 'doom',
];

$stmt = $connection->query($update, $bind);
```

Delete
------

To get a new `Delete` object, invoke the `newDelete()` method on the 
_QueryFactory_ object.
You can then modify the `Delete` object and pass it to the `query()` method.

```php
<?php
// create a new Delete object
$delete = $query_factory->newDelete();

// DELETE FROM WHERE zim = :zim OR gir = :gir
$delete->from('foo')
       ->where('zim = :zim')
       ->orWhere('gir = :gir');

$bind = [
    'zim' => 'dib',
    'gir' => 'doom',
];

$stmt = $connection->query($delete, $bind);
```

## MySQL Queries

## PostgreSQL Qeries

## SQLite Queries

## Microsoft SQL Queries

