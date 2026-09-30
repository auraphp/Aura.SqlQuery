# CHANGELOG 

## 7.0.0 (unreleased)

The first release of the 7.x line. The version jumps from 3.x to 7.x, so that
the Aura packages released together share a major version; there is no 4.x,
5.x or 6.x of this package. docs/upgrading.md works through the breaking
changes below as ordered steps.

### Breaking

- [BRK] Bumped the minimum version to PHP 8.4; the CI matrix now covers
  PHP 8.4 and 8.5.

- [BRK] Every parameter and every method return declares a native type, taken
  from the type its docblock already claimed; only the two constructors,
  where PHP allows no return type, are without one. A userland class
  overriding, say, Quoter::quoteName($spec) or Select::limit() must declare a
  matching signature or it will fatal on load. For the methods documented
  `@return $this`, that means `static`: 166 methods declare it, so a subclass
  overriding any of them has to declare `static` too. Callers get a type they
  can rely on where before they had a promise in a comment.

  How this lands on calling code depends on the calling file, not on this
  package: strict_types is declared by the caller and governs the calls made
  from that file, whatever the file defining the method says. This package
  declares none, which settles nothing for you.

  From a file without `declare(strict_types=1)` -- the historical default --
  PHP coerces a scalar at the boundary the way it always has, and `limit('5')`
  still means `LIMIT 5`. Only a value that cannot coerce is new: `limit('abc')`
  throws a TypeError where the cast inside used to make it zero, which
  dropped the LIMIT without a word. Those casts are gone, the declaration
  having taken their job.

  From a file with `declare(strict_types=1)`, every one of these calls is
  strict, so a scalar must already be the declared type: `limit('5')` throws
  and `limit(5)` is required. Code that reads a limit or offset out of a
  request and passes the string along is the usual way to meet this, and
  casting at that edge is the fix.

  Two parameters are wider than their docblock said rather than narrower:
  fromSubSelect() and joinSubSelect() take string|SelectInterface, not
  string|Select, which is what the code already accepted. Three docblocks
  were wrong, and the declaration follows the code: Quoter::quoteNamesIn()
  and replaceNamesIn() return a string, not `string|array`, and
  getLastInsertIdName() returns `string|null`, not `mixed`, which also
  narrows that method on InsertInterface.

  Protected methods changed shape as well, for subclasses that override
  them: subSelect() moved from Common\Select up to AbstractQuery and takes a
  `$source` argument, and rebuildCondAndBindValues() takes a `$clause`
  argument.

- [BRK] Every property declares a native type, bar `$builder`: each query
  class narrows that one to its own builder in a docblock, which a native
  type -- invariant in PHP -- would not allow. A subclass redeclaring a
  property has to declare the same type. The properties that were null until
  set -- the INSERT, UPDATE and DELETE table and the conflict target -- are
  nullable; the last-insert-id names and the ON DUPLICATE KEY UPDATE values
  start as empty arrays rather than null.

- [BRK] The interfaces gained methods an implementation outside this package
  has to add. SelectInterface, InsertInterface, UpdateInterface and
  DeleteInterface all extend the new WithInterface (with(),
  withRecursive(), resetWith(), hasWith()); extending the concrete query, or
  using Common\WithTrait, supplies them. SelectInterface::union() and
  unionAll() take an optional `?SelectInterface $select`, and join() and
  joinSubSelect() take the `array $bind = []` their implementations and
  innerJoin()/leftJoin() already had. SelectInterface::reset() returns
  `static`, like every other reset method, rather than void. QueryInterface
  declares resetBindValues(), which every query already had. Pgsql\Insert
  and Sqlite\Insert implement the new Common\OnConflictUpdateInterface.

- [BRK] AbstractQuery::getStatement() is abstract. Select and
  AbstractDmlQuery both write clauses above the ones build() renders -- the
  union branches, the WITH clause -- so nothing was left for the base
  implementation to do, and a query type extending AbstractQuery directly now
  says how its statement is assembled rather than inheriting
  `return $this->build();`.

- [BRK] The package now throws concrete exceptions from the new
  Aura\SqlQuery\Exception namespace — LogicException,
  BadMethodCallException, and InvalidArgumentException — each extending
  its SPL counterpart and implementing the new
  Aura\SqlQuery\ExceptionInterface marker (extends \Throwable), which is
  the recommended catch-all. Aura\SqlQuery\Exception is now a deprecated
  interface extending that marker, so existing `catch
  (Aura\SqlQuery\Exception $e)` blocks keep working until its removal in
  8.x; since every SPL parent used derives from \LogicException, `catch
  (\LogicException $e)` also catches everything. Code that instantiated
  or subclassed Aura\SqlQuery\Exception directly must switch to one of
  the concrete classes. Fixes #151.

- [BRK] Two different parts of one query claiming the same placeholder name
  now throws Aura\SqlQuery\Exception\LogicException instead of silently
  discarding one of the values. The common case is a condition that tests a
  column the query also sets:

      $update->table('orders')
          ->cols(['status' => 'shipped'])
          ->where('status = :status', ['status' => 'pending']);

  which previously rendered `SET "status" = :status WHERE status = :status`
  with a single bound value, so the UPDATE quietly set the column to the value
  meant only to select rows. Bind the condition under its own name
  (`:old_status`) to fix it. This applies even when the two values happen to
  agree, since either part may revise its value afterwards and nothing
  re-checks the pair. Binding by hand with bindValue()/bindValues() is
  unaffected and may still overwrite any value; cols() and the upsert methods
  may still revise a placeholder they already own. The same check covers
  doUpdateCol() and onDuplicateKeyUpdateCol(), whose placeholders are derived
  by suffix and so only collide when a column is literally named
  `<col>__on_conflict` or `<col>__on_duplicate_key`.

  Conditions count separately per clause, so two WHERE conditions, or a WHERE
  and a HAVING, binding different values to one name is caught as well; a
  sub-select's own bound values and those passed alongside a closure condition
  are tracked too, where before they were merged in unchecked. resetWhere(),
  resetHaving() and resetTables() release the names their clause claimed, so
  the placeholder may be reused after a reset; the bound values themselves
  survive a reset as they always have, which is what lets union() bind the
  half it has already rendered. Both halves of a union may filter on one
  placeholder as long as they ask for the same value -- the same tenant id
  either side of the union is not a collision -- but only one clause per
  branch may do so, and resetUnions() then leaves the name with that clause
  rather than freeing it. The names are held for every branch of a union,
  however many there are, and only the names a branch's SQL actually spells
  count: one inside a string literal, a comment or a quoted identifier does
  not, read by each dialect's own quoting rules.

  Two consequences worth calling out. A sub-select's bound values are claimed
  by the clause it is rendered into -- fromSubSelect() and joinSubSelect()
  hold theirs until resetTables() -- rather than by whichever of the
  sub-select's own clauses bound them, so resetWhere() on the outer query no
  longer frees a name the sub-select is still binding. Fixes #238, #248.

- [BRK] The placeholder names a bulk insert generates are now tracked like
  any others. Each row's placeholders are renamed `<name>_<row>`, and those
  banked names were invisible to the collision check while still winning the
  merge in getBindValues() -- so after
  `cols(['status' => ...])->addRow([...])`, a condition binding `:status_0`
  had its value silently replaced by row 0's, and the statement ran against
  the wrong one. Either order now throws
  Aura\SqlQuery\Exception\LogicException; bind the condition under a name no
  row can generate. Columns of the row being built are exempt, since a column
  named `a_1` alongside `a` is renamed out of the way before the merge.
  Binding by hand with bindValue() is unaffected and now correctly overwrites
  a row's value, where before the banked value won regardless. Fixes #241.

- [BRK] A value given for a `?` placeholder in a condition is bound under a
  generated name, as a list for one already was: `where('id = ?', [5])`
  renders `id = :__1__`, and getBindValues() returns `['__1__' => 5]` rather
  than `[0 => 5]`. Passing getBindValues() to execute() is unaffected. Code
  that reached the value by number afterwards -- `getBindValues()[0]`, or
  `bindValue(0, ...)` to replace it -- has to give it a name instead. In
  return, a statement never mixes `?` with named placeholders, which plain
  PDO rejects on MySQL and PostgreSQL (and which pdo_sqlite ran with the `?`
  unbound), and `?` placeholders from separate calls no longer both claim the
  number 0, which 3.x rendered against a single value. A condition given no
  values keeps its `?` for binding by hand, and the values given have to
  match the `?` one for one or InvalidArgumentException is thrown. A doubled
  `??`, PDO's escape for a literal `?`, is not read as a placeholder.

- [BRK] A table-qualified column gets a placeholder PDO can read: `col('t.a',
  1)` binds `:t_a` rather than `:t.a`, which PDO parsed as `:t` followed by
  `.a`. The same goes for doUpdateCol() (`:t_a__on_conflict`) and
  onDuplicateKeyUpdateCol(). Code that bound such a column's value by hand
  under the dotted name must use the underscored one.

- [BRK] Queries that cannot be built, or that would build SQL the database
  rejects, now throw where they used to render something. Each is listed in
  step 5 of docs/upgrading.md:
  - an INSERT, UPDATE or DELETE with no table (LogicException; this was a
    TypeError from the builder, and `UPDATE  SET ...` in 3.x);
  - an UPDATE with no columns (LogicException, where it was a TypeError);
  - forUpdate() on SQLite and SQL Server, which have no FOR UPDATE
    (BadMethodCallException);
  - a condition on a NATURAL join, or on a CROSS join except on MySQL, where
    the two are synonyms (LogicException);
  - a later bulk-insert row with a column the first row does not have
    (InvalidArgumentException, where the column was dropped and its value
    left bound);
  - more or fewer values than a condition has `?` placeholders
    (InvalidArgumentException);
  - a column or alias given to Select::cols() that is not a string
    (InvalidArgumentException);
  - an unknown database type, from the factory's new*() methods
    (InvalidArgumentException, where it was PHP's missing-class error).

### Added

- [ADD] Every query type takes common table expressions, via with() and
  withRecursive():

        $select->with('well_paid', $earners)
               ->cols(['name'])
               ->from('well_paid');

        $delete->with('seniors', $seniors)
               ->from('employee')
               ->where('id IN (SELECT id FROM seniors)');

  The CTE is given as a Select or as a raw string, with an optional column
  list, and a Select is rendered on the spot the way a branch passed to
  union() is; the values it bound come with it. RECURSIVE belongs to the
  clause rather than to one CTE, so a statement mixing recursive and plain
  members spells it once. SQL Server infers the recursion and rejects the
  keyword, so withRecursive() renders a plain WITH there and the same PHP is
  valid on every dialect.

  A CTE belongs to the statement rather than to a branch, so it survives the
  reset union() performs and is written once above every branch. The names it
  binds stay claimed for as long as it is defined, and one other clause may
  ask for a name when it wants the value already bound -- in either order,
  since a CTE is written at the top of the statement whenever with() is
  called. Asking for a second value still throws, and resetWith() releases
  the names along with the clause. A branch passed to union() may not bring
  a WITH clause of its own, which would render as `UNION WITH ... SELECT`;
  it is reported rather than built.

  MySQL is the one dialect that takes no WITH on INSERT: it allows a CTE only
  inside the SELECT an `INSERT ... SELECT` draws from, which this package does
  not build, so Mysql\Insert::with() throws BadMethodCallException rather than
  building a statement that could only fail at execute time. Fixes #130,
  #261.

- [ADD] `union()` and `unionAll()` now accept a Select as the next branch,
  so branches that differ only in part can be built from one another
  instead of being spelled out twice on the same query object:

      $select = $queryFactory->newSelect()
          ->cols(['SUM(amount) AS amount'])
          ->fromSubSelect($by_owner->union($by_property), 't');

  The values the given query has bound come across with it. Both branches
  may bind one placeholder name so long as they bind it to the same value,
  as the two halves of a union already could; two different values throw
  Aura\SqlQuery\Exception\LogicException, since the rendered statement has
  only the one placeholder. Executing such a statement asks the driver to
  bind that name at both spellings, which PDO cannot do for a driver with no
  named parameters of its own -- pdo_sqlsrv reports SQLSTATE 07002, and
  pdo_mysql with ATTR_EMULATE_PREPARES off reports HY093 -- so give each
  branch a name of its own there. The branch is rendered when it is passed, so
  later edits to that query do not reach back into the union, and it is the
  last branch: call `union()` or `unionAll()` again to add another after
  it, rather than building one on the query holding the union. Columns, a
  WHERE, a JOIN, a LIMIT -- anything set on that query afterwards has
  nowhere to render and throws when the statement is built, instead of
  being dropped from it in silence. Binding values is unaffected.
  Passing the query its own object throws rather than guess which reading
  of a self-union was meant; pass a clone. Called with no argument both
  methods behave exactly as before. Fixes #189.

- [ADD] Pgsql\Select and Mysql\Select gain `lateralJoinSubSelect()`,
  rendering `JOIN LATERAL` against an aliased sub-select so the
  sub-select can reference columns from the tables to its left. The
  signature matches `joinSubSelect()`, and the shared implementation
  lives in the new Common\LateralJoinTrait. A LATERAL join needs an `ON`
  clause on every join type except CROSS and NATURAL, so when no
  condition is given for the other join types, `ON true` is rendered.
  Passing a condition to a join type that rejects an `ON` clause throws
  Aura\SqlQuery\Exception\LogicException, as it now does for every join.
  Note that LATERAL requires MySQL 8.0.14 or later, and that MariaDB does
  not support it at all despite sharing the `mysql` query objects. Thanks
  to @golgote for the original implementation in #184.

- [ADD] Insert-ignore is now spelled `ignore()` on every dialect that
  supports it: Mysql\Insert renders `INSERT IGNORE` and Sqlite\Insert
  renders `INSERT OR IGNORE` (both as before); Pgsql\Insert gains
  `ignore()`, rendering the Postgres equivalent `ON CONFLICT DO NOTHING`
  (before any RETURNING clause); and Sqlite\Update gains `ignore()`
  matching Mysql\Update. The Sqlite `orIgnore()` methods remain as
  deprecated aliases, to be removed in 8.x. Sqlsrv, which has no
  equivalent, keeps throwing Exception\BadMethodCallException. Fixes #172;
  related to #158.

- [ADD] Pgsql\Insert and Sqlite\Insert gain an upsert API, declared by the
  new Common\OnConflictUpdateInterface: `onConflict()` sets the conflict
  target (a column, a list of columns as an array or a comma-separated
  string, or `ON CONSTRAINT <name>`), and `doUpdateCol()`, `doUpdateCols()`,
  `doUpdate()` and `doUpdateWhere()` build the `DO UPDATE SET` clause.
  `doUpdateCols()` refers to the proposed row through the `excluded`
  pseudo-table, `doUpdateCol()` binds a separate value, and `doUpdate()`
  takes a raw expression. The two dialects share Common\OnConflictUpdateTrait
  and Common\BuildOnConflictTrait, since SQLite adopted the Postgres
  grammar in 3.24. Both databases require a conflict target for DO UPDATE,
  and combining `ignore()` with the doUpdate methods is contradictory;
  either throws Exception\LogicException at build time. On SQLite the
  clause cannot be mixed with the `OR` flags, and `ignore()` with a target
  renders `ON CONFLICT (...) DO NOTHING` instead of `INSERT OR IGNORE`.
  Note that a raw `doUpdate()` expression must qualify any column it names
  on Postgres, which reads a bare name as ambiguous between the target
  table and `excluded`; SQLite accepts either. The `ON CONSTRAINT <name>`
  conflict target is Postgres-only, so passing it to Sqlite\Insert throws
  Exception\BadMethodCallException. An empty target -- `onConflict([])`,
  `onConflict('')`, a blank column in the array, or the bare keyword
  `ON CONSTRAINT` -- throws Exception\InvalidArgumentException, rather than
  rendering `ON CONFLICT ()` for the database to reject, and so does an
  index expression such as `lower(email)`, which cannot be quoted as a
  name; name the unique index's constraint instead. Addresses the Postgres
  half of #124.

### Changed

- [CHG] Calling `ignore()`, `orReplace()` or the upsert methods on a
  dialect that does not support them now throws
  Exception\BadMethodCallException. Common\Insert::ignore() already did,
  but there was no counterpart on Common\Update or Common\Delete and none
  for orReplace(), so most unsupported combinations were a fatal "call to
  undefined method" instead: `ignore()` on Pgsql Update and Delete, Sqlite
  Delete, and Sqlsrv Update and Delete (since SQL Server has no DELETE
  IGNORE equivalent); `orReplace()` on Mysql Update, Pgsql Insert and
  Update, and Sqlsrv Insert and Update. These are correct refusals rather
  than gaps to fill -- SQLite's DELETE grammar has no OR clause, and
  Postgres has no REPLACE. `orReplace()` on a Delete remains undefined on
  every dialect, since no dialect has such a flag there.

- [CHG] Modifier flags are written in one fixed order rather than the order
  their methods were called in. MySQL requires it for INSERT and UPDATE,
  where the priority comes before IGNORE -- `ignore()->lowPriority()` built
  `UPDATE IGNORE LOW_PRIORITY`, which MySQL rejects -- and the other
  statements take their options in any order, so the one order suits them
  all. A MySQL SELECT with several flags now renders them as DISTINCT,
  HIGH_PRIORITY, STRAIGHT_JOIN, the SQL_* result and cache options, then
  SQL_CALC_FOUND_ROWS.

- [CHG] A method that fails part-way leaves the query as it was. where(),
  having(), the join methods, fromSubSelect(), union(), with(), the column
  methods, the upsert methods and addRows() used to keep whatever they had
  done before the exception -- an appended union branch, a table reference,
  a column, or values bound before the colliding one -- so a caller catching
  it held a query whose next statement bound the wrong values or claimed
  names for SQL it did not contain.

- [CHG] The test suite runs on PHPUnit 12 (replacing yoast/phpunit-polyfills),
  and PHPStan checks the source at level 6 in CI.

- [CHG] Added an `integration` test suite that executes the generated SQL
  against real MySQL, PostgreSQL and SQL Server servers (in addition to
  SQLite), so dialect output is proven to run and not merely to match a
  string. CI runs it against MySQL 8.4/8.0, Postgres 17/15 and SQL Server
  2022/2019 service containers. Locally those cases skip unless
  `DB_MYSQL_DSN` / `DB_PGSQL_DSN` / `DB_SQLSRV_DSN` are set; see
  CONTRIBUTING.md.

### Fixed

- [FIX] Combining two queries that each bound an array no longer loses one
  of the arrays. Each query named the placeholders it generates for an array
  `:__1__`, `:__2__` and so on from one, so a sub-select, a union branch, a
  CTE, a FROM or JOIN sub-select, or a clone combined with its original held
  the same names as the query it was combined with, and one set of values
  silently replaced the other. The names a sub-select brings are now renamed
  from the receiving query's own sequence as it is taken in.

- [FIX] Placeholders in a condition are matched as whole names. `:id` no
  longer rewrites the start of `:id_2` when it is given an array, and a
  sub-select given for `:s` no longer rewrites `:s2`; neither did anything
  but corrupt the statement. `x::int` is not read as a placeholder, and a
  `?` or `:name` inside a string literal or a quoted identifier is left as
  written.

- [FIX] Arrays for `?` placeholders land on the right one. Each array
  replaced its `?` by counting every value given, named or not, and every
  `?` in the condition, including those already replaced, so two lists in
  one condition -- `a IN (?) AND b IN (?)` -- or a list after a named value
  wrote the list in the wrong place and raised warnings.

- [FIX] OFFSET without LIMIT builds SQL the dialect accepts: `LIMIT -1
  OFFSET n` on SQLite and `LIMIT 18446744073709551615 OFFSET n` on MySQL,
  neither of which has OFFSET alone, and `OFFSET n ROWS` with no `FETCH NEXT
  0 ROWS` on SQL Server. The MySQL and SQLite builders that carry this are
  new, and set the new AbstractBuilder::NO_LIMIT constant.

- [FIX] Select::cols() no longer reads the word after DISTINCT or ALL as an
  alias -- `cols(['DISTINCT a'])` rendered `DISTINCT AS "a"` -- and a null
  or empty alias in `cols(['a' => null])` means none rather than `AS ""`.

- [FIX] A select column whose expression has a space inside parentheses,
  such as `COUNT(DISTINCT t1.c1)`, is no longer split into a column and an
  alias; it rendered as `COUNT(DISTINCT AS ...)`. The implicit-alias form
  still works after a balanced call (`COUNT(*) tally`). Fixes the rest of
  #226.

- [FIX] A bulk insert combined with an upsert no longer loses the upsert's
  bound values. Finishing a row cleared every bound value, not just that
  row's, and building the statement finishes the last row -- so
  `cols(['a' => 1])->addRow(['a' => 2])->onDuplicateKeyUpdateCol('a', 3)`
  rendered `:a__on_duplicate_key` while binding only `a_0` and `a_1`, and
  execute() failed with `HY093: Invalid parameter number`. The same applied
  to Postgres and SQLite `doUpdateCol()` and to `doUpdateWhere()`
  conditions. Part of #241.

- [FIX] Naming several tables in one string no longer produces an identifier
  no database has. `from('t1, t2')` was read as a name and its alias and
  quoted whole, giving `"t1," "t2"`; a Select now builds the list, quoting
  and reference-checking each table, so it is the same as calling `from()`
  once per table. An identifier you quoted yourself is left as you wrote it,
  as it already was inside expressions, so a comma within it stays part of
  the name.

  Update::table() and Delete::from() take a single table and now throw
  Aura\SqlQuery\Exception\LogicException for a list, naming the sub-select
  alternative. There is no portable statement to build: MySQL writes a
  multi-table update as `UPDATE a, b SET ...`, PostgreSQL and SQLite as
  `UPDATE a SET ... FROM b`, and SQL Server as `UPDATE a SET ... FROM a JOIN
  b`, while `DELETE FROM a, b` is valid nowhere. Fixes #160.

- [FIX] An Insert with no columns no longer throws a TypeError; it now
  renders `INSERT INTO t DEFAULT VALUES` (or `INSERT INTO t () VALUES ()`
  on MySQL, which does not support DEFAULT VALUES), letting the database
  apply column defaults. Fixes #149.

- [FIX] Mysql\Insert::orReplace() combined with highPriority() or ignore()
  built `REPLACE HIGH_PRIORITY INTO` / `REPLACE IGNORE INTO`, which MySQL
  rejects at parse time: rewriting the INSERT keyword left the flags in
  place, and REPLACE accepts only LOW_PRIORITY and DELAYED. The
  combination now throws Exception\LogicException when the statement is
  built, whichever order the two methods were called in.

- [FIX] Mysql\Insert::orReplace() combined with the
  onDuplicateKeyUpdate*() methods built
  `REPLACE INTO ... ON DUPLICATE KEY UPDATE`, which MySQL rejects with a
  1064 syntax error: REPLACE resolves a conflict by deleting the old row,
  so it has no update clause to take. The combination now throws
  Exception\LogicException when the statement is built.

- [FIX] Mysql\Insert accepted two priority modifiers at once, building
  `INSERT LOW_PRIORITY HIGH_PRIORITY INTO` and the like; MySQL takes at
  most one of LOW_PRIORITY, HIGH_PRIORITY and DELAYED, on REPLACE as well
  as INSERT. Setting more than one now throws Exception\LogicException
  when the statement is built. Mysql\Update and Mysql\Delete are
  unaffected, having only the one priority modifier between them.

- [FIX] The Sqlite conflict clauses -- `orAbort()`, `orFail()`,
  `ignore()`/`orIgnore()`, `orReplace()` and `orRollback()` -- are
  alternatives to each other, but setting two of them stacked both into the
  statement (`INSERT OR IGNORE OR REPLACE`), which SQLite rejects. Setting
  more than one now throws Exception\LogicException when the statement is
  built, on both Sqlite\Insert and Sqlite\Update; switching clauses still
  works by turning the first one off.

- [FIX] The quoter no longer treats an `AS` inside an expression (e.g.
  `cast(col as varchar)`) as a column alias, which produced misquoted
  SQL. Fixes #157.

- [FIX] The quoter now recognizes `#` as an identifier character (legal
  on DB2 / IBM i, in any position), so `table.col#` quotes as
  `"table"."col#"` instead of the broken `"table"."col"#`. Fixes #177.

- [FIX] The quoter no longer treats the dot in a variable as a table/column
  separator. `@@session.time_zone` came out with each half backticked, as
  if `session` were a table, which no server can run; the same went for
  `@@global.x` and for user variables such as `@a.b`, whose names may
  legally contain dots and `$`. A token introduced by `@` is now returned
  as written, however many dots it has, while real column references beside
  it are quoted as before. Fixes #226.

- [FIX] The quoter now leaves an identifier the caller already quoted as
  written, instead of splitting it on the dot inside it. A MySQL column
  named `compound.group` has to be written with backticks by hand, and
  the quoter turned that into nested backticks the server could not
  parse. String literals were already exempt, so PostgreSQL and SQLite
  (double quotes) were unaffected; MySQL (backticks) and SQL Server
  (brackets) were not. Reported in #183.

## 3.0.0

The 3.x line, released alongside 2.8.1; its notes were published only as a
GitHub release until now.

- [BRK] The query builder logic moved out of the query objects into separate
  builder classes, one per query type and dialect where they differ. #131
- [BRK] The SubSelect interface is removed; sub-selects are typehinted on
  SelectInterface. #133
- [BRK] where(), having() and the other conditions take named placeholders,
  with their values passed as an array rather than as trailing arguments.
  #134, #114
- [ADD] where() and having() conditions can be grouped in parentheses by
  passing a closure. #136
- [ADD] Conditions accept an array value for an `IN (:name)` placeholder,
  which is expanded to one placeholder per element. #162, #198
- [ADD] Insert::orReplace(), and ignore() on Common\Insert. #115, #173
- [ADD] insert->col() and update->col() take a variable argument list. #111
- [CHG] The quoter was rewritten, and a database-specific quoter is used
  whenever one exists. #132, #141
- [CHG] limit()/offset() and where()/orWhere() share their code through
  traits; SelectInterface gained the public methods it was missing. #112,
  #116, #120
- [FIX] Bad use of strripos in the quoter. #206
- PHP 5.6 through 8.2 supported; HHVM dropped. #153, #195, #204

## 2.8.1

- [FIX] Bad use of strripos in the quoter. #206, #207

## 2.8.0

- [ADD] Array parameters are split into one placeholder per element, fixing
  the "Array to string conversion" error from PDO. #127
- [FIX] Corrected a preg_split() call. #190
- PHP 5.4 through 8.1 supported in CI; HHVM dropped. #154, #203

## 2.7.1

Hygiene release: update README.

## 2.7.0

- [DOC] Numerous docblock and README updates.

- [ADD] Add various `Select::reset*()` methods. Fixes #84, #95, #94, #91.

- [FIX] On SELECT, allow OFFSET even when LIMIT not specified. Fixes #88.

- [FIX] On SELECT, allow `join*()` before `from*()`. Joins-before-from are added
  to the first from. If no from is ever added, the joins will never be built
  into the statement. Fixes #69, #90.

- [BRK] Bumped the minimum version to PHP 5.3.9 (vs 5.3.0). Fixes #74. This is
  to address a language-level bug in PHP. Technically I think this is a BC
  break, but I hope it is understandable, given that PHP 5.3.x is end-of-life,
  and that Aura.SqlQuery itself simply will not operate on versions earlier
  than that. Updated README to reflect the version requirement.


## 2.6.0

- (DOC) Docblock and README updates; in particular, add an `@method getStatement()` to the QueryInterface for IDE auto-completion.

- (ADD) Select::hasCols() reports if there are any columsn in the Select.

- (ADD) Select::getCols() gets the existing columns in the Select.

- (ADD) Select::removeCol() removes a previously-added column.

- (FIX) Select::reset() now properly resets the table refs for a UNION.

- (FIX) Select::forUpdate() is now fluent.

## 2.5.0

- Docblock and README updates

- The Common\Select class, when binding values from a subselect, now checks for
  `instanceof SubselectInterface` instead of `self`; the Select class now
  implements SubselectInterface, so this should not be a BC break.

- Subselects bound as where/having/etc conditions should now retain ?-bound
  params.

## 2.4.2

This release modifies the testing structure and updates other support files.

## 2.4.1

This release fixes Insert::addRows() so that adding only one row generates the correct SQL statement.

## 2.4.0

This release incorporates two feature additions and one fix.

- ADD: The _Insert_ objects now support multiple-row inserts with the new `addRow()` and `addRows()` methods.

- ADD: The MySQL _Insert_ object now supports `ON DUPLICATE KEY UPDATE` functionality with the new `onDuplicateKeyUpdate*()` methods.

- FIX: The _Select_ methods regarding paging now interact better with LIMIT and OFFSET; in particular, both `setPaging()` now re-calculates the LIMIT and OFFSET values.

## 2.3.0

This release has several new features.

1. The various `join()` methods now have an extra `$bind` param that allows you to bind values to ?-placeholders in the condition, just as with `where()` and `having()`.

2. The _Select_ class now tracks table references internally, and will throw an exception if you try to use the same table name or alias more than once.

3. The method `getStatement()` has been added to all queries, to allow you to get the text of the statement being built. Among other things, this is to avoid exception-related blowups related to PHP's string casting.

4. When binding a value to a sequential placeholder in `where()`, `having()`, etc, the _Select_ class now examind the value to see if it is a query object. If so, it converts the object to a string and replaces the ?-placeholder inline with the string instead of attempting to bind it proper. It also binds the existing sequential placholder values into the current _Select_ in a non-conflicting fashion. (Previously, no binding from the sub-select took place at all.)

5. In `fromSubSelect()` and `joinSubSelect()`, the _Select_ class now binds the sub-select object sequential values to the current _Select_ in a non-conflicting fashion.  (Previously, no binding from the sub-select took place at all.)

The change log follows:

- REF: Extract rebuilding of condition and binding sequential values.

- FIX: Allow binding of values as part of join() methods. Fixes #27.

- NEW: Method Select::addTableRef(), to track table references and prevent double-use of aliases. Fixes #38.

- REF: Extract statement-building to AbstractQuery::getStatement() method. Fixes #30.

- FIX: #47, if value for sequential placeholder is a Query, place it as a string inline

- ADD: Sequential-placeholder prefixing

- ADD: bind values from sub-selects, and modify indenting

- ADD: QueryFactory now sets the sequntial bind prefix

- FIX: Fix line endings in queries to be sure tests will pass on windows and mac. Merge pull request #53 from ksimka/fix-tests-remove-line-endings: Fixed tests for windows.

- Merge pull request #50 from auraphp/bindonjoin: Allow binding of values as part of join() methods.

- Merge pull request #51 from auraphp/aliastracking: Add table-reference tracking to disallow duplicate references.

- Merge pull request #52 from auraphp/bindsubselect. Bind Values From Sub-Selects.

- DOC: Update documentation and support files.

## 2.2.0

To avoid mixing numbered and names placeholders, we now convert numbered ? placeholders in where() and having() to :_#_ named placeholders. This is because PDO is really touchy about sequence numbers on ? placeholders. If we have bound values [:foo, :bar, ?, :baz], the ? placeholder is not number 1, it is number 3. As it is nigh impossible to keep track of the numbering when done out-of-order, we now do a braindead check on the where/having condition string to see if it has ? placholders, and replace them with named :_#_ placeholders, where # is the current count of the $bind_values array.


## 2.1.0

- ADD: Select::fromRaw() to allow raw FROM clause strings.

- CHG: In Select, quote the columns at build time, not add time.

- CHG: In Select, retain columns keyed on their aliases (when given).

- DOC: Updates to README and docblocks.

## 2.0.0

Initial 2.0 stable release.

- The package has been renamed from Sql_Query to SqlQuery, in line with the new Aura naming standards.

- Now compatible with PHP 5.3!

- Refactored traits into interfaces (thanks @mindplay-dk).

- Refactored the internal build process (thanks again @mindplay-dk).

- Added Select::leftJoin()/innerJoin() methods (thanks @stanlemon).

- Methods bindValue() and bindValues() are now fluent (thanks @karikt).

- Select now throws an exception when there are no columns selected.

- In joins, the condition type (ON or USING) may now be part of the condition.

- Extracted new class, Quoter, for quoting identifer names.

- Extracted new class, AbstractDmlQuery, for Insert/Update/Delete queries.

- Select::cols() now accepts `colname => alias` pairs mixed in with sequential colname values.

- Added functionality to map last-insert-id names to alternative sequence names, esp. for Postgres and inherited/extended tables. Cf. QueryFactory::setLastInsertIdNames() and Insert::setLastInsertIdNames().

## 2.0.0-beta1

Initial 2.0 beta release.
