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
database. The MySQL and PostgreSQL cases are skipped unless you point them at
a server with an existing, throwaway database:

```sh
DB_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=aura_sqlquery_test' \
DB_MYSQL_USER=root DB_MYSQL_PASS=root \
DB_PGSQL_DSN='pgsql:host=127.0.0.1;port=5432;dbname=aura_sqlquery_test' \
DB_PGSQL_USER=postgres DB_PGSQL_PASS=postgres \
./vendor/bin/phpunit --testsuite integration
```

The database itself must already exist; the tests create their own `test_*`
tables in it once per test class and drop them again afterwards, so the
database is left as it was found. Each individual test runs inside a
transaction that is rolled back, so no test data survives either. Even so, do
not point the suite at a database you care about — it drops any table whose
name it wants to use. CI runs the same suite against MySQL and Postgres
service containers.
