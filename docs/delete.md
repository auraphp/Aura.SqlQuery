# DELETE

Build a _Delete_ query using the following methods. They do not need to
be called in any particular order, and may be called multiple times.

```php
$delete = $queryFactory->newDelete();

$delete
    ->from('foo')                   // FROM this table
    ->where('zim = :zim')           // AND WHERE these conditions
    ->orWhere('gir = :gir')         // OR WHERE these conditions
    ->bindValue('bar', 'bar_val')   // bind one value to a placeholder
    ->bindValues([                  // bind these values to the query
        'baz' => 99,
        'zim' => 'dib',
        'gir' => 'doom',
    ]);
```

Once you have built the query, pass it to the database connection of your
choice as a string, and send the bound values along with it.

```php
// the PDO connection
$pdo = new PDO(...);

// prepare the statement
$sth = $pdo->prepare($delete->getStatement())

// execute with bound values
$sth->execute($delete->getBindValues());
```

## WITH

```php
    ->with($name, $spec, array $cols = array())  // WITH "name" AS ( ... )
    ->withRecursive($name, $spec, array $cols = array())  // WITH RECURSIVE "name" AS ( ... )
```

A common table expression prefixes the whole statement, and the name it
defines is used below it as an ordinary table would be -- typically in the
`WHERE` that picks the rows to delete:

```php
$seniors = $queryFactory->newSelect()
    ->cols(['id'])
    ->from('employee')
    ->where('salary >= :cutoff', ['cutoff' => 300]);

$delete = $queryFactory->newDelete()
    ->with('seniors', $seniors)
    ->from('employee')
    ->where('id IN (SELECT id FROM seniors)');
```

```sql
WITH "seniors" AS (
    SELECT
        id
    FROM
        "employee"
    WHERE
        salary >= :cutoff
)
DELETE FROM "employee"
WHERE
    id IN (SELECT id FROM seniors)
```

The clause behaves exactly as it does on a _Select_: see [the WITH section of
the SELECT page](select.md#with) for `$spec` as a string or a _Select_, several
CTEs at once, `withRecursive()` -- which renders a plain `WITH` on SQL Server,
as it does for a _Select_ -- the placeholder names a CTE claims, and
`resetWith()`.
