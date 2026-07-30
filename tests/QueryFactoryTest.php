<?php
namespace Aura\SqlQuery;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueryFactoryTest extends TestCase
{
    #[DataProvider('provider')]
    public function test($db_type, $common, $query_type, $expect)
    {
        $query_factory = new QueryFactory($db_type, $common);
        $method = 'new' . $query_type;
        $actual = $query_factory->$method();
        $this->assertInstanceOf($expect, $actual);
    }

    public static function provider()
    {
        return [
            // db-specific
            ['Common', false, 'Select', 'Aura\SqlQuery\Common\Select'],
            ['Common', false, 'Insert', 'Aura\SqlQuery\Common\Insert'],
            ['Common', false, 'Update', 'Aura\SqlQuery\Common\Update'],
            ['Common', false, 'Delete', 'Aura\SqlQuery\Common\Delete'],
            ['Mysql',  false, 'Select', 'Aura\SqlQuery\Mysql\Select'],
            ['Mysql',  false, 'Insert', 'Aura\SqlQuery\Mysql\Insert'],
            ['Mysql',  false, 'Update', 'Aura\SqlQuery\Mysql\Update'],
            ['Mysql',  false, 'Delete', 'Aura\SqlQuery\Mysql\Delete'],
            ['Pgsql',  false, 'Select', 'Aura\SqlQuery\Pgsql\Select'],
            ['Pgsql',  false, 'Insert', 'Aura\SqlQuery\Pgsql\Insert'],
            ['Pgsql',  false, 'Update', 'Aura\SqlQuery\Pgsql\Update'],
            ['Pgsql',  false, 'Delete', 'Aura\SqlQuery\Pgsql\Delete'],
            ['Sqlite', false, 'Select', 'Aura\SqlQuery\Sqlite\Select'],
            ['Sqlite', false, 'Insert', 'Aura\SqlQuery\Sqlite\Insert'],
            ['Sqlite', false, 'Update', 'Aura\SqlQuery\Sqlite\Update'],
            ['Sqlite', false, 'Delete', 'Aura\SqlQuery\Sqlite\Delete'],
            ['Sqlsrv', false, 'Select', 'Aura\SqlQuery\Sqlsrv\Select'],
            ['Sqlsrv', false, 'Insert', 'Aura\SqlQuery\Sqlsrv\Insert'],
            ['Sqlsrv', false, 'Update', 'Aura\SqlQuery\Sqlsrv\Update'],
            ['Sqlsrv', false, 'Delete', 'Aura\SqlQuery\Sqlsrv\Delete'],

            // force common
            ['Common', QueryFactory::COMMON, 'Select', 'Aura\SqlQuery\Common\Select'],
            ['Common', QueryFactory::COMMON, 'Insert', 'Aura\SqlQuery\Common\Insert'],
            ['Common', QueryFactory::COMMON, 'Update', 'Aura\SqlQuery\Common\Update'],
            ['Common', QueryFactory::COMMON, 'Delete', 'Aura\SqlQuery\Common\Delete'],
            ['Mysql',  QueryFactory::COMMON, 'Select', 'Aura\SqlQuery\Common\Select'],
            ['Mysql',  QueryFactory::COMMON, 'Insert', 'Aura\SqlQuery\Common\Insert'],
            ['Mysql',  QueryFactory::COMMON, 'Update', 'Aura\SqlQuery\Common\Update'],
            ['Mysql',  QueryFactory::COMMON, 'Delete', 'Aura\SqlQuery\Common\Delete'],
            ['Pgsql',  QueryFactory::COMMON, 'Select', 'Aura\SqlQuery\Common\Select'],
            ['Pgsql',  QueryFactory::COMMON, 'Insert', 'Aura\SqlQuery\Common\Insert'],
            ['Pgsql',  QueryFactory::COMMON, 'Update', 'Aura\SqlQuery\Common\Update'],
            ['Pgsql',  QueryFactory::COMMON, 'Delete', 'Aura\SqlQuery\Common\Delete'],
            ['Sqlite', QueryFactory::COMMON, 'Select', 'Aura\SqlQuery\Common\Select'],
            ['Sqlite', QueryFactory::COMMON, 'Insert', 'Aura\SqlQuery\Common\Insert'],
            ['Sqlite', QueryFactory::COMMON, 'Update', 'Aura\SqlQuery\Common\Update'],
            ['Sqlite', QueryFactory::COMMON, 'Delete', 'Aura\SqlQuery\Common\Delete'],
            ['Sqlsrv', QueryFactory::COMMON, 'Select', 'Aura\SqlQuery\Common\Select'],
            ['Sqlsrv', QueryFactory::COMMON, 'Insert', 'Aura\SqlQuery\Common\Insert'],
            ['Sqlsrv', QueryFactory::COMMON, 'Update', 'Aura\SqlQuery\Common\Update'],
            ['Sqlsrv', QueryFactory::COMMON, 'Delete', 'Aura\SqlQuery\Common\Delete'],
        ];
    }
}
