# Contributing

We are happy to review any contributions you want to make. When contributing, please follow the rules outlined at <http://auraphp.com/contributing>.

The time between submitting a contribution and its review one may be extensive; do not be discouraged if there is not immediate feedback.

Thanks!

## Ignoring formatting-only commits in `git blame`

A commit that only reformats code — converting `array()` to `[]`, say — still
touches every line it rewrites, so `git blame` credits it for lines whose author
and reason lie further back. `.git-blame-ignore-revs` lists those commits, and
blame looks through them to the change that actually mattered.

GitHub reads the file on its own. Local `git blame` needs telling once per
clone, since this is not something a repository can configure for you:

```sh
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

Add a commit to the file when it changes formatting and nothing else, with a
comment naming it. Use the full SHA of the commit that made the change rather
than the merge commit, and take it after the merge has landed on `7.x`: a
revision the repository does not have is skipped in silence, so a SHA that is
wrong, or that a squash merge replaced, leaves blame noisy with nothing to say
so. Check an affected line with `git blame` afterwards.

## Running the tests

```sh
composer install
./vendor/bin/phpunit
```

That runs two suites: `unit` (string assertions on the generated SQL) and
`integration` (the generated SQL executed against a real server).

The integration suite always runs its SQLite cases, using an in-memory
database. The MySQL, PostgreSQL and SQL Server cases are skipped unless you
point them at a server with an existing, throwaway database.

The easiest way is to copy the example file and edit it:

```sh
cp .env.example .env
composer db-create
./vendor/bin/phpunit --testsuite integration
```

`.env` is git-ignored, and the PHPUnit bootstrap reads it. Delete or blank
the `DB_*_DSN` of any dialect you do not have a server for, and that dialect
goes back to skipping.

`composer db-create` creates the database named in each configured DSN, for
whichever dialects you set up. It only ever creates: an existing database is
left alone, and nothing is dropped. Run it once, or not at all if you would
rather create them by hand:

```sh
mysql -u root -p -e 'CREATE DATABASE IF NOT EXISTS aura_sqlquery_test'
createdb aura_sqlquery_test
sqlcmd -S 127.0.0.1 -U sa -Q 'CREATE DATABASE aura_sqlquery_test'
```

The test suite deliberately does not create databases itself, so that running
it never needs `CREATE DATABASE` rights — only the one-off script does.

Environment variables work just as well, and win over the file, so a one-off
run needs no edit:

```sh
DB_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=aura_sqlquery_test' \
DB_MYSQL_USER=root DB_MYSQL_PASS=root \
DB_PGSQL_DSN='pgsql:host=127.0.0.1;port=5432;dbname=aura_sqlquery_test' \
DB_PGSQL_USER=postgres DB_PGSQL_PASS=postgres \
DB_SQLSRV_DSN='sqlsrv:Server=127.0.0.1,1433;Database=aura_sqlquery_test;TrustServerCertificate=1' \
DB_SQLSRV_USER=sa DB_SQLSRV_PASS='Aura!Passw0rd' \
./vendor/bin/phpunit --testsuite integration
```

That is how CI passes them, which is why the environment takes precedence.

The SQL Server cases need two pieces, not one: the `pdo_sqlsrv` extension, and
Microsoft's ODBC Driver 18 for SQL Server, which the extension talks through.
Neither is packaged for every platform, and installing the extension alone is
not enough — connecting without the driver fails with
`SQLSTATE[IMSSP]: This extension requires the Microsoft ODBC Driver for SQL
Server`. See Microsoft's [install instructions][odbc]; CI installs
`msodbcsql18` from `packages.microsoft.com` and is the reference environment
for this dialect.

[odbc]: https://learn.microsoft.com/sql/connect/odbc/download-odbc-driver-for-sql-server

## Checking the documentation examples

Every `php` example in `docs/` that is followed by a `sql` block is checked
against the SQL the builder really emits, so the two cannot drift apart:

```sh
php ../scripts/verify-doc-examples.php .
```

The checker is shared between Aura packages and lives outside this repo, in
`scripts/verify-doc-examples.php` alongside the package checkouts; adjust the
path if yours sit elsewhere. What it needs from a package is the adapter at
`tests/doc-examples.php`, which says where the docs are, which query factory an
example expects, and how to turn the result into SQL.

It exits non-zero on the first mismatch and names the file and line, so a
changed clause shows up as a failing example rather than as stale docs. Write
examples as complete statements — the checker evaluates each `php` block on its
own, so a snippet that continues an earlier one has nothing to build.

A test class skips only when its `DB_*_DSN` is unset. Once a DSN is set, the
tests try to connect for real, and anything wrong from there on — a missing
extension, a missing ODBC driver, an unreachable server, a bad password —
surfaces as an error, not a skip. That is deliberate: a configured dialect
that quietly skipped would look like a passing run while testing nothing.

The database itself must already exist; the tests create their own `test_*`
tables in it once per test class and drop them again afterwards, so the
database is left as it was found. Each individual test runs inside a
transaction that is rolled back, so no test data survives either. Even so, do
not point the suite at a database you care about — it drops any table whose
name it wants to use. CI runs the same suite against MySQL, Postgres and SQL
Server service containers.
