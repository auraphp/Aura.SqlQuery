<?php
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

class DeleteTest extends Common\DeleteTest
{
    protected $db_type = 'sqlsrv';

    protected function withRecursiveKeyword()
    {
        return 'WITH';
    }

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
}
