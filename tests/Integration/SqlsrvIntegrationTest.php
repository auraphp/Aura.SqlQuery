<?php
namespace Aura\SqlQuery\Integration;

use PDO;

/**
 *
 * Runs against a real SQL Server. Set DB_SQLSRV_DSN (plus DB_SQLSRV_USER and
 * DB_SQLSRV_PASS as needed) to enable; the test is skipped when they are
 * absent. CI runs it against a mssql/server service container; there is no
 * SQL Server build for every developer platform, so locally it usually skips.
 *
 */
class SqlsrvIntegrationTest extends AbstractIntegrationTest
{
    protected string $db_type = 'sqlsrv';

    protected function newPdo(): PDO
    {
        $dsn = getenv('DB_SQLSRV_DSN');
        if ($dsn === false || $dsn === '') {
            $this->markTestSkipped('DB_SQLSRV_DSN is not set.');
        }

        $user = getenv('DB_SQLSRV_USER') ?: null;
        $pass = getenv('DB_SQLSRV_PASS') ?: null;

        return new PDO($dsn, $user, $pass);
    }

    protected function getCreateTables(): array
    {
        return [
            'CREATE TABLE test_dept (
                id   INT PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_employee (
                id      INT IDENTITY(1,1) PRIMARY KEY,
                name    VARCHAR(50) NOT NULL,
                dept_id INT NULL,
                salary  INT NOT NULL,
                seq     INT NOT NULL
            )',
            "CREATE TABLE test_defaults (
                id   INT IDENTITY(1,1) PRIMARY KEY,
                name VARCHAR(50) NOT NULL DEFAULT 'anon'
            )",
            'CREATE TABLE test_isolate (
                id               INT PRIMARY KEY,
                [species.genus]  VARCHAR(50) NOT NULL,
                [species.name]   VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_compound (
                id                INT PRIMARY KEY,
                isolate_id        INT NOT NULL,
                [compound.group]  VARCHAR(50) NOT NULL,
                [compound.name]   VARCHAR(50) NOT NULL
            )',
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS VARCHAR(50))";
    }

    protected function inCsv(string $col, string $param): string
    {
        // SQL Server has no find_in_set(); STRING_SPLIT() is the equivalent,
        // and takes the string to split as a parameter
        return "{$col} IN (SELECT value FROM STRING_SPLIT({$param}, ','))";
    }
}
