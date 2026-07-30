<?php
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

class UpdateTest extends Common\UpdateTest
{
    protected $db_type = 'sqlite';

    protected $expected_sql_with_flag = "
        UPDATE %s <<t1>>
            SET
                <<c1>> = :c1,
                <<c2>> = :c2,
                <<c3>> = :c3,
                <<c4>> = NULL,
                <<c5>> = NOW()
            WHERE
                foo = :foo
                AND baz = :baz
                OR zim = gir
            LIMIT 5
    ";

    public function testOrderLimit()
    {
        $this->query->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->orderBy(['zim DESC', 'baz ASC'])
                    ->limit(5)
                    ->offset(10);

        $actual = $this->query->__toString();
        $expect = "
            UPDATE <<t1>>
            SET
                <<c1>> = :c1,
                <<c2>> = :c2,
                <<c3>> = :c3,
                <<c4>> = NULL,
                <<c5>> = NOW()
            WHERE
                foo = :foo
                AND baz = :baz
                OR zim = gir
            ORDER BY
                zim DESC,
                baz ASC
            LIMIT 5 OFFSET 10
        ";
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testOrAbort()
    {
        $this->query->orAbort()
                    ->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR ABORT');
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testOrFail()
    {
        $this->query->orFail()
                    ->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR FAIL');
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testIgnore()
    {
        $this->query->ignore()
                    ->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR IGNORE');
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testOrIgnore()
    {
        $this->query->orIgnore()
                    ->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR IGNORE');
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testOrReplace()
    {
        $this->query->orReplace()
                    ->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR REPLACE');
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testOrRollback()
    {
        $this->query->orRollback()
                    ->table('t1')
                    ->cols(['c1', 'c2', 'c3'])
                    ->set('c4', null)
                    ->set('c5', 'NOW()')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir')
                    ->limit(5);

        $actual = $this->query->__toString();
        $expect = sprintf($this->expected_sql_with_flag, 'OR ROLLBACK');
        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }

    public function testActual()
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->query("CREATE TABLE test (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(50) NOT NULL
        )");

        $names = [
            'Anna', 'Betty', 'Clara', 'Donna', 'Flora',
            'Gina', 'Hanna', 'Ione', 'Julia', 'Kara',
        ];

        $stm = "INSERT INTO test (name) VALUES (:name)";
        foreach ($names as $name) {
            $sth = $pdo->prepare($stm);
            $sth->execute(['name' => $name]);
        }

        $this->query->table('test')
                    ->cols(['name'])
                    ->where('id = :id', ['id' => 1])
                    ->bindValues(['name' => 'Annabelle']);

        $stm = $this->query->__toString();
        $bind = $this->query->getBindValues();

        $sth = $pdo->prepare($stm);
        $count = $sth->execute($bind);
        $this->assertEquals(1, $count);

        $sth = $pdo->prepare('SELECT * FROM test WHERE id = 1');
        $sth->execute();
        $row = $sth->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Annabelle', $row['name']);
    }

    public function testGetterOnLimitAndOffset()
    {
        $this->query->table('t1');
        $this->query->limit(10);
        $this->query->offset(5);

        $this->assertSame(10, $this->query->getLimit());
        $this->assertSame(5, $this->query->getOffset());
    }

    /**
     * The OR clauses are alternatives to each other, so asking for two of
     * them used to stack both into the statement.
     */
    #[DataProvider('provideConflictClausePair')]
    public function testTwoConflictClauses($first, $second, $message)
    {
        $this->query->$first()
                    ->$second()
                    ->table('t1')
                    ->cols(['c1']);

        $this->expectException('Aura\SqlQuery\Exception\LogicException');
        $this->expectExceptionMessage($message);
        $this->query->__toString();
    }

    public static function provideConflictClausePair()
    {
        return [
            ['orIgnore', 'orReplace', 'OR IGNORE and OR REPLACE'],
            ['ignore', 'orReplace', 'OR IGNORE and OR REPLACE'],
            ['orAbort', 'orFail', 'OR ABORT and OR FAIL'],
            ['orRollback', 'orAbort', 'OR ABORT and OR ROLLBACK'],
        ];
    }
}
