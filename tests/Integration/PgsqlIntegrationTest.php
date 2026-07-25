<?php
namespace Aura\SqlQuery\Integration;

use PDO;

/**
 *
 * Runs against a real PostgreSQL server. Set DB_PGSQL_DSN (plus DB_PGSQL_USER
 * and DB_PGSQL_PASS as needed) to enable; the test is skipped when they are
 * absent.
 *
 */
class PgsqlIntegrationTest extends AbstractIntegrationTest
{
    protected string $db_type = 'pgsql';

    protected function newPdo(): PDO
    {
        $dsn = getenv('DB_PGSQL_DSN');
        if ($dsn === false || $dsn === '') {
            $this->markTestSkipped('DB_PGSQL_DSN is not set.');
        }

        $user = getenv('DB_PGSQL_USER') ?: null;
        $pass = getenv('DB_PGSQL_PASS') ?: null;

        return new PDO($dsn, $user, $pass);
    }

    protected function getCreateTables(): array
    {
        return [
            'CREATE TABLE test_dept (
                id   INTEGER PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_employee (
                id      SERIAL PRIMARY KEY,
                name    VARCHAR(50) NOT NULL,
                dept_id INTEGER NULL,
                salary  INTEGER NOT NULL,
                seq     INTEGER NOT NULL
            )',
            "CREATE TABLE test_defaults (
                id   SERIAL PRIMARY KEY,
                name VARCHAR(50) NOT NULL DEFAULT 'anon'
            )",
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS VARCHAR)";
    }

    public function testInsertReturning()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols(['name' => 'Edna', 'dept_id' => 1, 'salary' => 500, 'seq' => 5])
            ->returning(['id', 'name']);

        $row = $this->fetchAll($insert)[0];
        $this->assertSame('Edna', $row['name']);
        $this->assertGreaterThan(0, (int) $row['id']);
    }

    public function testUpdateReturning()
    {
        $update = $this->query_factory->newUpdate()
            ->table('test_employee')
            ->cols(['salary' => 999])
            ->where('seq = :seq', ['seq' => 1])
            ->returning(['name', 'salary']);

        $rows = $this->fetchAll($update);
        $this->assertSame('Anna', $rows[0]['name']);
        $this->assertSame(999, (int) $rows[0]['salary']);
    }

    public function testDeleteReturning()
    {
        $delete = $this->query_factory->newDelete()
            ->from('test_employee')
            ->where('salary >= :min', ['min' => 300])
            ->returning(['name']);

        $rows = $this->fetchAll($delete);
        $names = array_column($rows, 'name');
        sort($names);
        $this->assertSame(['Clara', 'Donna'], $names);
    }

    public function testGetLastInsertIdName()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols(['name' => 'Edna', 'dept_id' => 1, 'salary' => 500, 'seq' => 5]);

        $this->exec($insert);

        $name = $insert->getLastInsertIdName('id');
        $this->assertSame('test_employee_id_seq', $name);
        $this->assertGreaterThan(0, (int) $this->pdo->lastInsertId($name));
    }
}
