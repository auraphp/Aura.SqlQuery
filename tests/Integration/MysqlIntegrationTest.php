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
            'CREATE TABLE test_isolate (
                id               INT PRIMARY KEY,
                `species.genus`  VARCHAR(50) NOT NULL,
                `species.name`   VARCHAR(50) NOT NULL
            )',
            'CREATE TABLE test_compound (
                id                INT PRIMARY KEY,
                isolate_id        INT NOT NULL,
                `compound.group`  VARCHAR(50) NOT NULL,
                `compound.name`   VARCHAR(50) NOT NULL
            )',
        ];
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS CHAR)";
    }

    protected function inCsv(string $col, string $param): string
    {
        return "find_in_set({$col}, {$param})";
    }

    public function testSelectSessionVariable()
    {
        // issue #226: quoting the parts of @@session.time_zone made this
        // unrunnable; the server has no 'session' table
        $select = $this->query_factory->newSelect()
            ->cols(['@@session.time_zone AS tz'])
            ->from('test_employee')
            ->where('seq = :seq', ['seq' => 1]);

        $this->assertStatementContains('@@session.time_zone AS <<tz>>', $select);

        $actual = $this->fetchAll($select);
        $this->assertNotSame('', (string) $actual[0]['tz']);
    }

    public function testSelectSessionVariableBesideColumn()
    {
        // the shape from the issue: a real column reference and a session
        // variable in one expression, only the first of which is quoted
        $select = $this->query_factory->newSelect()
            ->cols([
                'test_employee.name',
                'convert_tz(:when, @@session.time_zone, @@session.time_zone) AS converted',
                "convert_tz(:when, '+00:00', '+05:30') AS shifted",
            ])
            ->from('test_employee')
            ->where('test_employee.seq = :seq', [
                'seq' => 1,
                'when' => '2020-01-01 00:00:00',
            ]);

        $this->assertStatementContains(
            'convert_tz(:when, @@session.time_zone, @@session.time_zone) AS <<converted>>',
            $select
        );

        $actual = $this->fetchAll($select);
        $this->assertSame('Anna', $actual[0]['name']);
        // converting from the session zone to itself is a no-op, so this
        // holds whatever the server is set to
        $this->assertSame('2020-01-01 00:00:00', $actual[0]['converted']);
        $this->assertSame('2020-01-01 05:30:00', $actual[0]['shifted']);
    }

    public function testSelectUserVariable()
    {
        // a user variable name may contain dots and '$'; the whole of it is
        // one name, so quoting any part of it is a syntax error
        $this->pdo->exec('SET @var.name = 42, @my$var.name = 7');

        $select = $this->query_factory->newSelect()
            ->cols(['@var.name AS dotted', '@my$var.name AS dollared'])
            ->from('test_employee')
            ->where('seq = :seq', ['seq' => 1]);

        $this->assertStatementContains('@var.name AS <<dotted>>', $select);
        $this->assertStatementContains('@my$var.name AS <<dollared>>', $select);

        $actual = $this->fetchAll($select);
        $this->assertSame(42, (int) $actual[0]['dotted']);
        $this->assertSame(7, (int) $actual[0]['dollared']);
    }

    public function testSelectUserVariableBesideColumn()
    {
        $this->pdo->exec("SET @wanted.name = 'Clara'");

        $select = $this->query_factory->newSelect()
            ->cols(['test_employee.name'])
            ->from('test_employee')
            ->where('test_employee.name = @wanted.name');

        // the column is quoted, the variable beside it is not
        $this->assertStatementContains(
            '<<test_employee>>.<<name>> = @wanted.name',
            $select
        );

        $actual = $this->fetchAll($select);
        $this->assertSame(['Clara'], array_column($actual, 'name'));
    }

    public function testInsertIgnore()
    {
        $insert = $this->query_factory->newInsert()
            ->ignore()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Duplicate']);

        $this->assertStatementContains('INSERT IGNORE INTO <<test_dept>>', $insert);

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

        $this->assertStatementContains('ON DUPLICATE KEY UPDATE', $insert);

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

        $this->assertStatementContains('REPLACE INTO <<test_dept>>', $insert);

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

        $this->assertStatementContains('LIMIT 1', $update);

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

        $this->assertStatementContains('LIMIT 1', $delete);

        $this->assertSame(1, $this->exec($delete));
        $this->assertSame(['Anna', 'Betty', 'Clara'], $this->fetchNames());
    }
}
