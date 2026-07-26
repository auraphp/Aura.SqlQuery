# SQLite Additions

These 'sqlite' query objects have additional SQLite-specific behaviors.

## INSERT

- `orAbort()` to add or remove an `OR ABORT` flag
- `orFail()` to add or remove an `OR FAIL` flag
- `orIgnore()` to add or remove an `OR IGNORE` flag
- `orReplace()` to add or remove an `OR REPLACE` flag
- `orRollback()` to add or remove an `OR ROLLBACK` flag
- `onConflict()`, `doUpdateCol()`, `doUpdateCols()`, `doUpdate()` and
  `doUpdateWhere()` to add an `ON CONFLICT ... DO UPDATE SET` clause

The `OR` flags are alternatives to one another, so setting two of them throws
`Aura\SqlQuery\Exception\LogicException`.

### Upsert with ON CONFLICT

SQLite adopted the PostgreSQL `ON CONFLICT` grammar in 3.24, and the methods
here work the same way; see [the PostgreSQL page](./pgsql.md) for the full
description.

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

A conflict target is required for `DO UPDATE`, and the `ON CONFLICT` clause
cannot be combined with the `OR` flags above; either throws
`Aura\SqlQuery\Exception\LogicException`. Calling `ignore()` together with a
conflict target renders `ON CONFLICT (...) DO NOTHING` rather than
`INSERT OR IGNORE`.

Unlike PostgreSQL, SQLite accepts an unqualified column name in a raw
`doUpdate()` expression, so `doUpdate('hits', 'hits + 1')` runs here but is
ambiguous on PostgreSQL. Qualify the column if the same code has to run on both.

## UPDATE

- `orAbort()` to add or remove an `OR ABORT` flag
- `orFail()` to add or remove an `OR FAIL` flag
- `orIgnore()` to add or remove an `OR IGNORE` flag
- `orReplace()` to add or remove an `OR REPLACE` flag
- `orRollback()` to add or remove an `OR ROLLBACK` flag
- `orderBy()` to add an ORDER BY clause
- `limit()` to set a LIMIT count
- `offset()` to set an OFFSET count

## DELETE

- `orAbort()` to add or remove an `OR ABORT` flag
- `orFail()` to add or remove an `OR FAIL` flag
- `orIgnore()` to add or remove an `OR IGNORE` flag
- `orReplace()` to add or remove an `OR REPLACE` flag
- `orRollback()` to add or remove an `OR ROLLBACK` flag
- `orderBy()` to add an ORDER BY clause
- `limit()` to set a LIMIT count
- `offset()` to set an OFFSET count
