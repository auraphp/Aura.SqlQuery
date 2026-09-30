<?php
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Common;

class SelectTest extends Common\SelectTest
{
    protected $db_type = 'sqlite';

    /**
     *
     * SQLite has no FOR UPDATE; asking for it has to say so rather than
     * render SQL that cannot run.
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
