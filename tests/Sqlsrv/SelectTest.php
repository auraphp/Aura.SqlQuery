<?php
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

class SelectTest extends Common\SelectTest
{
    protected $db_type = 'sqlsrv';

    public static function provideNamesHeldFromABranch()
    {
        return array_merge(Common\SelectTest::provideNamesHeldFromABranch(), [
            // read no further than this. SQL Server names quoted with double
            // quotes are legal under QUOTED_IDENTIFIER, but the brackets are
            // what this builder writes, so a name kept inside a double-quoted
            // one is a needless collision report and nothing worse.
            'gap: double-quoted identifier' => [['a'], 'x = "odd:a name"'],
        ]);
    }

    public function testLimitOffset()
    {
        $this->query->cols(['*']);
        $this->query->limit(10);
        $expect = '
            SELECT TOP 10
                *
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);

        $this->query->offset(40);
        $expect = '
            SELECT
                *
            OFFSET 40 ROWS FETCH NEXT 10 ROWS ONLY
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    public function testPage()
    {
        $this->query->cols(['*']);
        $this->query->page(5);
        $expect = '
            SELECT
                *
            OFFSET 40 ROWS FETCH NEXT 10 ROWS ONLY
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    protected function withRecursiveKeyword()
    {
        return 'WITH';
    }

    public function testWithLimit()
    {
        $sub = $this->newQuery()->cols(['c1'])->from('t1');
        $this->query->with('cte', $sub)->cols(['*'])->from('cte')->limit(10);
        $expect = '
            WITH <<cte>> AS (
                SELECT
                    c1
                FROM
                    <<t1>>
            )
            SELECT TOP 10
                *
            FROM
                <<cte>>
        ';
        $this->assertSameSql($expect, $this->query->getStatement());
    }

    /**
     *
     * SQL Server has no FOR UPDATE on a plain SELECT; asking for it has to
     * say so rather than render SQL that cannot run.
     *
     */
    public function testForUpdate()
    {
        $this->query->cols(['*']);

        // turning it off is allowed and does nothing
        $this->query->forUpdate(false);
        $expect = '
            SELECT
                *
        ';
        $this->assertSameSql($expect, $this->query->__toString());

        $this->expectException(\Aura\SqlQuery\Exception\BadMethodCallException::class);
        $this->expectExceptionMessage("doesn't support FOR UPDATE");
        $this->query->forUpdate();
    }

    public static function provideStateAfterUnionTail()
    {
        $cases = Common\SelectTest::provideStateAfterUnionTail();
        unset($cases['forUpdate']);
        return $cases;
    }
}
