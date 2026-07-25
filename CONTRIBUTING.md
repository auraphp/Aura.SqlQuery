# Contributing

We are happy to review any contributions you want to make. When contributing, please follow the rules outlined at <http://auraphp.com/contributing>.

The time between submitting a contribution and its review one may be extensive; do not be discouraged if there is not immediate feedback.

Thanks!

## Running the tests

```
composer install
./vendor/bin/phpunit
```

That runs two suites: `unit` (string assertions on the generated SQL) and
`integration` (the generated SQL executed against a real server).

The integration suite always runs its SQLite cases, using an in-memory
database. The MySQL and PostgreSQL cases are skipped unless you point them at
a server with an existing, throwaway database:

```
DB_MYSQL_DSN='mysql:host=127.0.0.1;port=3306;dbname=aura_sqlquery_test' \
DB_MYSQL_USER=root DB_MYSQL_PASS=root \
DB_PGSQL_DSN='pgsql:host=127.0.0.1;port=5432;dbname=aura_sqlquery_test' \
DB_PGSQL_USER=postgres DB_PGSQL_PASS=postgres \
./vendor/bin/phpunit --testsuite integration
```

The tests drop and recreate their own `test_*` tables in that database, so do
not point them at anything you care about. CI runs the same suite against
MySQL and Postgres service containers.
