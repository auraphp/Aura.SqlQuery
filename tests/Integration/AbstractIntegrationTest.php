<?php
namespace Aura\SqlQuery\Integration;

use Aura\SqlQuery\QueryFactory;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 *
 * Runs the SQL this library generates against a real database server, instead
 * of only asserting on the generated string. Each test pins the shape of the
 * SQL and then executes it, so that a dialect which renders
 * valid-looking-but-unexecutable SQL fails, and so does one that runs but
 * quotes the wrong thing. Subclasses provide the connection and the
 * dialect-specific DDL; the test methods here are shared by all of them.
 *
 */
abstract class AbstractIntegrationTest extends TestCase
{
    protected string $db_type;

    protected PDO $pdo;

    protected QueryFactory $query_factory;

    /**
     * One connection per test class, keyed by class name so that each dialect
     * keeps its own. The schema is built once on that connection and every
     * test runs inside a transaction that is rolled back afterwards.
     *
     * @var array<string, PDO>
     */
    private static array $connections = [];

    /**
     * Returns a connection, or skips the test when the server is unavailable.
     */
    abstract protected function newPdo(): PDO;

    /**
     * Returns the CREATE TABLE statements for this dialect.
     *
     * @return string[]
     */
    abstract protected function getCreateTables(): array;

    /**
     * Returns an expression casting $expr to a string type in this dialect.
     */
    abstract protected function castToChar(string $expr): string;

    /**
     * Returns an expression that is true when $col is one of the values in
     * the comma-separated string bound to $param; MySQL spells this
     * find_in_set(), the others have no such function.
     */
    abstract protected function inCsv(string $col, string $param): string;

    /**
     * Returns the pagination clause this dialect renders for a query limited
     * to 2 rows starting at offset 1. Most dialects spell it LIMIT/OFFSET;
     * SQL Server has no LIMIT and overrides this.
     */
    protected function getLimitOffsetSql(): string
    {
        return 'LIMIT 2 OFFSET 1';
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->getConnection();
        $this->query_factory = new QueryFactory($this->db_type);

        // seed inside the transaction, so the rollback in tearDown() undoes
        // the seed rows along with whatever the test itself wrote
        $this->pdo->beginTransaction();
        $this->seed();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    /**
     * Drops the tables this class created, leaving the database as it was
     * found. The schema cannot live inside the per-test transaction: MySQL
     * implicitly commits DDL, so a rollback would not undo it.
     */
    public static function tearDownAfterClass(): void
    {
        $pdo = self::$connections[static::class] ?? null;
        if ($pdo !== null) {
            static::dropTables($pdo);
            unset(self::$connections[static::class]);
        }
        parent::tearDownAfterClass();
    }

    /**
     * Returns this class's connection, building the schema on first use.
     */
    protected function getConnection(): PDO
    {
        if (isset(self::$connections[static::class])) {
            return self::$connections[static::class];
        }

        $pdo = $this->newPdo();
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // a previous run may have died before its teardown
        static::dropTables($pdo);
        foreach ($this->getCreateTables() as $stm) {
            $pdo->exec($stm);
        }

        self::$connections[static::class] = $pdo;
        return $pdo;
    }

    /**
     * @return string[]
     */
    protected static function getTableNames(): array
    {
        return [
            'test_employee',
            'test_dept',
            'test_defaults',
            'test_compound',
            'test_isolate',
        ];
    }

    protected static function dropTables(PDO $pdo): void
    {
        foreach (static::getTableNames() as $table) {
            $pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }

    protected function seed(): void
    {
        $depts = [
            [1, 'Engineering'],
            [2, 'Sales'],
        ];
        $stm = $this->pdo->prepare(
            'INSERT INTO test_dept (id, name) VALUES (?, ?)'
        );
        foreach ($depts as $dept) {
            $stm->execute($dept);
        }

        $employees = [
            ['Anna', 1, 100, 1],
            ['Betty', 1, 200, 2],
            ['Clara', 2, 300, 3],
            ['Donna', 2, 400, 4],
        ];
        $stm = $this->pdo->prepare(
            'INSERT INTO test_employee (name, dept_id, salary, seq) VALUES (?, ?, ?, ?)'
        );
        foreach ($employees as $employee) {
            $stm->execute($employee);
        }

        $this->seedIssue183();
    }

    /**
     * Seeds the two tables from issue #183, whose column names contain dots.
     */
    protected function seedIssue183(): void
    {
        $isolates = [
            [1, 'Candida', 'albicans'],
            [2, 'Aspergillus', 'niger'],
        ];
        $stm = $this->pdo->prepare(
            'INSERT INTO test_isolate (id, ' . $this->quoteName('species.genus')
            . ', ' . $this->quoteName('species.name') . ') VALUES (?, ?, ?)'
        );
        foreach ($isolates as $isolate) {
            $stm->execute($isolate);
        }

        $compounds = [
            // matches: an azole, on a Candida isolate
            [1, 1, 'azole', 'fluconazole'],
            // the compound half matches, the isolate half does not
            [2, 2, 'echinocandin', 'caspofungin'],
            // matches only if the parentheses are missing
            [3, 2, 'azole', 'fluconazole'],
        ];
        $stm = $this->pdo->prepare(
            'INSERT INTO test_compound (id, isolate_id, '
            . $this->quoteName('compound.group') . ', '
            . $this->quoteName('compound.name') . ') VALUES (?, ?, ?, ?)'
        );
        foreach ($compounds as $compound) {
            $stm->execute($compound);
        }
    }

    /**
     * Quotes a single identifier the way this dialect does, for the raw SQL
     * in the seeds; the query objects do their own quoting.
     */
    protected function quoteName(string $name): string
    {
        $query = $this->query_factory->newSelect();
        return $query->getQuoteNamePrefix() . $name . $query->getQuoteNameSuffix();
    }

    /**
     * Prepares and executes a query object.
     */
    protected function perform($query): \PDOStatement
    {
        $sth = $this->pdo->prepare($query->getStatement());
        foreach ($query->getBindValues() as $name => $value) {
            // bind with an explicit type the way a connection library would;
            // SQLite in particular will not compare an integer expression
            // against a string-bound value
            $sth->bindValue($name, $value, $this->getParamType($value));
        }
        $sth->execute();
        return $sth;
    }

    protected function getParamType($value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            $value === null => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Asserts that the generated SQL contains $expect, in which '<<' and
     * '>>' stand for this dialect's identifier quotes. Executing a query
     * proves it runs and returns the right rows; this pins the shape of the
     * SQL that did so, which the rows alone cannot show.
     */
    protected function assertStatementContains(string $expect, $query): void
    {
        $expect = str_replace(
            ['<<', '>>'],
            [$query->getQuoteNamePrefix(), $query->getQuoteNameSuffix()],
            $expect
        );
        $this->assertStringContainsString($expect, (string) $query->getStatement());
    }

    /**
     * Executes a query object and returns all rows.
     */
    protected function fetchAll($query): array
    {
        return $this->perform($query)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executes a query object and returns the affected row count.
     */
    protected function exec($query): int
    {
        return $this->perform($query)->rowCount();
    }

    protected function fetchNames(?string $where = null): array
    {
        $stm = 'SELECT name FROM test_employee';
        if ($where !== null) {
            $stm .= " WHERE {$where}";
        }
        $stm .= ' ORDER BY seq';
        return $this->pdo->query($stm)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function testSelectWhereOrderLimitOffset()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['name', 'salary'])
            ->from('test_employee')
            ->where('salary > :min', ['min' => 100])
            ->orderBy(['salary DESC'])
            ->limit(2)
            ->offset(1);

        $this->assertStatementContains($this->getLimitOffsetSql(), $select);

        $actual = $this->fetchAll($select);
        $this->assertSame(['Clara', 'Betty'], array_column($actual, 'name'));
    }

    public function testSelectJoinGroupByHaving()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['test_dept.name AS dept_name', 'COUNT(*) AS employee_count'])
            ->from('test_dept')
            ->innerJoin('test_employee', 'test_employee.dept_id = test_dept.id')
            ->groupBy(['test_dept.name'])
            ->having('COUNT(*) > :least', ['least' => 1])
            ->orderBy(['test_dept.name']);

        $this->assertStatementContains(
            'INNER JOIN <<test_employee>> ON <<test_employee>>.<<dept_id>> = <<test_dept>>.<<id>>',
            $select
        );

        $actual = $this->fetchAll($select);
        $this->assertSame(
            ['Engineering', 'Sales'],
            array_column($actual, 'dept_name')
        );
        $this->assertSame([2, 2], array_map('intval', array_column($actual, 'employee_count')));
    }

    public function testSelectWhereGroupedConditions()
    {
        // issue #183: closure grouping must emit real parentheses, so that
        // (a OR b) AND (c OR d) binds tighter than the flat a OR b AND c OR d
        $select = $this->query_factory->newSelect()
            ->cols(['name'])
            ->from('test_employee')
            ->where(function ($select) {
                $select->where('dept_id = :dept_id', ['dept_id' => 1])
                    ->orWhere('salary = :salary', ['salary' => 400]);
            })
            ->where(function ($select) {
                $select->where('name = :first', ['first' => 'Anna'])
                    ->orWhere('name = :second', ['second' => 'Donna']);
            })
            ->orderBy(['seq']);

        $this->assertStatementContains('AND (', $select);

        // without the parentheses this would also match Betty
        $actual = $this->fetchAll($select);
        $this->assertSame(['Anna', 'Donna'], array_column($actual, 'name'));
    }

    public function testSelectIssue183ReportedQuery()
    {
        // issue #183, as reported: two OR-groups combined with AND, over
        // column names that contain a dot and so are quoted by hand
        $group = 'test_compound.' . $this->quoteName('compound.group');
        $name = 'test_compound.' . $this->quoteName('compound.name');
        $genus = 'test_isolate.' . $this->quoteName('species.genus');
        $species = 'test_isolate.' . $this->quoteName('species.name');

        $select = $this->query_factory->newSelect()
            ->cols(['test_compound.id'])
            ->from('test_compound')
            ->innerJoin('test_isolate', 'test_isolate.id = test_compound.isolate_id')
            ->where(function ($select) use ($group, $name) {
                $select
                    ->where($this->inCsv($group, ':compound_groups'), ['compound_groups' => 'azole'])
                    ->orWhere($this->inCsv($name, ':compound_names'), ['compound_names' => 'caspofungin']);
            })
            ->where(function ($select) use ($genus, $species) {
                $select
                    ->where($this->inCsv($genus, ':species_genera'), ['species_genera' => 'Candida'])
                    ->orWhere($this->inCsv($species, ':species_names'), ['species_names' => 'albicans']);
            })
            ->orderBy(['test_compound.id']);

        // the hand-quoted names survive, and the two groups are parenthesized
        $this->assertStatementContains('<<compound.group>>', $select);
        $this->assertStatementContains('<<species.genus>>', $select);
        $this->assertStatementContains('AND (', $select);

        // row 3 satisfies the first group only, so it comes back too if the
        // parentheses are lost to AND binding tighter than OR
        $actual = $this->fetchAll($select);
        $this->assertSame([1], array_map('intval', array_column($actual, 'id')));
    }

    public function testSelectSubSelectInWhere()
    {
        $sub = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('test_dept')
            ->where('name = :dept_name', ['dept_name' => 'Sales']);

        $select = $this->query_factory->newSelect()
            ->cols(['name'])
            ->from('test_employee')
            ->where('dept_id IN (' . $sub->getStatement() . ')', $sub->getBindValues())
            ->orderBy(['seq']);

        $this->assertStatementContains('dept_id IN (SELECT', $select);

        $actual = $this->fetchAll($select);
        $this->assertSame(['Clara', 'Donna'], array_column($actual, 'name'));
    }

    public function testSelectFromSubSelect()
    {
        $sub = $this->query_factory->newSelect()
            ->cols(['name', 'salary'])
            ->from('test_employee')
            ->where('salary >= :min', ['min' => 300]);

        $select = $this->query_factory->newSelect()
            ->cols(['name'])
            ->fromSubSelect($sub, 'well_paid')
            ->orderBy(['name']);

        $this->assertStatementContains(') AS <<well_paid>>', $select);

        $actual = $this->fetchAll($select);
        $this->assertSame(['Clara', 'Donna'], array_column($actual, 'name'));
    }

    public function testSelectDistinctAndUnion()
    {
        $select = $this->query_factory->newSelect()
            ->distinct()
            ->cols(['dept_id'])
            ->from('test_employee')
            ->where('salary < :max', ['max' => 300])
            ->union()
            ->cols(['dept_id'])
            ->from('test_employee')
            ->where('salary > :min', ['min' => 300]);

        $this->assertStatementContains('SELECT DISTINCT', $select);
        $this->assertStatementContains('UNION', $select);

        $actual = $this->fetchAll($select);
        $dept_ids = array_map('intval', array_column($actual, 'dept_id'));
        sort($dept_ids);
        $this->assertSame([1, 2], $dept_ids);
    }

    public function testSelectCastExpression()
    {
        // regression for #157: the quoter must not mangle a CAST() type name
        $select = $this->query_factory->newSelect()
            ->cols([$this->castToChar('test_employee.salary') . ' AS salary_text'])
            ->from('test_employee')
            ->where('name = :name', ['name' => 'Anna']);

        // the CAST type name must not be quoted as an identifier
        $this->assertStatementContains(
            $this->castToChar('<<test_employee>>.<<salary>>') . ' AS <<salary_text>>',
            $select
        );

        $actual = $this->fetchAll($select);
        $this->assertSame('100', (string) $actual[0]['salary_text']);
    }

    public function testSelectCountDistinct()
    {
        // issue #226: a space inside parentheses is not an alias separator.
        // 'COUNT(DISTINCT x)' used to render as 'COUNT(DISTINCT AS x)',
        // which is a syntax error on every dialect, so this only passes if
        // the statement actually executes.
        $select = $this->query_factory->newSelect()
            ->cols([
                'COUNT(DISTINCT test_employee.dept_id)',
                // a balanced call is still a column name, so the two-word
                // form keeps working as an alias
                'COUNT(*) row_count',
            ])
            ->from('test_employee');

        $this->assertStatementContains(
            'COUNT(DISTINCT <<test_employee>>.<<dept_id>>)',
            $select
        );
        $this->assertStatementContains('COUNT(*) AS <<row_count>>', $select);

        $row = $this->fetchAll($select)[0];
        $this->assertSame(2, (int) array_values($row)[0]);
        $this->assertSame(4, (int) $row['row_count']);
    }

    public function testInsert()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols([
                'name' => 'Edna',
                'dept_id' => 1,
                'salary' => 500,
                'seq' => 5,
            ]);

        $this->assertStatementContains('INSERT INTO <<test_employee>>', $insert);
        $this->assertStatementContains('<<name>>', $insert);

        $this->assertSame(1, $this->exec($insert));
        $this->assertSame('Edna', $this->fetchNames('seq = 5')[0]);
    }

    public function testInsertBulkRows()
    {
        $insert = $this->query_factory->newInsert();
        $insert->into('test_employee')
            ->addRows([
                ['name' => 'Edna', 'dept_id' => 1, 'salary' => 500, 'seq' => 5],
                ['name' => 'Fiona', 'dept_id' => 2, 'salary' => 600, 'seq' => 6],
            ]);

        $this->assertStatementContains(
            '(:name_0, :dept_id_0, :salary_0, :seq_0),',
            $insert
        );

        $this->assertSame(2, $this->exec($insert));
        $this->assertSame(
            ['Anna', 'Betty', 'Clara', 'Donna', 'Edna', 'Fiona'],
            $this->fetchNames()
        );
    }

    public function testInsertNullValue()
    {
        $insert = $this->query_factory->newInsert()
            ->into('test_employee')
            ->cols([
                'name' => 'Edna',
                'dept_id' => null,
                'salary' => 500,
                'seq' => 5,
            ]);

        $this->assertStatementContains('<<dept_id>>', $insert);

        $this->exec($insert);

        $sth = $this->pdo->query('SELECT dept_id FROM test_employee WHERE seq = 5');
        $this->assertNull($sth->fetchColumn());
    }

    public function testInsertDefaultValues()
    {
        // regression for #149: an insert with no columns must still be valid
        $insert = $this->query_factory->newInsert()->into('test_defaults');
        $this->assertStatementContains('INSERT INTO <<test_defaults>>', $insert);
        $this->assertSame(1, $this->exec($insert));

        $sth = $this->pdo->query('SELECT name FROM test_defaults');
        $this->assertSame('anon', $sth->fetchColumn());
    }

    public function testUpdate()
    {
        $update = $this->query_factory->newUpdate()
            ->table('test_employee')
            ->cols(['salary' => 999])
            ->set('name', 'test_employee.name')
            ->where('seq = :seq', ['seq' => 1]);

        // set() takes a raw expression, so the column reference is quoted
        $this->assertStatementContains(
            '<<name>> = <<test_employee>>.<<name>>',
            $update
        );

        $this->assertSame(1, $this->exec($update));

        $sth = $this->pdo->query('SELECT name, salary FROM test_employee WHERE seq = 1');
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('Anna', $row['name']);
        $this->assertSame(999, (int) $row['salary']);
    }

    public function testDelete()
    {
        $delete = $this->query_factory->newDelete()
            ->from('test_employee')
            ->where('salary >= :min', ['min' => 300]);

        $this->assertStatementContains('DELETE FROM <<test_employee>>', $delete);

        $this->assertSame(2, $this->exec($delete));
        $this->assertSame(['Anna', 'Betty'], $this->fetchNames());
    }
}
