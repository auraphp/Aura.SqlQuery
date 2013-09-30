<?php
namespace Aura\Sql_Query;

class QueryFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provider
     */
    public function test($db_type, $common, $query_type, $expect)
    {
        $query_factory = new QueryFactory($db_type, $common);
        $method = 'new' . $query_type;
        $actual = $query_factory->$method();
        $this->assertInstanceOf($expect, $actual);
    }
    
    public function provider()
    {
        return [
            // db-specific
            ['Common', false, 'Select', 'Aura\Sql_Query\Common\Select'],
            ['Common', false, 'Insert', 'Aura\Sql_Query\Common\Insert'],
            ['Common', false, 'Update', 'Aura\Sql_Query\Common\Update'],
            ['Common', false, 'Delete', 'Aura\Sql_Query\Common\Delete'],
            ['Mysql',  false, 'Select', 'Aura\Sql_Query\Mysql\Select'],
            ['Mysql',  false, 'Insert', 'Aura\Sql_Query\Mysql\Insert'],
            ['Mysql',  false, 'Update', 'Aura\Sql_Query\Mysql\Update'],
            ['Mysql',  false, 'Delete', 'Aura\Sql_Query\Mysql\Delete'],
            ['Pgsql',  false, 'Select', 'Aura\Sql_Query\Pgsql\Select'],
            ['Pgsql',  false, 'Insert', 'Aura\Sql_Query\Pgsql\Insert'],
            ['Pgsql',  false, 'Update', 'Aura\Sql_Query\Pgsql\Update'],
            ['Pgsql',  false, 'Delete', 'Aura\Sql_Query\Pgsql\Delete'],
            ['Sqlite', false, 'Select', 'Aura\Sql_Query\Sqlite\Select'],
            ['Sqlite', false, 'Insert', 'Aura\Sql_Query\Sqlite\Insert'],
            ['Sqlite', false, 'Update', 'Aura\Sql_Query\Sqlite\Update'],
            ['Sqlite', false, 'Delete', 'Aura\Sql_Query\Sqlite\Delete'],
            ['Sqlsrv', false, 'Select', 'Aura\Sql_Query\Sqlsrv\Select'],
            ['Sqlsrv', false, 'Insert', 'Aura\Sql_Query\Sqlsrv\Insert'],
            ['Sqlsrv', false, 'Update', 'Aura\Sql_Query\Sqlsrv\Update'],
            ['Sqlsrv', false, 'Delete', 'Aura\Sql_Query\Sqlsrv\Delete'],
            
            // force common
            ['Common', QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'],
            ['Common', QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'],
            ['Common', QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'],
            ['Common', QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'],
            ['Mysql',  QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'],
            ['Mysql',  QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'],
            ['Mysql',  QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'],
            ['Mysql',  QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'],
            ['Pgsql',  QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'],
            ['Pgsql',  QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'],
            ['Pgsql',  QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'],
            ['Pgsql',  QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'],
            ['Sqlite', QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'],
            ['Sqlite', QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'],
            ['Sqlite', QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'],
            ['Sqlite', QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'],
            ['Sqlsrv', QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'],
            ['Sqlsrv', QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'],
            ['Sqlsrv', QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'],
            ['Sqlsrv', QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'],
        ];
    }
}
