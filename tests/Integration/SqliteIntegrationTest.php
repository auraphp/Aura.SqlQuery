<?php
namespace Aura\SqlQuery\Integration;

use PDO;

class SqliteIntegrationTest extends AbstractIntegrationTest
{
    protected string $db_type = 'sqlite';

    protected function newPdo(): PDO
    {
        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is not available.');
        }
        return new PDO('sqlite::memory:');
    }

    protected function getCreateTables(): array
    {
        return [
            'CREATE TABLE test_dept (
                id   INTEGER PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_employee (
                id      INTEGER PRIMARY KEY AUTOINCREMENT,
                name    VARCHAR(50) NOT NULL,
                dept_id INTEGER NULL,
                salary  INTEGER NOT NULL,
                seq     INTEGER NOT NULL
            )',
            "CREATE TABLE test_defaults (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL DEFAULT 'anon'
            )",
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS TEXT)";
    }

    public function testInsertIgnore()
    {
        $insert = $this->query_factory->newInsert()
            ->ignore()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Duplicate']);

        $this->assertSame(0, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Engineering', $sth->fetchColumn());
    }

    public function testInsertOrReplace()
    {
        $insert = $this->query_factory->newInsert()
            ->orReplace()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Replaced']);

        $this->exec($insert);

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Replaced', $sth->fetchColumn());
    }
}
