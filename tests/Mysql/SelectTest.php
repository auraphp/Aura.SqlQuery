<?php
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

class SelectTest extends Common\SelectTest
{
    use Common\LateralJoinTestTrait;

    protected $db_type = 'mysql';

    /**
     * Unlike Postgres, MySQL accepts an ON clause on a CROSS join, because
     * CROSS and INNER are synonyms there. So a condition is rendered rather
     * than rejected; see Pgsql\SelectTest for the contrast.
     */
    public function testLateralJoinSubSelect_crossWithCondition()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');
        $this->query->lateralJoinSubSelect(
            'cross',
            'SELECT * FROM t2',
            'a2',
            't1.c1 = a2.c1'
        );
        $expect = '
            SELECT
                *
            FROM
                <<t1>>
                    CROSS JOIN LATERAL (
                        SELECT * FROM t2
                    ) <<a2>> ON <<t1>>.<<c1>> = <<a2>>.<<c1>>
        ';
        $actual = $this->query->__toString();
        $this->assertSameSql($expect, $actual);
    }

    protected $expected_sql_with_flag = '
        SELECT %s
            <<t1>>.<<c1>>,
            <<t1>>.<<c2>>,
            <<t1>>.<<c3>>
        FROM
            <<t1>>
    ';

    public function testMultiFlags()
    {
        $this->query->calcFoundRows()
                    ->distinct()
                    ->noCache()
                    ->from('t1')
                    ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_CALC_FOUND_ROWS DISTINCT SQL_NO_CACHE');
        $this->assertSameSql($expect, $actual);
    }

    public function testCalcFoundRows()
    {
        $this->query->calcFoundRows()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_CALC_FOUND_ROWS');
        $this->assertSameSql($expect, $actual);
    }

    public function testCache()
    {
        $this->query->cache()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_CACHE');
        $this->assertSameSql($expect, $actual);
    }

    public function testNoCache()
    {
        $this->query->noCache()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_NO_CACHE');
        $this->assertSameSql($expect, $actual);
    }

    public function testStraightJoin()
    {
        $this->query->straightJoin()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'STRAIGHT_JOIN');
        $this->assertSameSql($expect, $actual);
    }

    public function testHighPriority()
    {
        $this->query->highPriority()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'HIGH_PRIORITY');
        $this->assertSameSql($expect, $actual);
    }

    public function testSmallResult()
    {
        $this->query->smallResult()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_SMALL_RESULT');
        $this->assertSameSql($expect, $actual);
    }

    public function testBigResult()
    {
        $this->query->bigResult()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_BIG_RESULT');
        $this->assertSameSql($expect, $actual);
    }

    public function testBufferResult()
    {
        $this->query->bufferResult()
                     ->from('t1')
                     ->cols(array('t1.c1', 't1.c2', 't1.c3'));

        $actual = $this->query->__toString();

        $expect = sprintf($this->expected_sql_with_flag, 'SQL_BUFFER_RESULT');
        $this->assertSameSql($expect, $actual);
    }

    public static function provideNamesHeldFromABranch()
    {
        return array_merge(Common\SelectTest::provideNamesHeldFromABranch(), array(
            'backslash escape' => array(array(), "note = 'it\\'s :a'"),
            'backslash before a backslash' => array(array('a'), "note = 'ends\\\\' AND a = :a"),
            'hash comment' => array(array(), 'c1 > 0 # :a'),
            'hash after a literal' => array(array(), "note = 'x' # :a"),

            // MySQL wants whitespace after the dashes before it reads them as
            // a comment, so this is an operator and a placeholder
            'no space after the dashes' => array(array('a'), 'c1 > 0--:a'),

            // read no further than this. A double-quoted string is a string
            // here and an identifier under ANSI_QUOTES, and keeping the name
            // costs a needless collision report where reading it as a string
            // would swallow a real placeholder in every ANSI_QUOTES query.
            'gap: double-quoted string' => array(array('a'), 'x = ":a"'),
        ));
    }

}
