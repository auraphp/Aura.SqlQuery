# MySQL Additions

These 'mysql' query objects have additional MySQL-specific behaviors.

## SELECT

- `calcFoundRows()` to add or remove `SQL_CALC_FOUND_ROWS` flag
- `cache()` to add or remove `SQL_CACHE` flag
- `noCache()` to add or remove `SQL_NO_CACHE` flag
- `bigResult()` to add or remove `SQL_BIG_RESULT` flag
- `smallResult()` to add or remove `SQL_SMALL_RESULT` flag
- `bufferResult()` to add or remove `SQL_BUFFER_RESULT` flag
- `highPriority()` to add or remove `HIGH_PRIORITY` flag
- `straightJoin()` to add or remove `STRAIGHT_JOIN` flag
- `lateralJoinSubSelect()` to add a `JOIN LATERAL` against an aliased
  sub-select

A `LATERAL` join lets the sub-select reference columns from the tables to its
left, which is how you express "the top row per group":

```php
$select = $queryFactory->newSelect();
$select
    ->cols(['dept.name', 'top.name AS top_earner'])
    ->from('dept')
    ->lateralJoinSubSelect(
        'left',
        'SELECT name FROM employee
         WHERE employee.dept_id = dept.id
         ORDER BY salary DESC LIMIT 1',
        'top'
    );
```

The signature matches `joinSubSelect()`: the join type, the sub-select (a string
or another _Select_ object), the alias, an optional condition, and optional bind
values.

MySQL rejects a `LEFT JOIN LATERAL` with no `ON` clause, so when you omit the
condition, `ON true` is added for you (except on `CROSS` and `NATURAL` joins,
which take no `ON` clause). The example above renders as:

```sql
SELECT
    `dept`.`name`,
    `top`.`name` AS `top_earner`
FROM
    `dept`
        LEFT JOIN LATERAL (
            SELECT name FROM employee
            WHERE employee.dept_id = dept.id
            ORDER BY salary DESC LIMIT 1
        ) `top` ON true
```

Passing a condition to a `NATURAL` lateral join throws
`Aura\SqlQuery\Exception\LogicException`, because a `NATURAL` join derives its
condition from the common column names and rejects `ON`. A `CROSS` join *does*
accept a condition on MySQL, where `CROSS` and `INNER` are synonyms — note that
this differs from the PostgreSQL objects, which reject it.

> `LATERAL` requires MySQL 8.0.14 or later. MariaDB has no `LATERAL` support at
> all, so do not use this method against a MariaDB server even though it shares
> the `mysql` query objects.

## INSERT

- `orReplace()` to add or remove `OR REPLACE`
- `highPriority()` to add or remove `HIGH_PRIORITY` flag
- `lowPriority()` to add or remove `LOW_PRIORITY` flag
- `ignore()` to add or remove `IGNORE` flag
- `delayed()` to add or remove `DELAYED` flag

`ignore()` downgrades the errors a row would raise into warnings and skips it.
MySQL casts this net wider than the other dialects: duplicate keys, `NOT NULL`,
`CHECK` and foreign-key violations are all demoted, so check
`SHOW WARNINGS` rather than assuming every row landed:

```php
$insert = $queryFactory->newInsert();
$insert
    ->ignore()
    ->into('users')
    ->cols(['email' => 'alice@example.com', 'name' => 'Alice']);
```

```sql
INSERT IGNORE INTO `users` (
    `email`,
    `name`
) VALUES (
    :email,
    :name
)
```

The flags are not all combinable. `REPLACE` accepts only `LOW_PRIORITY` and
`DELAYED`, so pairing `orReplace()` with `highPriority()` or `ignore()` throws
`Aura\SqlQuery\Exception\LogicException`; and since `LOW_PRIORITY`,
`HIGH_PRIORITY` and `DELAYED` are alternatives to one another, asking for two of
them throws as well. Both are raised when the statement is built, whichever
order the methods were called in.

### ON DUPLICATE KEY UPDATE

In addition, the MySQL _Insert_ object has support for `ON DUPLICATE KEY UPDATE`:

- `onDuplicateKeyUpdate($col, $raw_value)` sets a raw value
- `onDuplicateKeyUpdateCol($col, $value)` is a `col()` equivalent for the update
- `onDuplicateKeyUpdateCols($cols)` is a `cols()` equivalent for the update

```php
$insert = $queryFactory->newInsert();
$insert
    ->into('users')
    ->cols(['email' => 'alice@example.com', 'name' => 'Alice', 'hits' => 1])
    ->onDuplicateKeyUpdateCol('name', 'Updated')
    ->onDuplicateKeyUpdate('hits', 'hits + 1');
```

```sql
INSERT INTO `users` (
    `email`,
    `name`,
    `hits`
) VALUES (
    :email,
    :name,
    :hits
) ON DUPLICATE KEY UPDATE
    `name` = :name__on_duplicate_key,
    `hits` = hits + 1
```

Placeholders for bound values in the `ON DUPLICATE KEY UPDATE` portions will be
automatically suffixed with `__on_duplicate_key` to deconflict them from the
insert placeholders — here the bind values are `email`, `name` and `hits` for
the insert, plus `name__on_duplicate_key` for the update.

Unlike MySQL, PostgreSQL and SQLite take an explicit conflict target and spell
this `ON CONFLICT ... DO UPDATE`; see [the PostgreSQL page](./pgsql.md). Note
that `onDuplicateKeyUpdateCols(['name'])` with no value emits a *new* placeholder
for you to bind, where the `doUpdateCols(['name'])` of those dialects reuses the
value you tried to insert.

## UPDATE

- `lowPriority()` to add or remove `LOW_PRIORITY` flag
- `ignore()` to add or remove `IGNORE` flag
- `where()` and `orWhere()` to add WHERE conditions flag
- `orderBy()` to add an ORDER BY clause flag
- `limit()` to set a LIMIT count

`ignore()` here skips the rows whose update would violate a constraint, leaving
the rest of the statement to apply:

```php
$update = $queryFactory->newUpdate();
$update
    ->ignore()
    ->table('users')
    ->cols(['email' => 'alice@example.com'])
    ->where('id = :id', ['id' => 1]);
```

```sql
UPDATE IGNORE `users`
SET
    `email` = :email
WHERE
    id = :id
```

## DELETE

- `lowPriority()` to add or remove `LOW_PRIORITY` flag
- `ignore()` to add or remove `IGNORE` flag
- `quick()` to add or remove `QUICK` flag
- `orderBy()` to add an ORDER BY clause
- `limit()` to set a LIMIT count

`ignore()` downgrades the errors a delete would raise — a restricting foreign
key, say — to warnings, leaving the offending rows in place:

```php
$delete = $queryFactory->newDelete();
$delete
    ->ignore()
    ->from('users')
    ->where('id = :id', ['id' => 1]);
```

```sql
DELETE IGNORE FROM `users`
WHERE
    id = :id
```
