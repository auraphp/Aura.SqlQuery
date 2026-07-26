## Identifier Quoting

In most cases, the query objects will quote identifiers for you. For example,
under the common _Select_ object with double-quotes for identifiers:

```php
$select->cols(['foo', 'bar AS barbar'])
       ->from('table1')
       ->from('table2')
       ->where('table2.zim = 99');

echo $select->getStatement();
// SELECT
//     "foo",
//     "bar" AS "barbar"
// FROM
//     "table1",
//     "table2"
// WHERE
//     "table2"."zim" = 99

```

If you discover that a partially-qualified identifier has not been auto-quoted
for you, change it to a fully-qualified identifier (e.g., from `col_name` to
`table_name.col_name`).

## Table Prefixes

One frequently-requested feature for this package is support for "automatic
table prefixes" on all queries.  This feature sounds great in theory, but in
practice is it (1) difficult to implement well, and (2) even when implemented it
turns out to be not as great as it seems in theory. This assessment is the
result of the hard trials of experience. For those of you who want modifiable
table prefixes, we suggest using constants with your table names prefixed as
desired; as the prefixes change, you can then change your constants.

## Placeholder Names

Bound values live in one flat array keyed by placeholder name, so two values
under one name means one of them is lost. When two *different* parts of a query
do that, it throws `Aura\SqlQuery\Exception\LogicException` — see
[UPDATE](./update.md) for the case that trips people up, a condition testing a
column the query also sets.

Two *conditions* sharing a name is not caught, because both come from the same
part of the query. Watch for it:

```php
$select = $queryFactory->newSelect();

$select
    ->cols(['*'])
    ->from('orders')
    ->where('status = :val', ['val' => 'pending'])
    ->orWhere('channel = :val', ['val' => 'web']);
```

```sql
SELECT
    *
FROM
    "orders"
WHERE
    status = :val
    OR channel = :val
```

Only `'web'` stays bound, so this asks for `status = 'web' OR channel = 'web'`.
Give each condition its own name:

```php
$select = $queryFactory->newSelect();

$select
    ->cols(['*'])
    ->from('orders')
    ->where('status = :status', ['status' => 'pending'])
    ->orWhere('channel = :channel', ['channel' => 'web']);
```

```sql
SELECT
    *
FROM
    "orders"
WHERE
    status = :status
    OR channel = :channel
```

The upsert methods need no such care: `doUpdateCol()` and
`onDuplicateKeyUpdateCol()` derive their placeholder by suffixing the column
name, so `cols(['name' => 'Alice'])` binds `:name` while
`onDuplicateKeyUpdateCol('name', 'updated')` binds `:name__on_duplicate_key`,
and both values survive.
