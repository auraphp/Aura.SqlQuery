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
        return array_merge(Common\SelectTest::provideNamesHeldFromABranch(), [
            // standard strings: the backslash is an ordinary character, so
            // the literal ends at the quote after it and the rest is SQL
            'backslash escape' => [['a'], "note = 'it\\'s :a'"],
            'hash is not a comment' => [['a'], 'c1 > 0 # :a'],

            // the two literals Postgres alone has, read by Pgsql's own text
            // pattern: a name inside one is text, and a real placeholder
            // after one is still read
            'dollar-quoted string' => [[], 'note = $$ :a $$'],
            'tagged dollar-quoted string' => [[], 'note = $q$ it$s :a $q$'],
            'dollar-quoted then code' => [['a'], 'note = $$ :b $$ AND a = :a'],
            'escape string' => [[], "note = E'\\':a'"],
            'escape string then code' => [['a'], "note = E'it\\'s :b' AND a = :a"],
            'unclosed dollar quote' => [['a'], 'note = $$ unclosed AND a = :a'],
        ]);
    }

}
