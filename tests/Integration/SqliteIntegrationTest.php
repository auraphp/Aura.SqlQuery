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
            'CREATE TABLE test_isolate (
                id               INTEGER PRIMARY KEY,
                "species.genus"  VARCHAR(50) NOT NULL,
                "species.name"   VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_compound (
                id                INTEGER PRIMARY KEY,
                isolate_id        INTEGER NOT NULL,
                "compound.group"  VARCHAR(50) NOT NULL,
                "compound.name"   VARCHAR(50) NOT NULL
            )',
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS TEXT)";
    }

    protected function inCsv(string $col, string $param): string
    {
        // SQLite has no find_in_set()
        return "instr(',' || {$param} || ',', ',' || {$col} || ',') > 0";
    }

    public function testInsertIgnore()
    {
        $insert = $this->query_factory->newInsert()
            ->ignore()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Duplicate']);

        $this->assertStatementContains('INSERT OR IGNORE INTO <<test_dept>>', $insert);

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

        $this->assertStatementContains('INSERT OR REPLACE INTO <<test_dept>>', $insert);

        $this->exec($insert);

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Replaced', $sth->fetchColumn());
    }

    /**
     * An UPDATE carries a conflict clause too. Moving Sales onto the key
     * Engineering holds would violate the primary key; OR REPLACE resolves
     * it by dropping the row that was in the way.
     */
    public function testUpdateOrReplace()
    {
        // the condition cannot reuse :id -- cols() already binds that name
        // for the SET clause, and the second binding would win
        $update = $this->query_factory->newUpdate()
            ->orReplace()
            ->table('test_dept')
            ->cols(['id' => 1])
            ->where('id = :old_id', ['old_id' => 2]);

        $this->assertStatementContains('UPDATE OR REPLACE <<test_dept>>', $update);

        $this->assertSame(1, $this->exec($update));

        $sth = $this->pdo->query('SELECT id, name FROM test_dept ORDER BY id');
        $this->assertSame(
            [['id' => 1, 'name' => 'Sales']],
            $sth->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
