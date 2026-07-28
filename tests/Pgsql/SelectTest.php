<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\Common;

class SelectTest extends Common\SelectTest
{
    use Common\LateralJoinTestTrait;

    protected $db_type = 'pgsql';

    /**
     * Postgres rejects an ON clause on a CROSS join, so supplying a condition
     * could only ever produce a syntax error at execute time. MySQL differs;
     * see Mysql\SelectTest.
     */
    public function testLateralJoinSubSelect_crossWithConditionThrows()
    {
        $this->query->cols(['*']);
        $this->query->from('t1');

        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('CROSS JOIN LATERAL cannot take a condition');

        $this->query->lateralJoinSubSelect(
            'cross',
            'SELECT * FROM t2',
            'a2',
            't1.c1 = a2.c1'
        );
    }

    public static function provideNamesHeldFromABranch()
    {
        return array_merge(Common\SelectTest::provideNamesHeldFromABranch(), array(
            // standard strings: the backslash is an ordinary character, so
            // the literal ends at the quote after it and the rest is SQL
            'backslash escape' => array(array('a'), "note = 'it\\'s :a'"),
            'hash is not a comment' => array(array('a'), 'c1 > 0 # :a'),

            // read no further than this. Both spell a literal Postgres alone
            // has, and a name kept inside one is a needless collision report
            // where a reading of them gone wrong would swallow a real
            // placeholder standing outside.
            'gap: dollar-quoted string' => array(array('a'), 'note = $$ :a $$'),
            'gap: escape string' => array(array('a'), "note = E'\\':a'"),
        ));
    }

}
