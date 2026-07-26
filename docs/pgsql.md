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
`NATURAL`, which reject one. When you omit the condition for the other join
types, `ON true` is added for you, so the example above renders as:

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

Passing a condition to a `CROSS` or `NATURAL` lateral join throws
`Aura\SqlQuery\Exception\LogicException`, because PostgreSQL rejects an `ON`
clause on those and the statement could only fail at execute time.

## INSERT

- `returning()` to add a `RETURNING` clause
- `ignore()` to add an `ON CONFLICT DO NOTHING` clause
- `onConflict()`, `doUpdateCol()`, `doUpdateCols()`, `doUpdate()` and
  `doUpdateWhere()` to add an `ON CONFLICT ... DO UPDATE SET` clause

### Upsert with ON CONFLICT

`onConflict()` names the conflict target, and the `doUpdate*()` methods say what
to change when a row already conflicts:

```php
$insert = $queryFactory->newInsert();
$insert
    ->into('users')
    ->cols(['email' => 'alice@example.com', 'name' => 'Alice'])
    ->onConflict('email')
    ->doUpdateCols(['name']);
```

```sql
INSERT INTO "users" (
    "email",
    "name"
) VALUES (
    :email,
    :name
)
ON CONFLICT ("email") DO UPDATE SET
    "name" = excluded."name"
```

Here `email` is both an inserted column and the conflict target: the row is
inserted if that email is new, and its `name` is overwritten with the proposed
`'Alice'` if the email is already present.

The target must be covered by a unique index or primary key. It may be one
column, an array of columns, or a named constraint:

```php
$insert->onConflict('email');                         // ON CONFLICT ("email")
$insert->onConflict(['tenant_id', 'email']);          // ON CONFLICT ("tenant_id", "email")
$insert->onConflict('ON CONSTRAINT users_email_key'); // ON CONFLICT ON CONSTRAINT "users_email_key"
```

The constraint-name form is PostgreSQL-only; SQLite takes a column list and
nothing else, and rejects it.

PostgreSQL requires a target for `DO UPDATE`; omitting it throws
`Aura\SqlQuery\Exception\LogicException` rather than failing at execute time.
Combining `ignore()` with the `doUpdate*()` methods also throws, since a
statement cannot both do nothing and update.

#### Setting the updated values

`doUpdateCol()` binds a value of your own rather than reusing the proposed one,
and `doUpdate()` takes a raw expression, which is not escaped. This statement
inserts a page row, and on conflict keeps the proposed title, stamps a fixed
status, and increments the existing hit count:

```php
$insert = $queryFactory->newInsert();
$insert
    ->into('pages')
    ->cols(['slug' => 'home', 'title' => 'Home', 'hits' => 1])
    ->onConflict('slug')
    ->doUpdateCols(['title'])
    ->doUpdateCol('status', 'revised')
    ->doUpdate('hits', 'pages.hits + 1');
```

```sql
INSERT INTO "pages" (
    "slug",
    "title",
    "hits"
) VALUES (
    :slug,
    :title,
    :hits
)
ON CONFLICT ("slug") DO UPDATE SET
    "title" = excluded."title",
    "status" = :status__on_conflict,
    "hits" = "pages"."hits" + 1
```

The bind values are `slug`, `title` and `hits` for the insert, plus
`status__on_conflict` for the update; `doUpdateCol()` suffixes its placeholder so
it cannot collide with the inserted column of the same name.

`doUpdateWhere()` decides whether the conflicting row is updated at all. Without
it every conflict updates; with it, a conflict whose row fails the condition is
left untouched:

```php
$insert = $queryFactory->newInsert();
$insert
    ->into('pages')
    ->cols(['slug' => 'home', 'title' => 'Home'])
    ->onConflict('slug')
    ->doUpdateCols(['title'])
    ->doUpdateWhere('pages.locked = :locked', ['locked' => false]);
```

```sql
INSERT INTO "pages" (
    "slug",
    "title"
) VALUES (
    :slug,
    :title
)
ON CONFLICT ("slug") DO UPDATE SET
    "title" = excluded."title"
WHERE
    "pages"."locked" = :locked
```

### Qualify columns in raw doUpdate() expressions

Inside `DO UPDATE SET`, an unqualified column name is ambiguous between the table
being inserted into and the `excluded` pseudo-table, and PostgreSQL rejects it:

```php
// ERROR: column reference "hits" is ambiguous
$insert->onConflict('id')->doUpdate('hits', 'hits + 1');

// correct
$insert->onConflict('id')->doUpdate('hits', 'pages.hits + 1');
```

This applies only to raw expressions passed to `doUpdate()`; `doUpdateCols()` and
`doUpdateCol()` build qualified references for you. Note that SQLite accepts the
unqualified form, so an expression that works there can still fail here.

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
