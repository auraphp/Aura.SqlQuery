# Other Topics

## Identifier Quoting

The query objects quote most identifiers for you: table names, aliases, and
table-qualified column names. A bare column name in a SELECT list is left as
written. For example, under the common _Select_ object with double-quotes for
identifiers:

```php
$select = $queryFactory->newSelect();

$select->cols(['foo', 'bar AS barbar'])
       ->from('table1')
       ->from('table2')
       ->where('table2.zim = 99');
```

```sql
SELECT
    foo,
    bar AS "barbar"
FROM
    "table1",
    "table2"
WHERE
    "table2"."zim" = 99
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

A table-qualified column gets a placeholder with the dot replaced by an
underscore, since PDO reads `:t.a` as the placeholder `:t` followed by `.a`.
So `col('t.a', 1)` binds `:t_a`, `doUpdateCol('t.a', ...)` binds
`:t_a__on_conflict`, and `onDuplicateKeyUpdateCol('t.a', ...)` binds
`:t_a__on_duplicate_key`; bind those names, not `t.a`, if you set the value
yourself.

A method that throws part-way through leaves the query as it was before the
call. A `where()` whose second placeholder collides does not keep the first
one's value, and a `union()`, `join()` or `fromSubSelect()` that fails adds
nothing to the query.

### Lists And Question-Mark Placeholders

A condition's placeholders are matched as whole names: binding `:id` does not
touch `:id_2`, and in `x::int` the `::int` is a cast rather than a placeholder.
A `?` or `:name` inside a quoted string or a quoted identifier is part of the
text and is left alone.

A value given for a `?` is bound under a generated name, `:__1__`, `:__2__` and
so on, and an array bound to any placeholder is written as a list of them, one
per value. So a statement never mixes `?` with named placeholders, which plain
PDO rejects on MySQL and PostgreSQL, and two `?` from separate calls -- a join
and a `WHERE`, say -- do not both claim the number 0. The values are matched to
the `?` placeholders in order, counting only the `?` placeholders -- a named
value in the same array does not take up a position:

```php
$select = $queryFactory->newSelect();

$select
    ->cols(['*'])
    ->from('t')
    ->where('a = ? AND b IN (?) AND c = :c', [1, [2, 3], 'c' => 'x']);
```

```sql
SELECT
    *
FROM
    "t"
WHERE
    a = :__1__ AND b IN (:__2__, :__3__) AND c = :c
```

Values given for `?` have to match the `?` placeholders one for one; more or
fewer throws `Aura\SqlQuery\Exception\InvalidArgumentException`. A `?` no value
was given for -- in a condition given no values, or only named ones, as in
`where('a = :a AND b = ?', ['a' => 1])` -- is kept as written, for a value
bound by hand with `bindValue()`, as in 3.x.
A doubled `??` is PDO's escape for a literal `?`, such as PostgreSQL's JSON
operator, and is left alone, as is a `?` inside a comment or a string literal.
Both are read as the dialect reads them: on MySQL `#` begins a comment and `--`
does only when whitespace follows, so `a--?` there is subtraction and a
placeholder, and on PostgreSQL an `E'...'` escape string or a `$$...$$`
dollar-quoted string is a literal too.

Resetting a clause -- `resetWhere()`, `resetHaving()`, `resetTables()`,
`resetUnions()`, `resetWith()` -- drops the generated values it bound, since nobody can rebind
a generated name and a value left behind would be a parameter the statement no
longer spells. A value bound under a name you wrote is kept, as it always has
been, and so is a generated one that a rendered `union()` branch still uses.

The generated names are numbered by the query that holds them. When a query
comes into another one -- as a sub-select bound to a placeholder, through
`fromSubSelect()` or `joinSubSelect()`, as a `union()` branch, or as a CTE --
its generated names are renumbered from the receiving query's own sequence, so
two queries that each bind a list do not collide over `:__1__`.

#### Mixing `?` And Named Placeholders

A `?` is kept only where no value was given for it -- one written into
`cols()`, a raw `from()` or join string, or a condition given no values or only
named ones, for a value you bind by hand with `bindValue()`. A query that keeps such a `?` and has
a `:name` anywhere else, including the generated ones, is a statement that
mixes the two styles, and whether that runs depends on what executes it, not
on this package:

- PDO's own placeholder parser, which pdo_mysql and pdo_pgsql use, rejects it
  at `prepare()` with `SQLSTATE[HY093]: Invalid parameter number: mixed named
  and positional parameters`.
- pdo_sqlite accepts the statement, but `execute($query->getBindValues())`
  leaves the `?` unbound and reports nothing, so the query runs against `NULL`.
- Aura.Sql's _ExtendedPdo_ rewrites the placeholders before PDO sees them, and
  runs it.

With plain PDO, bind such a value by name instead: write `:name` where the `?`
was, and `bindValue('name', ...)`.

### Bulk Insert Rows

A bulk insert renames each row's placeholders, appending the row number: two
rows of `cols(['status' => ...])` bind `:status_0` and `:status_1`. Those
generated names are reserved in the same way, so binding one from another part
of the query throws:

```php
// onConflict() is PostgreSQL and SQLite only, so this example needs one of
// those factories rather than the shared one used elsewhere on this page
$pgsqlFactory = new \Aura\SqlQuery\QueryFactory('pgsql');
$insert = $pgsqlFactory->newInsert();

try {
    $insert
        ->into('t')
        ->cols(['status' => 'row0'])
        ->addRow(['status' => 'row1'])
        ->onConflict('id')
        ->doUpdateCol('status')
        ->doUpdateWhere('t.note = :status_0', ['status_0' => 'checked']);
} catch (\Aura\SqlQuery\Exception\LogicException $e) {
    // throws: The placeholder ':status_0' is already in use by a
    // bulk-insert row...
}
```

Name the condition's placeholder something a row cannot generate -- `:note` --
and both values survive. As everywhere else, `bindValue()` may still overwrite
a row's value by its generated name.

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

Positional placeholders bound by hand are the exception. `where('id = ?')` with
no values keeps the `?` token, and `bindValue(0, ...)` binds its value by
number, so there is no name in the statement to
look for; those are held on the strength of having been bound at all. The
alternative would be to release a name the rendered SQL is certainly using and
let a later branch overwrite it, leaving the first branch to run on the second
branch's data with nothing reported.
