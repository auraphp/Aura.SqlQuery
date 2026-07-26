<?php
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

class UpdateTest extends Common\UpdateTest
{
    protected $db_type = 'sqlsrv';

    /**
     * SQL Server has no IGNORE; asking for it has to say so rather than
     * fatal on an undefined method.
     */
    public function testIgnoreNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support IGNORE flag");
        $this->query->ignore();
    }

    /**
     * SQL Server has no REPLACE; asking for it has to say so rather than
     * fatal on an undefined method.
     */
    public function testOrReplaceNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support OR REPLACE flag");
        $this->query->orReplace();
    }
}
