<?php
namespace Aura\SqlQuery;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 *
 * Placeholder handling across queries: the names inlineArray() generates,
 * whole-name matching, positional lists, and leaving a query as it was when
 * a change to it fails.
 *
 */
class PlaceholderTest extends TestCase
{
    protected QueryFactory $query_factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->query_factory = new QueryFactory('sqlite');
    }

    /**
     * Runs a query on an in-memory SQLite table t(id, a, b) holding ids 1-5,
     * with a = id and b = id * 10, and returns the ids it selects.
     *
     * @return list<int>
     */
    protected function ids(QueryInterface $query): array
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE t (id INTEGER, a INTEGER, b INTEGER)');
        $pdo->exec('CREATE TABLE u (id INTEGER, a INTEGER, b INTEGER)');
        for ($i = 1; $i <= 5; $i++) {
            $pdo->exec("INSERT INTO t VALUES ($i, $i, " . ($i * 10) . ")");
            $pdo->exec("INSERT INTO u VALUES ($i, $i, " . ($i * 10) . ")");
        }

        $sth = $pdo->prepare($query->getStatement());
        $sth->execute($query->getBindValues());
        return array_map('intval', $sth->fetchAll(PDO::FETCH_COLUMN));
    }

    public function testArraysInOuterQueryAndSubSelectDoNotCollide()
    {
        $sub = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('u')
            ->where('a IN (:a)', ['a' => [2, 3, 4]]);

        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id IN (:id)', ['id' => [1, 2, 3]])
            ->where('id IN (:sub)', ['sub' => $sub])
            ->orderBy(['id']);

        $this->assertSame(
            ['__1__' => 1, '__2__' => 2, '__3__' => 3, '__4__' => 2, '__5__' => 3, '__6__' => 4],
            $select->getBindValues()
        );
        $this->assertSame([2, 3], $this->ids($select));
    }

    public function testUnionOfTwoQueriesWithArrays()
    {
        $a = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id IN (:ids)', ['ids' => [1, 2]]);

        $b = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('u')
            ->where('id IN (:ids)', ['ids' => [4, 5]]);

        $a->union($b);

        $this->assertSame([1, 2, 4, 5], $this->ids($a));
    }

    public function testUnionOfClonesThatEachBindAnArray()
    {
        $base = $this->query_factory->newSelect()->cols(['id'])->from('t');

        $low = clone $base;
        $low->where('id IN (:ids)', ['ids' => [1, 2]]);

        $high = clone $base;
        $high->where('id IN (:ids)', ['ids' => [4, 5]]);

        $low->unionAll($high);

        $this->assertSame([1, 2, 4, 5], $this->ids($low));
    }

    public function testArraysInFromSubSelectAndCte()
    {
        $cte = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('u')
            ->where('id IN (:ids)', ['ids' => [2, 3, 4]]);

        $sub = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id IN (:ids)', ['ids' => [3, 4, 5]]);

        $select = $this->query_factory->newSelect()
            ->with('c', $cte)
            ->cols(['s.id'])
            ->fromSubSelect($sub, 's')
            ->where('s.id IN (SELECT id FROM c)')
            ->where('s.id IN (:ids)', ['ids' => [4, 5]])
            ->orderBy(['s.id']);

        $this->assertSame([4], $this->ids($select));
    }

    public function testNamedPlaceholderIsMatchedAsAWholeName()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a IN (:id) AND b = :id_2', ['id' => [1, 2], 'id_2' => 20]);

        $this->assertStringContainsString(
            'a IN (:__1__, :__2__) AND b = :id_2',
            $select->getStatement()
        );
        $this->assertSame([2], $this->ids($select));
    }

    public function testShorterNameAfterLongerOne()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('b = :idx AND a IN (:id)', ['idx' => 30, 'id' => [3, 4]]);

        $this->assertStringContainsString(
            'b = :idx AND a IN (:__1__, :__2__)',
            $select->getStatement()
        );
        $this->assertSame([3], $this->ids($select));
    }

    public function testSubSelectPlaceholderIsMatchedAsAWholeName()
    {
        $sub = $this->query_factory->newSelect()->cols(['id'])->from('u')->where('a > 3');

        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id IN (:s) AND b > :s2', ['s' => $sub, 's2' => 40]);

        $this->assertStringContainsString('AND b > :s2', $select->getStatement());
        $this->assertSame([5], $this->ids($select));
    }

    public function testPostgresCastIsNotAPlaceholder()
    {
        $select = (new QueryFactory('pgsql'))->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a::int IN (:int)', ['int' => [1, 2]]);

        $this->assertStringContainsString(
            'a::int IN (:__1__, :__2__)',
            $select->getStatement()
        );
    }

    public function testPlaceholderInsideStringLiteralIsLeftAlone()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where("'?' <> ? AND a IN (?)", ['x', [1, 2]]);

        $this->assertStringContainsString(
            "'?' <> :__1__ AND a IN (:__2__, :__3__)",
            $select->getStatement()
        );
        $this->assertSame(['__1__' => 'x', '__2__' => 1, '__3__' => 2], $select->getBindValues());
    }

    public function testTwoPositionalArrays()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a IN (?) AND b IN (?)', [[2, 3], [30, 40]]);

        $this->assertStringContainsString(
            'a IN (:__1__, :__2__) AND b IN (:__3__, :__4__)',
            $select->getStatement()
        );
        $this->assertSame([3], $this->ids($select));
    }

    public function testPositionalArrayAfterScalar()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = ? OR b IN (?)', [1, [40, 50]]);

        $this->assertStringContainsString(
            'a = :__1__ OR b IN (:__2__, :__3__)',
            $select->getStatement()
        );
        $this->assertSame(['__1__' => 1, '__2__' => 40, '__3__' => 50], $select->getBindValues());
        $this->assertSame([1, 4, 5], $this->ids($select));
    }

    public function testPositionalArrayCountsOnlyPositionalValues()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('b = :b OR a IN (?)', ['b' => 10, 0 => [4, 5]]);

        $this->assertStringContainsString(
            'b = :b OR a IN (:__1__, :__2__)',
            $select->getStatement()
        );
        $this->assertSame([1, 4, 5], $this->ids($select));
    }

    public function testSubSelectForAPositionalPlaceholder()
    {
        $sub = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('u')
            ->where('a IN (:a)', ['a' => [2, 3]]);

        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('b IN (?) AND id IN (?)', [[20, 30, 40], $sub])
            ->orderBy(['id']);

        $this->assertStringContainsString(
            'b IN (:__1__, :__2__, :__3__) AND id IN (SELECT',
            $select->getStatement()
        );
        $this->assertSame(
            ['__1__' => 20, '__2__' => 30, '__3__' => 40, '__4__' => 2, '__5__' => 3],
            $select->getBindValues()
        );
        $this->assertSame([2, 3], $this->ids($select));
    }

    public function testPositionalListWithoutAPlaceholderThrows()
    {
        $select = $this->query_factory->newSelect()->cols(['id'])->from('t');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage("1 '?' placeholder(s), but 2 value(s)");
        $select->where('a IN (?)', [[1, 2], [3, 4]]);
    }

    public function testQuestionMarksFromSeparateCallsDoNotCollide()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a > ?', [1])
            ->where('b < ?', [50])
            ->orderBy(['id']);

        $this->assertStringContainsString('a > :__1__', $select->getStatement());
        $this->assertStringContainsString('b < :__2__', $select->getStatement());
        $this->assertSame([2, 3, 4], $this->ids($select));
    }

    public function testQuestionMarksInJoinAndWhereDoNotCollide()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['t.id'])
            ->from('t')
            ->join('INNER', 'u', 'u.id = t.id AND u.a > ?', [2])
            ->where('t.b < ?', [50])
            ->orderBy(['t.id']);

        $this->assertSame([3, 4], $this->ids($select));
    }

    public function testTooFewValuesForQuestionMarksThrows()
    {
        $select = $this->query_factory->newSelect()->cols(['id'])->from('t');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage("2 '?' placeholder(s), but 1 value(s)");
        $select->where('a = ? AND b = ?', [1]);
    }

    public function testConditionWithoutValuesKeepsItsQuestionMarks()
    {
        // nothing given with the condition, so nothing to name: the `?` is
        // left for a value bound by hand, as it always was
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = ?');
        $select->bindValue(0, 3);

        $this->assertStringContainsString('a = ?', $select->getStatement());
        $this->assertSame([0 => 3], $select->getBindValues());
    }

    public function testDoubledQuestionMarkIsNotAPlaceholder()
    {
        $select = (new QueryFactory('pgsql'))->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('data ?? :key AND a = ?', ['key' => 'x', 0 => 1]);

        $this->assertStringContainsString('data ?? :key AND a = :__1__', $select->getStatement());
        $this->assertSame(['key' => 'x', '__1__' => 1], $select->getBindValues());
    }

    public function testRebindingByNumberDoesNotReachAValueGivenWithTheCondition()
    {
        // the value has a generated name now, so binding number 0 by hand
        // adds a value the statement does not use; rebind by name instead
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = ?', [5]);
        $select->bindValue(0, 7);

        $this->assertStringContainsString('a = :__1__', $select->getStatement());
        $this->assertSame(['__1__' => 5, 0 => 7], $select->getBindValues());

        $named = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = :a', ['a' => 5]);
        $named->bindValue('a', 2);
        $this->assertSame([2], $this->ids($named));
    }

    public function testFailedWhereLeavesQueryUnchanged()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = :x', ['x' => 1]);

        $before = [$select->getStatement(), $select->getBindValues()];

        try {
            $select->having('b = :y AND a = :x', ['y' => 10, 'x' => 2]);
            $this->fail('Expected a collision.');
        } catch (Exception\LogicException $e) {
        }

        $this->assertSame($before, [$select->getStatement(), $select->getBindValues()]);

        // and the name the failed call bound first is free again
        $select->where('b = :y', ['y' => 10]);
        $this->assertSame(['x' => 1, 'y' => 10], $select->getBindValues());
    }

    public function testFailedUnionLeavesQueryUnchanged()
    {
        $a = $this->query_factory->newSelect()->cols(['id'])->from('t')->where('a = :x', ['x' => 1]);
        $b = $this->query_factory->newSelect()->cols(['id'])->from('u')->where('a = :x', ['x' => 2]);

        $before = [$a->getStatement(), $a->getBindValues()];

        try {
            $a->union($b);
            $this->fail('Expected a collision.');
        } catch (Exception\LogicException $e) {
        }

        $this->assertSame($before, [$a->getStatement(), $a->getBindValues()]);
        $this->assertSame([1], $this->ids($a));
    }

    public function testFailedFromSubSelectCanBeRetried()
    {
        $select = $this->query_factory->newSelect()->cols(['s.id'])->where('s.a = :x', ['x' => 1]);
        $bad = $this->query_factory->newSelect()->cols(['id', 'a'])->from('t')->where('b = :x', ['x' => 10]);
        $good = $this->query_factory->newSelect()->cols(['id', 'a'])->from('t')->where('b = :y', ['y' => 10]);

        try {
            $select->fromSubSelect($bad, 's');
            $this->fail('Expected a collision.');
        } catch (Exception\LogicException $e) {
        }

        $select->fromSubSelect($good, 's');
        $this->assertSame([1], $this->ids($select));
    }

    public function testFailedColsLeavesQueryUnchanged()
    {
        $update = $this->query_factory->newUpdate()
            ->table('t')
            ->where('id = :b', ['b' => 1]);

        try {
            $update->cols(['a' => 1, 'b' => 2]);
            $this->fail('Expected a collision.');
        } catch (Exception\LogicException $e) {
        }

        $this->assertFalse($update->hasCols());
        $this->assertSame(['b' => 1], $update->getBindValues());
    }

    public function testBulkInsertRowWithExtraColumnThrows()
    {
        // the last row stays open for col() and set() until the statement
        // is built, so that is where its columns are checked
        $insert = $this->query_factory->newInsert()->into('t');
        $insert->addRows([['a' => 1], ['a' => 3, 'b' => 2]]);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Column "b" in row 1 is not in the first row.');
        $insert->getStatement();
    }

    public function testFailedAddRowsLeavesInsertUnchanged()
    {
        $insert = $this->query_factory->newInsert()->into('t')->addRows([['a' => 1], ['a' => 2]]);
        $before = [$insert->getStatement(), $insert->getBindValues()];

        try {
            $insert->addRows([['a' => 3], ['a' => 4, 'b' => 5]]);
            $this->fail('Expected an extra column to throw.');
        } catch (Exception\InvalidArgumentException $e) {
        }

        $this->assertSame($before, [$insert->getStatement(), $insert->getBindValues()]);
    }
}
