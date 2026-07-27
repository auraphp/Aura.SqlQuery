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
do that, or when two conditions bind different values to the same name, it throws
`Aura\SqlQuery\Exception\LogicException` — see [UPDATE](./update.md) for the case
that trips people up, a condition testing a column the query also sets.

For example, this throws a `LogicException` because `:val` is bound to two
different values:

```php
$query = $queryFactory->newSelect();

try {
    $query
        ->cols(['*'])
        ->from('orders')
        ->where('status = :val', ['val' => 'pending'])
        ->orWhere('channel = :val', ['val' => 'web']);
} catch (\Aura\SqlQuery\Exception\LogicException $e) {
    // throws: The placeholder ':val' is already in use by a WHERE condition...
}
```

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

The upsert methods stay clear of `cols()` on their own: `doUpdateCol()` and
`onDuplicateKeyUpdateCol()` derive their placeholder by suffixing the column
name, so `cols(['name' => 'Alice'])` binds `:name` while
`onDuplicateKeyUpdateCol('name', 'updated')` binds `:name__on_duplicate_key`,
and both values survive.

The suffix only settles the ordinary case, though; the derived names are
reserved, so do not bind them yourself. A column literally named
`name__on_duplicate_key` in `cols()` collides with
`onDuplicateKeyUpdateCol('name', ...)`, and `name__on_conflict` collides with
`doUpdateCol('name', ...)`. Both throw the same `LogicException`.

### UNION Branches

`union()` and `unionAll()` build the branch so far into SQL and keep it, so
that SQL goes on binding the placeholders it was written with. A later branch
may not rebind one of those names to a *different* value -- the rendered branch
would silently start running with the new one -- so this throws:

```php
$select = $queryFactory->newSelect();

try {
    $select
        ->cols(['*'])->from('a')->where('id = :id', ['id' => 1])
        ->union()
        ->cols(['*'])->from('b')->where('id = :id', ['id' => 2]);
} catch (\Aura\SqlQuery\Exception\LogicException $e) {
    // throws: The placeholder ':id' is already in use by a rendered UNION
    // branch...
}
```

Binding the *same* value is fine, since there is nothing to lose -- one filter
applied to both halves needs only one placeholder:

```php
$select = $queryFactory->newSelect();

$select
    ->cols(['*'])->from('a')->where('tenant = :tenant', ['tenant' => 5])
    ->union()
    ->cols(['*'])->from('b')->where('tenant = :tenant', ['tenant' => 5]);
```

```sql
SELECT
    *
FROM
    "a"
WHERE
    tenant = :tenant
UNION
SELECT
    *
FROM
    "b"
WHERE
    tenant = :tenant
```

A rendered branch holds its names against `resetWhere()` and the other clause
resets too, since those clauses have been built into SQL already.
`resetUnions()` discards that SQL and releases the names with it -- except
those a clause of the current branch is sharing, which pass to that clause
rather than going free, since it is still binding them.

This covers hand-bound values as well. A branch can be written around
`bindValue()` -- `where('id = :id')` with the value supplied separately -- and
the retained SQL binds `:id` no differently, so a later clause cannot rebind it
to another value.

A branch holds the names its SQL actually spells as `:name`. A name bound but
never used -- one a `resetWhere()` dropped from the branch before it was
rendered, say -- is not held, since nothing in that SQL can bind it, and the
next branch may use it for a value of its own.

Positional placeholders are the exception. `where('id = ?')` keeps the `?`
token and binds its value by number, so there is no name in the statement to
look for; those are held on the strength of having been bound at all. The
alternative would be to release a name the rendered SQL is certainly using and
let a later branch overwrite it, leaving the first branch to run on the second
branch's data with nothing reported.
