<?php
namespace Aura\SqlQuery\Integration;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

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
            // InnoDB explicitly: test_dept_ref puts a foreign key on this
            // table, and both ends have to be InnoDB for that to work
            'CREATE TABLE test_dept (
                id   INT PRIMARY KEY,
                name VARCHAR(50) NOT NULL
            ) ENGINE=InnoDB',
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
            // a restricting foreign key, so that DELETE IGNORE has an error
            // to swallow; created last because it references test_dept
            'CREATE TABLE test_dept_ref (
                id      INT PRIMARY KEY,
                dept_id INT NOT NULL,
                CONSTRAINT test_dept_ref_fk
                    FOREIGN KEY (dept_id) REFERENCES test_dept (id)
            ) ENGINE=InnoDB',
        ];
    }

    /**
     * The referencing table has to be dropped before the table it points at,
     * so it goes first.
     *
     * @return string[]
     */
    protected static function getTableNames(): array
    {
        return array_merge(['test_dept_ref'], parent::getTableNames());
    }

    protected function castToChar(string $expr): string
    {
        return "CAST({$expr} AS CHAR)";
    }

    protected function inCsv(string $col, string $param): string
    {
        return "find_in_set({$col}, {$param})";
    }

    /**
     * LATERAL arrived in MySQL 8.0.14; MariaDB has no equivalent at all, and
     * shares this 'mysql' db_type.
     */
    protected function skipUnlessLateralSupported(): void
    {
        $version = $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (stripos($version, 'mariadb') !== false) {
            $this->markTestSkipped("MariaDB does not support LATERAL ($version).");
        }

        if (version_compare($version, '8.0.14', '<')) {
            $this->markTestSkipped("MySQL $version is older than 8.0.14.");
        }
    }

    public function testLateralJoinSubSelect()
    {
        $this->skipUnlessLateralSupported();

        // the top earner in each department
        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'left',
                'SELECT name, salary FROM test_employee
                 WHERE test_employee.dept_id = test_dept.id
                 ORDER BY salary DESC LIMIT 1',
                'top',
                'true'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('LEFT JOIN LATERAL', $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(
            ['Betty', 'Donna'],
            array_column($rows, 'employee_name')
        );
    }

    /**
     * MySQL rejects a LEFT JOIN LATERAL with no ON clause, so "ON true" has to
     * be supplied.
     */
    public function testLateralJoinSubSelect_noCondition()
    {
        $this->skipUnlessLateralSupported();

        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'left',
                'SELECT name FROM test_employee
                 WHERE test_employee.dept_id = test_dept.id
                 ORDER BY salary DESC LIMIT 1',
                'top'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('ON true', $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(
            ['Betty', 'Donna'],
            array_column($rows, 'employee_name')
        );
    }

    public function testLateralJoinSubSelect_cross()
    {
        $this->skipUnlessLateralSupported();

        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'cross',
                'SELECT name FROM test_employee
                 WHERE test_employee.dept_id = test_dept.id
                 ORDER BY salary DESC LIMIT 1',
                'top'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('CROSS JOIN LATERAL', $select);
        $this->assertStringNotContainsString('ON true', (string) $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(
            ['Betty', 'Donna'],
            array_column($rows, 'employee_name')
        );
    }

    /**
     * MySQL accepts an ON clause on a CROSS join, where Postgres rejects it.
     * Mysql\Select relaxes joinForbidsCondition() for exactly this, so prove
     * the relaxed case really runs.
     */
    public function testLateralJoinSubSelect_crossWithCondition()
    {
        $this->skipUnlessLateralSupported();

        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'top.name AS employee_name'])
            ->from('test_dept')
            ->lateralJoinSubSelect(
                'cross',
                'SELECT name, dept_id FROM test_employee
                 ORDER BY salary DESC LIMIT 1',
                'top',
                'top.dept_id = test_dept.id'
            )
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains('CROSS JOIN LATERAL', $select);

        $rows = $this->fetchAll($select);
        $this->assertSame(['Donna'], array_column($rows, 'employee_name'));
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

    /**
     * REPLACE accepts LOW_PRIORITY and DELAYED, and nothing else; the flags
     * MySQL rejects cannot be built at all, so they are covered by the unit
     * tests instead. DELAYED is obsolete but still parses -- the server
     * converts it and warns.
     */
    #[DataProvider('provideReplaceFlagAllowed')]
    public function testInsertOrReplaceWithFlag($method, $flag)
    {
        $insert = $this->query_factory->newInsert()
            ->orReplace()
            ->$method()
            ->into('test_dept')
            ->cols(['id' => 1, 'name' => 'Replaced']);

        $this->assertStatementContains("REPLACE {$flag} INTO <<test_dept>>", $insert);

        // two rows affected: the old one deleted, the new one inserted
        $this->assertSame(2, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_dept WHERE id = 1');
        $this->assertSame('Replaced', $sth->fetchColumn());
    }

    public static function provideReplaceFlagAllowed(): array
    {
        return [
            ['lowPriority', 'LOW_PRIORITY'],
            ['delayed', 'DELAYED'],
        ];
    }

    public function testUpdateIgnore()
    {
        // moving Sales onto the primary key Engineering already holds. The
        // condition cannot reuse :id -- cols() already binds that name for
        // the SET clause, and the second binding would win.
        $update = $this->query_factory->newUpdate()
            ->table('test_dept')
            ->cols(['id' => 1])
            ->where('id = :old_id', ['old_id' => 2]);

        // without the flag the collision is an error
        try {
            $this->exec($update);
            $this->fail('Expected a duplicate-key error.');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
        }

        // with it, the offending row is skipped instead
        $update->ignore();
        $this->assertStatementContains('UPDATE IGNORE <<test_dept>>', $update);
        $this->assertSame(0, $this->exec($update));

        $sth = $this->pdo->query('SELECT id, name FROM test_dept ORDER BY id');
        $rows = $sth->fetchAll(PDO::FETCH_ASSOC);
        $this->assertSame([1, 2], array_map('intval', array_column($rows, 'id')));
        $this->assertSame(['Engineering', 'Sales'], array_column($rows, 'name'));
    }

    public function testDeleteIgnore()
    {
        // a child row pointing at Engineering, so the delete below violates
        // the restricting foreign key
        $this->pdo->exec('INSERT INTO test_dept_ref (id, dept_id) VALUES (1, 1)');

        $delete = $this->query_factory->newDelete()
            ->from('test_dept')
            ->where('id = :id', ['id' => 1]);

        // without the flag the constraint is an error
        try {
            $this->exec($delete);
            $this->fail('Expected a foreign key violation.');
        } catch (\PDOException $e) {
            $this->assertStringContainsString('foreign key constraint fails', $e->getMessage());
        }

        // with it, the same violation is downgraded to a warning and the
        // parent row is left in place
        $delete->ignore();
        $this->assertStatementContains('DELETE IGNORE FROM <<test_dept>>', $delete);
        $this->assertSame(0, $this->exec($delete));

        $sth = $this->pdo->query('SELECT id FROM test_dept ORDER BY id');
        $this->assertSame([1, 2], array_map('intval', $sth->fetchAll(PDO::FETCH_COLUMN)));

        // and a delete that violates nothing still removes its row
        $unblocked = $this->query_factory->newDelete()
            ->ignore()
            ->from('test_dept')
            ->where('id = :id', ['id' => 2]);
        $this->assertSame(1, $this->exec($unblocked));
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
