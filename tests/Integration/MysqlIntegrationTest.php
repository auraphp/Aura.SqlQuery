<?php
namespace Aura\SqlQuery\Integration;

use PDO;

/**
 *
 * Runs against a real MySQL/MariaDB server. Set DB_MYSQL_DSN (plus
 * DB_MYSQL_USER and DB_MYSQL_PASS as needed) to enable; the test is skipped
 * when they are absent.
 *
 */
class MysqlIntegrationTest extends AbstractIntegrationTest
{
    protected string $db_type = 'mysql';

    protected function newPdo(): PDO
    {
        $dsn = getenv('DB_MYSQL_DSN');
        if ($dsn === false || $dsn === '') {
            $this->markTestSkipped('DB_MYSQL_DSN is not set.');
        }

        $user = getenv('DB_MYSQL_USER') ?: null;
        $pass = getenv('DB_MYSQL_PASS') ?: null;

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
                id      INT AUTO_INCREMENT PRIMARY KEY,
                name    VARCHAR(50) NOT NULL,
                dept_id INT NULL,
                salary  INT NOT NULL,
                seq     INT NOT NULL
            )',
            "CREATE TABLE test_defaults (
                id   INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL DEFAULT 'anon'
            )",
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS CHAR)";
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

    public function testInsertOnDuplicateKeyUpdate()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Ignored'])
            ->onDuplicateKeyUpdateCol('name', 'Updated');

        $this->exec($insert);

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Updated', $sth->fetchColumn());
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

    public function testUpdateOrderByLimit()
    {
        $update = $this->query_factory->newUpdate()
            ->table('test_employee')
            ->cols(['salary' => 1])
            ->where('salary > :min', ['min' => 0])
            ->orderBy(['salary DESC'])
            ->limit(1);

        $this->assertSame(1, $this->exec($update));

        $sth = $this->pdo->query('SELECT salary FROM test_employee WHERE seq = 4');
        $this->assertSame(1, (int) $sth->fetchColumn());
    }

    public function testDeleteOrderByLimit()
    {
        $delete = $this->query_factory->newDelete()
            ->from('test_employee')
            ->where('salary > :min', ['min' => 0])
            ->orderBy(['salary DESC'])
            ->limit(1);

        $this->assertSame(1, $this->exec($delete));
        $this->assertSame(['Anna', 'Betty', 'Clara'], $this->fetchNames());
    }
}
