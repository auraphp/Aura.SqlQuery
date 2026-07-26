# PostgreSQL Additions

These 'pgsql' query objects have additional PostgreSQL-specific behaviors.

## SELECT

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

PostgreSQL requires an `ON` clause on every `LATERAL` join except `CROSS` and
`NATURAL`. When you omit the condition for the other join types, `ON true` is
added for you, so the example above renders as:

```sql
SELECT
    "dept"."name",
    "top"."name" AS "top_earner"
FROM
    "dept"
        LEFT JOIN LATERAL (
            SELECT name FROM employee
            WHERE employee.dept_id = dept.id
            ORDER BY salary DESC LIMIT 1
        ) "top" ON true
```

## INSERT

- `returning()` to add a `RETURNING` clause

### Last Insert IDs and Table Inheritance

PostgreSQL determines the default sequence name for the last inserted ID by
concatenating the table name, the column name, and a `seq` suffix, using
underscore separators (e.g. `table_col_seq`).

However, when inserting into an extended or inherited table, the parent table is
used for the sequence name, not the child (insertion) table. This package allows
you to override the default last-insert-id name with the method
`setLastInsertIdNames()` on both _QueryFactory_ and the _Insert_ object itself.
Pass an array of `inserttable.col` keys mapped to `parenttable_col_seq` values,
and the _Insert_ object will use the mapped sequence names instead of the
default names.

```php
$queryFactory->setLastInsertIdNames([
    'child.id' => 'parent_id_seq'
]);

$insert = $queryFactory->newInsert();
$insert->into('child');
// ...
$seq = $insert->getLastInsertIdName('id');
```

The `$seq` name is now `parent_id_seq`, not `child_id_seq` as it would have been
by default.

## UPDATE

- `returning()` to add a `RETURNING` clause

## DELETE

- `returning()` to add a `RETURNING` clause
