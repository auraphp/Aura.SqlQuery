<?php
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'sqlsrv';

    public function testIgnore()
    {
        // T-SQL has no INSERT IGNORE equivalent, so the base behavior
        // of throwing on ignore() must be retained
        $this->expectException(\Aura\SqlQuery\Exception\BadMethodCallException::class);
        $this->expectExceptionMessage("doesn't support IGNORE");
        $this->query->ignore();
    }
}
