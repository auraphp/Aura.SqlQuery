<?php
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\Exception\InvalidArgumentException;
use Aura\SqlQuery\Exception\LogicException;

/**
 *
 * Shared WITH assertions for the data-modifying queries.
 *
 * The clause itself is the same on all three, so the only thing each test
 * class has to say is what its own statement looks like below the clause:
 * withBody() fills the query in and returns the SQL it renders to.
 *
 */
trait WithTestTrait
{
    /**
     *
     * Fills in the query below the WITH clause.
     *
     * @return string The SQL the filled-in query renders to.
     *
     */
    abstract protected function withBody();

    /**
     *
     * SQL Server infers the recursion and rejects the keyword.
     *
     * @return string
     *
     */
    protected function withRecursiveKeyword()
    {
        return 'WITH RECURSIVE';
    }

    /**
     *
     * MySQL takes no WITH clause on INSERT, and refuses it rather than
     * building a statement that can only fail at execute time.
     *
     * @return bool
     *
     */
    protected function supportsWith()
    {
        return true;
    }

    protected function skipWithoutWith()
    {
        if (! $this->supportsWith()) {
            $this->markTestSkipped(
                'This query takes no WITH clause on this dialect.'
            );
        }
    }

    protected function newCte()
    {
        return $this->query_factory->newSelect()->cols(['c1'])->from('t1');
    }

    public function testWith()
    {
        $this->skipWithoutWith();
        $this->query->with('cte', $this->newCte());
        $body = trim($this->withBody());
        $expect = "
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            {$body}
        ";
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithCols()
    {
        $this->skipWithoutWith();
        $this->query->with('cte', $this->newCte(), ['col1']);
        $body = trim($this->withBody());
        $expect = "
            WITH <<cte>> (<<col1>>) AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            {$body}
        ";
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithString()
    {
        $this->skipWithoutWith();
        $this->query->with('cte', 'SELECT c1 FROM t1');
        $body = trim($this->withBody());
        $expect = "
            WITH <<cte>> AS (
                SELECT c1 FROM t1
            )
            {$body}
        ";
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithMultiple()
    {
        $this->skipWithoutWith();
        $sub2 = $this->query_factory->newSelect()->cols(['c2'])->from('t2');
        $this->query
            ->with('cte1', $this->newCte())
            ->with('cte2', $sub2);
        $body = trim($this->withBody());
        $expect = "
            WITH <<cte1>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            ),
            <<cte2>> AS (
                SELECT
                    c2
                FROM
                    <<t2>>
            )
            {$body}
        ";
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    public function testWithRecursive()
    {
        $this->skipWithoutWith();
        $this->query->withRecursive('cte', $this->newCte());
        $body = trim($this->withBody());
        $keyword = $this->withRecursiveKeyword();
        $expect = "
            {$keyword} <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            {$body}
        ";
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    /**
     *
     * The values the CTE bound come with it, claimed for the statement rather
     * than for any one clause of it.
     *
     */
    public function testWithBindValues()
    {
        $this->skipWithoutWith();
        $sub = $this->query_factory->newSelect()
            ->cols(['c1'])
            ->from('t1')
            ->where('c2 = :v', ['v' => 9]);

        $this->query->with('cte', $sub);
        $this->withBody();

        $this->assertSame(['v' => 9], $this->query->getBindValues());
    }

    public function testHasWith()
    {
        $this->skipWithoutWith();
        $this->assertFalse($this->query->hasWith());
        $this->query->with('cte', $this->newCte());
        $this->assertTrue($this->query->hasWith());
    }

    public function testResetWith()
    {
        $this->skipWithoutWith();
        $this->query->with('cte', $this->newCte());
        $this->query->resetWith();

        $body = trim($this->withBody());
        $this->assertSameSql($body, $this->query->getStatement());
        $this->assertFalse($this->query->hasWith());
    }

    /**
     *
     * Once the clause is gone the names it held are free, so the clause that
     * asks for one next may have it.
     *
     */
    public function testResetWithReleasesBindSources()
    {
        $this->skipWithoutWith();
        $sub = $this->query_factory->newSelect()
            ->cols(['c1'])
            ->from('t1')
            ->where('c2 = :v', ['v' => 9]);

        $this->query->with('cte', $sub);
        $this->query->resetWith();

        $this->query->bindValue('v', 10);
        $this->assertSame(['v' => 10], $this->query->getBindValues());
    }

    public function testWithRequiresName()
    {
        $this->skipWithoutWith();
        $this->expectException(InvalidArgumentException::class);
        $this->query->with('', $this->newCte());
    }

    public function testWithRejectsDuplicateName()
    {
        $this->skipWithoutWith();
        $this->query->with('cte', $this->newCte());
        $this->expectException(LogicException::class);
        $this->query->with('cte', $this->newCte());
    }

    /**
     *
     * A CTE that cannot render leaves the clause as it was, rather than
     * marking it recursive on the way to throwing.
     *
     */
    public function testWithRecursiveDoesNotSetFlagWhenWithThrows()
    {
        $this->skipWithoutWith();
        try {
            $this->query->withRecursive('', $this->newCte());
            $this->fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            // expected
        }

        $this->query->with('cte', $this->newCte());
        $body = trim($this->withBody());
        $expect = "
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            {$body}
        ";
        $this->assertSameSql($expect, $this->query->getStatement());
    }
}
