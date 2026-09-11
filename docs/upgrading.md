## Upgrading from 3.x

**Most calling code needs nothing beyond the PHP version.** The query-building
API is the one you already know: the same factory, the same `select()`,
`cols()`, `where()`, the same `getStatement()` and `getBindValues()`.

What changed is that several situations which used to fail quietly, or fail
with an unhelpful PHP error, now throw a named exception at build time. Those
are the ones to look for, because a query that was silently wrong keeps
building today and throws tomorrow. Steps 3 through 5 cover them.

Steps 7 and 8 reach you only if you extend or implement the package's classes.
Step 2 reaches you either way: it is the one to check first if you subclass,
since an override whose signature no longer matches its parent fails when the
class loads rather than when the query runs -- and if your own files declare
`strict_types`, it reaches your calling code too.

The version jumps 3.x to 7.x. There is no 4.x, 5.x, or 6.x of this package;
the number is shared across the Aura packages rather than counting this one's
releases.

Work through these in order.

### 1. Move to PHP 8.4

```
composer require aura/sqlquery:^7.0
```

The package still has no runtime dependencies.

### 2. Add Types To Any Methods You Override

It comes second because the subclass half of it fatals on load rather than at
runtime, so it is the one to find first if you extend the package. Read to the
end even if you only call it: whether the rest reaches you turns on whether
your own files declare `strict_types`.

Every parameter in the package now declares its type. If you extend an
Aura.SqlQuery class, each overridden method needs a signature compatible with
its parent:

```php
<?php
// 3.x
public function quoteName($spec) { /* ... */ }
public function limit($limit)    { /* ... */ }

// 7.x
public function quoteName(string $spec) { /* ... */ }
public function limit(int $limit)       { /* ... */ }
?>
```

The quickest way to find every override you need to touch is to load your
classes and let PHP report the incompatible signatures, since it checks each
one against its parent at class-load time.

Part of this does reach callers, and **how much depends on your files, not on
this package's.** `strict_types` is declared by the calling file and governs
the calls made from it, whatever the file defining the method says. This
package declares none, which settles nothing for you -- what matters is
whether the file making the call does.

From a file **without** `declare(strict_types=1)`, which is the historical
default, PHP coerces a scalar at the boundary as it always has. Only a value
that cannot coerce is new:

```php
<?php
$select->limit('5');     // 3.x: LIMIT 5.    7.x: LIMIT 5.
$select->limit('abc');   // 3.x: LIMIT 0.    7.x: TypeError.
?>
```

From a file **with** `declare(strict_types=1)`, every call into the package is
strict, so a scalar must already be the declared type:

```php
<?php
declare(strict_types=1);

$select->limit('5');     // 3.x: LIMIT 5.    7.x: TypeError.
$select->limit(5);       // fine.
?>
```

If your codebase declares `strict_types` widely, this is the part of the
upgrade to budget for. The usual way to meet it is a limit, offset or page
read out of a request and passed along as the string it arrived as; cast at
that edge:

```php
<?php
$select->page((int) $request->get('page'));
?>
```

Two parameters went the other way and take more than 3.x documented:
`fromSubSelect()` and `joinSubSelect()` accept `string|SelectInterface` rather
than `string|Select`. That is what the code already passed on to
`subSelect()`, so nothing that worked before stops working.

### 3. Give Each Bound Value Its Own Placeholder Name

This is the change most likely to surface in a working application, because
what it catches is a query that was already wrong.

When two different parts of one query bind different values to the same
placeholder name, the package now throws
_Aura\SqlQuery\Exception\LogicException_ instead of discarding one of the
values:

```php
<?php
// Threw nothing in 3.x; throws LogicException in 7.x.
$update->table('orders')
    ->cols(['status' => 'shipped'])
    ->where('status = :status', ['status' => 'pending']);
?>
```

In 3.x that rendered `SET "status" = :status WHERE status = :status` against a
single bound value, so the UPDATE set the column to `pending` -- the value
meant only to choose the rows -- and reported nothing. Bind the condition
under a name of its own:

```php
<?php
$update->table('orders')
    ->cols(['status' => 'shipped'])
    ->where('status = :old_status', ['old_status' => 'pending']);
?>
```

The check counts conditions separately per clause, so two WHERE conditions, or
a WHERE and a HAVING, binding different values to one name is caught as well.
Two halves of a union may share a name as long as they ask for the same value.

Three particular cases are worth checking your code for:

- **Separate `?` placeholders.** Two `?` bound by separate `where()` calls now
  throw, because each is numbered from the start of its own values array and so
  asks for the same name. This never worked -- both conditions rendered against
  one bound value and PDO rejected the statement at execute time -- so a query
  doing it was already broken. Several `?` in a single call are unaffected.

- **Bulk inserts.** Each row's placeholders are renamed `<name>_<row>`, and
  those names are now tracked like any others. A condition binding `:status_0`
  alongside `cols(['status' => ...])->addRow([...])` throws; pick a name no row
  can generate.

- **Sub-selects.** A sub-select's bound values are claimed by the clause it is
  rendered into, so `fromSubSelect()` and `joinSubSelect()` hold theirs until
  `resetTables()`, not until `resetWhere()`.

`resetWhere()`, `resetHaving()` and `resetTables()` release the names their
clause claimed, so a placeholder can be reused after a reset. Binding by hand
with `bindValue()` and `bindValues()` is unaffected and may still overwrite any
value.

### 4. Name One Table Per Update And Delete

`Update::table()` and `Delete::from()` take a single table and now throw
_Aura\SqlQuery\Exception\LogicException_ when given a list:

```php
<?php
// 3.x: quoted the whole string, giving the identifier "a," "b".
// 7.x: throws LogicException.
$update->table('a, b');
?>
```

There is no portable statement to build -- MySQL writes a multi-table update
as `UPDATE a, b SET ...`, PostgreSQL and SQLite as `UPDATE a SET ... FROM b`,
SQL Server as `UPDATE a SET ... FROM a JOIN b`, and `DELETE FROM a, b` is valid
nowhere. Match the other table with a sub-select in the WHERE clause instead.

`Select::from()` does accept a list, and now builds it properly: `from('t1,
t2')` is the same as calling `from()` once per table, where 3.x quoted the
string whole as `"t1," "t2"`. If you were working around that by splitting the
string yourself, you can stop. An identifier you quoted yourself is left as you
wrote it, so a comma inside one stays part of the name.

### 5. Catch ExceptionInterface

The package throws concrete exceptions from the new `Aura\SqlQuery\Exception`
namespace -- _LogicException_, _BadMethodCallException_ and
_InvalidArgumentException_ -- each extending its SPL counterpart and
implementing the new _Aura\SqlQuery\ExceptionInterface_ marker. That marker is
the recommended catch-all:

```php
<?php
use Aura\SqlQuery\ExceptionInterface;

try {
    $statement = $select->getStatement();
} catch (ExceptionInterface $e) {
    // ...
}
?>
```

`Aura\SqlQuery\Exception` is now a deprecated *interface* extending that
marker, so an existing `catch (Aura\SqlQuery\Exception $e)` block keeps
catching everything until the interface is removed in 8.x. Since every SPL
parent used derives from `\LogicException`, `catch (\LogicException $e)` also
catches the lot.

Code that **instantiated or subclassed** `Aura\SqlQuery\Exception` directly
must switch to one of the concrete classes -- it is an interface now, and an
interface cannot be constructed.

One more call worth knowing about: asking a dialect for a modifier it does not
have now throws _Exception\BadMethodCallException_ rather than fatalling with
"call to undefined method". That covers `ignore()` on Pgsql Update and Delete,
Sqlite Delete, and Sqlsrv Update and Delete, and `orReplace()` on Mysql Update,
Pgsql Insert and Update, and Sqlsrv Insert and Update. These are refusals, not
gaps: SQLite's DELETE grammar has no OR clause and Postgres has no REPLACE.

### 6. Spell Insert-Ignore As ignore()

`ignore()` is now the spelling on every dialect that supports it. Sqlite's
`orIgnore()` remains as a deprecated alias on both _Insert_ and _Update_:

```php
<?php
// 3.x
$insert->orIgnore();

// 7.x
$insert->ignore();
?>
```

Pgsql\Insert gains `ignore()` too, rendering the Postgres equivalent `ON
CONFLICT DO NOTHING`, and Sqlite\Update gains one matching Mysql\Update.

### 7. Declare The WITH Methods On Any Query Interface You Implement

_InsertInterface_, _UpdateInterface_ and _DeleteInterface_ extend
_WithInterface_, the way _SelectInterface_ already did. Nothing shipped by this
package changes hands, but a class of yours implementing one of those
interfaces directly now has four more methods to declare: `with()`,
`withRecursive()`, `hasWith()` and `resetWith()`.

Extending the concrete query class supplies them, as does using
`Common\WithTrait`.

### 8. Implement getStatement() On Any Direct AbstractQuery Subclass

`AbstractQuery::getStatement()` is abstract. _Select_ and _AbstractDmlQuery_
both write clauses above the ones `build()` renders -- the union branches, the
WITH clause -- so nothing was left for the base implementation to do.

If you extend _AbstractQuery_ directly, say how your statement is assembled
rather than inheriting `return $this->build();`. Subclasses of the shipped
query classes are unaffected.

### 9. Things That Fixed Themselves

No action needed on any of these; they are listed so that a change in generated
SQL does not surprise you.

- An _Insert_ with no columns renders `INSERT INTO t DEFAULT VALUES` (or
  `INSERT INTO t () VALUES ()` on MySQL) and lets the database apply its
  defaults, where 3.x threw a `TypeError`.

- The quoter no longer reads an `AS` inside an expression -- `cast(col as
  varchar)` -- as a column alias, so expressions like that are no longer
  misquoted.

- The quoter recognises `#` as an identifier character, leaves the dot in a
  variable alone, and leaves an identifier you quoted yourself as you wrote it.

- A union of three or more branches holds the placeholder names from every
  branch, not only the most recent one, and a branch no longer holds a name
  that appears solely inside its string literals, comments or quoted
  identifiers.

- A bulk insert combined with an upsert keeps the upsert's bound values;
  in 3.x finishing a row cleared every bound value, and `execute()` failed with
  `HY093: Invalid parameter number`.

### What's New

Upgrading is also how you get at the additions. See [SELECT](./select.md) for
common table expressions via `with()` and `withRecursive()` -- available on
INSERT, UPDATE and DELETE too -- and for passing a _Select_ to `union()` and
`unionAll()`. See [PostgreSQL additions](./pgsql.md) and [Sqlite
additions](./sqlite.md) for the upsert API: `onConflict()`, `doUpdateCol()`,
`doUpdateCols()`, `doUpdate()` and `doUpdateWhere()`. PostgreSQL and MySQL
_Select_ also gain `lateralJoinSubSelect()`.
