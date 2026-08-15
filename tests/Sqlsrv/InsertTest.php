<?php
namespace Aura\SqlQuery\Sqlsrv;

use Aura\SqlQuery\Common;

class InsertTest extends Common\InsertTest
{
    protected $db_type = 'sqlsrv';

    protected function withRecursiveKeyword()
    {
        return 'WITH';
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

    public function testIgnore()
    {
        // T-SQL has no INSERT IGNORE equivalent, so the base behavior
        // of throwing on ignore() must be retained
        $this->expectException(\Aura\SqlQuery\Exception\BadMethodCallException::class);
        $this->expectExceptionMessage("doesn't support IGNORE");
        $this->query->ignore();
    }

    public function testOnConflictNotSupported()
    {
        $this->expectException('Aura\SqlQuery\Exception\BadMethodCallException');
        $this->expectExceptionMessage("doesn't support ON CONFLICT clause");
        $this->query->onConflict('id');
    }
}
