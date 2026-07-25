# Contributing

We are happy to review any contributions you want to make. When contributing, please follow the rules outlined at <http://auraphp.com/contributing>.

The time between submitting a contribution and its review one may be extensive; do not be discouraged if there is not immediate feedback.

Thanks!

## Running the tests

```sh
composer install
./vendor/bin/phpunit
```

That runs two suites: `unit` (string assertions on the generated SQL) and
`integration` (the generated SQL executed against a real server).

The integration suite always runs its SQLite cases, using an in-memory
database. The MySQL, PostgreSQL and SQL Server cases are skipped unless you
point them at a server with an existing, throwaway database:

```sh
DB_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=aura_sqlquery_test' \
DB_MYSQL_USER=root DB_MYSQL_PASS=root \
DB_PGSQL_DSN='pgsql:host=127.0.0.1;port=5432;dbname=aura_sqlquery_test' \
DB_PGSQL_USER=postgres DB_PGSQL_PASS=postgres \
DB_SQLSRV_DSN='sqlsrv:Server=127.0.0.1,1433;Database=aura_sqlquery_test;TrustServerCertificate=1' \
DB_SQLSRV_USER=sa DB_SQLSRV_PASS='Aura!Passw0rd' \
./vendor/bin/phpunit --testsuite integration
```

The SQL Server cases need two pieces, not one: the `pdo_sqlsrv` extension, and
Microsoft's ODBC Driver 18 for SQL Server, which the extension talks through.
Neither is packaged for every platform, and installing the extension alone is
not enough — connecting without the driver fails with
`SQLSTATE[IMSSP]: This extension requires the Microsoft ODBC Driver for SQL
Server`. See Microsoft's [install instructions][odbc]; CI installs
`msodbcsql18` from `packages.microsoft.com` and is the reference environment
for this dialect.

[odbc]: https://learn.microsoft.com/sql/connect/odbc/download-odbc-driver-for-sql-server

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
