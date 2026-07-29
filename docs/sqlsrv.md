# SQL Server Additions

The 'sqlsrv' query objects have no additional methods specific to Microsoft SQL
Server. However, `limit()` and `offset()` behaviors are somewhat modified, and
`withRecursive()` writes its clause the way T-SQL spells it.

In general, `limit()` and `offset()` with Microsoft SQL Server are best
combined with `orderBy()`. The `limit()` and `offset()` methods on the
Microsoft SQL Server query objects will generate sqlsrv-specific variations of
`LIMIT ... OFFSET`:

- If only a `LIMIT` is present, it will be translated as a `TOP` clause.

- If both `LIMIT` and `OFFSET` are present, it will be translated as an
  `OFFSET ... ROWS FETCH NEXT ... ROWS ONLY` clause. In this case there *must*
  be an `ORDER BY` clause, as the limiting clause is a sub-clause of `ORDER
  BY`.

## Recursive Common Table Expressions

`RECURSIVE` is not part of the T-SQL grammar: SQL Server infers the recursion
from the CTE naming itself, and rejects the keyword. So `withRecursive()`
writes a plain `WITH` here, and the same PHP that runs on the other dialects
runs unchanged:

```php
$counter = $queryFactory->newSelect()
    ->cols(['1 AS n'])
    ->unionAll()
    ->cols(['n + 1'])
    ->from('counter')
    ->where('n < :stop', ['stop' => 3]);

$select = $queryFactory->newSelect()
    ->withRecursive('counter', $counter, ['n'])
    ->cols(['n'])
    ->from('counter');
```

```sql
WITH [counter] ([n]) AS (
    SELECT
        1 AS [n]
    UNION ALL
    SELECT
        n + 1
    FROM
        [counter]
    WHERE
        n < :stop
)
SELECT
    n
FROM
    [counter]
```

Note also that SQL Server wants the statement before a `WITH` terminated with
a semicolon when it is not the first in the batch; that is the caller's to
add, since this library issues one statement at a time.
