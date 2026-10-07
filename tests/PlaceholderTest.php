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

    /**
     * The statement on one line, for asserting on parts that wrap.
     */
    protected function flat(QueryInterface $query): string
    {
        return (string) preg_replace('/\s+/', ' ', $query->getStatement());
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

    public function testQuestionMarkBesideOnlyNamedValuesIsKeptForBindingByHand()
    {
        // as in 3.x: no value was given for the `?`, so it is left for one
        // bound by hand. Plain PDO cannot run it beside a named placeholder;
        // Aura.Sql's ExtendedPdo, which rewrites the placeholders, can.
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = :a AND b = ?', ['a' => 1]);
        $select->bindValue(0, 10);

        $this->assertStringContainsString('a = :a AND b = ?', $this->flat($select));
        $this->assertSame(['a' => 1, 0 => 10], $select->getBindValues());
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

    /**
     * Issue #276: a `$` is an identifier character on PostgreSQL, so `$$`
     * inside a name opens no dollar-quoted string.
     */
    public function testDollarsInAPostgresIdentifierOpenNoString()
    {
        $select = (new QueryFactory('pgsql'))->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a$$b = ? AND c$$d = ?', [1, 2]);

        $this->assertStringContainsString('a$$b = :__1__ AND c$$d = :__2__', $select->getStatement());
        $this->assertSame(['__1__' => 1, '__2__' => 2], $select->getBindValues());
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

    public function testResetDropsTheGeneratedValuesItsClauseBound()
    {
        // a value left behind would be a parameter the statement no longer
        // spells, and PDO rejects that at execute()
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = ?', [1])
            ->where('b IN (?)', [[10, 20]])
            ->resetWhere()
            ->where('a = ?', [2]);

        $this->assertStringContainsString('a = :__4__', $select->getStatement());
        $this->assertSame(['__4__' => 2], $select->getBindValues());
        $this->assertSame([2], $this->ids($select));

        $having = $this->query_factory->newSelect()
            ->cols(['a'])
            ->from('t')
            ->groupBy(['a'])
            ->having('a IN (?)', [[1, 2]])
            ->resetHaving();
        $this->assertSame([], $having->getBindValues());

        $join = $this->query_factory->newSelect()
            ->cols(['t.id'])
            ->from('t')
            ->join('INNER', 'u', 'u.id = t.id AND u.a = ?', [3])
            ->resetTables()
            ->from('t');
        $this->assertSame([], $join->getBindValues());
    }

    public function testResetWithOnADataModifyingQueryDropsTheCteGeneratedValues()
    {
        // an UPDATE has no UNION branches to keep a generated value alive
        $cte = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('u')
            ->where('a IN (?)', [[1, 2]]);

        $update = $this->query_factory->newUpdate()
            ->with('picked', $cte)
            ->table('t')
            ->cols(['b' => 0])
            ->where('id IN (SELECT id FROM picked)');

        $this->assertSame(['__1__' => 1, '__2__' => 2, 'b' => 0], $update->getBindValues());

        $update->resetWith();
        $this->assertSame(['b' => 0], $update->getBindValues());
    }

    public function testResetKeepsNamedValues()
    {
        // a name the caller wrote may be rebound by hand, so it stays, as
        // it always has
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a = :a', ['a' => 1])
            ->resetWhere();

        $this->assertSame(['a' => 1], $select->getBindValues());
    }

    public function testUnionKeepsTheGeneratedValuesOfARenderedBranch()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id IN (?)', [[1, 2]]);

        $select->union()
            ->cols(['id'])
            ->from('u')
            ->where('id = ?', [5]);

        $this->assertSame(['__1__' => 1, '__2__' => 2, '__3__' => 5], $select->getBindValues());
        $this->assertSame([1, 2, 5], $this->ids($select));

        $select->resetUnions();
        $this->assertSame(['__3__' => 5], $select->getBindValues());
    }

    public function testQuestionMarksInCommentsAreNotPlaceholders()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where("a > ? /* why? :no */ AND b < ? -- really? :no\n", [1, 50]);

        $statement = $select->getStatement();
        $this->assertStringContainsString('a > :__1__ /* why? :no */ AND b < :__2__ -- really? :no', $statement);
        $this->assertSame(['__1__' => 1, '__2__' => 50], $select->getBindValues());
        $this->assertSame([2, 3, 4], $this->ids($select));
    }

    public function testMysqlDashesBeginACommentOnlyBeforeWhitespace()
    {
        $factory = new QueryFactory('mysql');

        // `a--?` is subtraction and a placeholder on MySQL
        $select = $factory->newSelect()->cols(['*'])->from('t')->where('a--? > 0', [1]);
        $this->assertStringContainsString('a--:__1__ > 0', $select->getStatement());

        $update = $factory->newUpdate()->table('t')->cols(['b' => 1])->where('a--? > 0', [1]);
        $this->assertStringContainsString('a--:__1__ > 0', $update->getStatement());

        // `-- ` and `#` are comments there, and hide the `?` inside them
        $select = $factory->newSelect()->cols(['*'])->from('t')
            ->where("a = ? -- why?\nAND b = ? # really?\n", [1, 2]);
        $this->assertStringContainsString('a = :__1__ -- why?', $select->getStatement());
        $this->assertStringContainsString('AND b = :__2__ # really?', $select->getStatement());
    }

    public function testStandardDashesNeedNoWhitespace()
    {
        // SQLite and PostgreSQL read `--` as a comment whatever follows it,
        // and `#` as an operator
        $select = $this->query_factory->newSelect()->cols(['id'])->from('t')
            ->where("a > ? --why?\n", [3]);
        $this->assertStringContainsString('a > :__1__ --why?', $select->getStatement());
        $this->assertSame([4, 5], $this->ids($select));

        $pgsql = (new QueryFactory('pgsql'))->newSelect()->cols(['*'])->from('t')
            ->where('a # ? = 0', [1]);
        $this->assertStringContainsString('a # :__1__ = 0', $pgsql->getStatement());
    }

    public function testResetKeepsTheValuesOfABranchSuppliedWhole()
    {
        // a branch passed to union() is kept as rendered SQL, so its
        // generated values stay; a value bound and reset after it does not
        $branch = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('u')
            ->where('id IN (?)', [[4, 5]]);

        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id = ?', [1])
            ->union($branch);

        $before = $select->getBindValues();
        $this->assertSame(['__1__' => 1, '__2__' => 4, '__3__' => 5], $before);

        $select->where('id = ?', [9])->resetWhere();

        $this->assertSame($before, $select->getBindValues());
        $this->assertSame([1, 4, 5], $this->ids($select));
    }

    public function testNameInsideALiteralOfARenderedBranchDoesNotKeepAValue()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where("':__1__' = ':__1__'");

        $select->union()
            ->cols(['id'])
            ->from('u')
            ->where('id = ?', [5])
            ->resetWhere();

        $this->assertSame([], $select->getBindValues());
    }

    public function testQuestionMarkInsidePostgresLiteralsIsText()
    {
        $factory = new QueryFactory('pgsql');

        $select = $factory->newSelect()->cols(['*'])->from('t')
            ->where('a = E\'it\\\'s ?\' AND b = $$why?$$ AND c = $q$what?$q$ AND d = ?', [5]);
        $this->assertStringContainsString(
            'a = E\'it\\\'s ?\' AND b = $$why?$$ AND c = $q$what?$q$ AND d = :__1__',
            $select->getStatement()
        );
        $this->assertSame(['__1__' => 5], $select->getBindValues());

        // only Postgres reads these as literals; elsewhere the scan stays as
        // it was
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->query_factory->newSelect()->cols(['*'])->from('t')
            ->where('a = $$why?$$ AND b = ?', [5]);
    }

    public function testGeneratedNamesFollowTheOrderTheValuesAreGiven()
    {
        $sub = $this->query_factory->newSelect()->cols(['id'])->from('u')->where('a = ?', [3]);

        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('id IN (?) AND b > ? AND a IN (?)', [$sub, 20, [3, 4]]);

        $this->assertStringContainsString('b > :__2__ AND a IN (:__3__, :__4__)', $select->getStatement());
        $this->assertSame(
            ['__1__' => 3, '__2__' => 20, '__3__' => 3, '__4__' => 4],
            $select->getBindValues()
        );
        $this->assertSame([3], $this->ids($select));
    }

    public function testEachRetainedBranchIsReadOnce()
    {
        // each retained branch is read for its names once, not once per
        // generated name a reset releases; reading it per name made 100
        // branches of 100-value lists take seconds. Counted rather than
        // timed, so that a slow machine cannot fail it.
        $select = new class (new Common\Quoter(), new Common\SelectBuilder()) extends Sqlite\Select {
            public int $reads = 0;

            protected function getSpelledNames(string $sql): array
            {
                $this->reads++;
                return parent::getSpelledNames($sql);
            }
        };

        $select->cols(['id'])->from('t')->where('a IN (?)', [range(1, 20)]);
        for ($i = 1; $i < 50; $i++) {
            $select->unionAll()->cols(['id'])->from('t')->where('a IN (?)', [range(1, 20)]);
        }
        $select->getStatement();

        $this->assertSame(49, $select->reads);
        $this->assertCount(1000, $select->getBindValues());
    }

    public function testClosureValuesFillTheQuestionMarksItLeavesEmpty()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where(function ($select) {
                $select->where('a > ?')->where('b < ?', [50]);
            }, [1])
            ->orderBy(['id']);

        // taken before the closure runs, so the outer value numbers first
        $this->assertStringContainsString('a > :__1__', $this->flat($select));
        $this->assertStringContainsString('b < :__2__', $this->flat($select));
        $this->assertSame(['__2__' => 50, '__1__' => 1], $select->getBindValues());
        $this->assertSame([2, 3, 4], $this->ids($select));
    }

    public function testClosureValuesReachNestedClosuresInReadingOrder()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where(function ($select) {
                $select->where('a > ?')->where(function ($select) {
                    $select->where('b = ?')->orWhere('b = ?', [50]);
                });
            }, [1, 20])
            ->orderBy(['id']);

        $this->assertStringContainsString('b = :__2__ OR b = :__3__', $this->flat($select));
        $this->assertSame([2, 5], $this->ids($select));
    }

    public function testClosureNumbersInPlaceWhereverItStands()
    {
        $select = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where('a > ?', [1])
            ->where(function ($select) {
                $select->where('a < ?')->where('b < ?', [50]);
            }, [5])
            ->where('b > ?', [10]);

        $this->assertStringContainsString(
            'a > :__1__ AND ( a < :__2__ AND b < :__3__ ) AND b > :__4__',
            $this->flat($select)
        );
        $this->assertSame([2, 3, 4], $this->ids($select));

        // inside a closure, the values passed with it number before its own,
        // so an empty `?` after a valued one reads out of order -- still all
        // named, and bound correctly
        $inner = $this->query_factory->newSelect()
            ->cols(['id'])
            ->from('t')
            ->where(function ($select) {
                $select->where('b < ?', [50])->where('a > ?');
            }, [1]);
        $this->assertStringContainsString('b < :__2__ AND a > :__1__', $this->flat($inner));
        $this->assertSame([2, 3, 4], $this->ids($inner));
    }

    public function testClosureKeepsNamedValuesAndUnfilledQuestionMarks()
    {
        $named = $this->query_factory->newSelect()->cols(['id'])->from('t')
            ->where(function ($select) {
                $select->where('a = :a')->orWhere('a = ?');
            }, ['a' => 1, 3]);
        $this->assertStringContainsString('a = :a OR a = :__1__', $this->flat($named));
        $this->assertSame([1, 3], $this->ids($named));

        // no values for `?` given with it: the `?` is left for binding by hand
        $bare = $this->query_factory->newSelect()->cols(['id'])->from('t')
            ->where(function ($select) {
                $select->where('a = ?');
            });
        $this->assertStringContainsString('a = ?', $this->flat($bare));
    }

    public function testClosureValuesMustMatchItsEmptyQuestionMarks()
    {
        $select = $this->query_factory->newSelect()->cols(['id'])->from('t');

        try {
            $select->where(function ($select) {
                $select->where('a = ?');
            }, [1, 2]);
            $this->fail('Expected a count mismatch.');
        } catch (Exception\InvalidArgumentException $e) {
            $this->assertStringContainsString("leave 1 '?' placeholder(s) without a value, but 2", $e->getMessage());
        }

        // and the failed call left nothing behind
        $this->assertSame([], $select->getBindValues());
        $this->assertStringNotContainsString('WHERE', $this->flat($select));
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
