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
        return array(
            // db-specific
            array('Common', false, 'Select', 'Aura\Sql_Query\Common\Select'),
            array('Common', false, 'Insert', 'Aura\Sql_Query\Common\Insert'),
            array('Common', false, 'Update', 'Aura\Sql_Query\Common\Update'),
            array('Common', false, 'Delete', 'Aura\Sql_Query\Common\Delete'),
            array('Mysql',  false, 'Select', 'Aura\Sql_Query\Mysql\Select'),
            array('Mysql',  false, 'Insert', 'Aura\Sql_Query\Mysql\Insert'),
            array('Mysql',  false, 'Update', 'Aura\Sql_Query\Mysql\Update'),
            array('Mysql',  false, 'Delete', 'Aura\Sql_Query\Mysql\Delete'),
            array('Pgsql',  false, 'Select', 'Aura\Sql_Query\Pgsql\Select'),
            array('Pgsql',  false, 'Insert', 'Aura\Sql_Query\Pgsql\Insert'),
            array('Pgsql',  false, 'Update', 'Aura\Sql_Query\Pgsql\Update'),
            array('Pgsql',  false, 'Delete', 'Aura\Sql_Query\Pgsql\Delete'),
            array('Sqlite', false, 'Select', 'Aura\Sql_Query\Sqlite\Select'),
            array('Sqlite', false, 'Insert', 'Aura\Sql_Query\Sqlite\Insert'),
            array('Sqlite', false, 'Update', 'Aura\Sql_Query\Sqlite\Update'),
            array('Sqlite', false, 'Delete', 'Aura\Sql_Query\Sqlite\Delete'),
            array('Sqlsrv', false, 'Select', 'Aura\Sql_Query\Sqlsrv\Select'),
            array('Sqlsrv', false, 'Insert', 'Aura\Sql_Query\Sqlsrv\Insert'),
            array('Sqlsrv', false, 'Update', 'Aura\Sql_Query\Sqlsrv\Update'),
            array('Sqlsrv', false, 'Delete', 'Aura\Sql_Query\Sqlsrv\Delete'),
            
            // force common
            array('Common', QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'),
            array('Common', QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'),
            array('Common', QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'),
            array('Common', QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'),
            array('Mysql',  QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'),
            array('Mysql',  QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'),
            array('Mysql',  QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'),
            array('Mysql',  QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'),
            array('Pgsql',  QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'),
            array('Pgsql',  QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'),
            array('Pgsql',  QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'),
            array('Pgsql',  QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'),
            array('Sqlite', QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'),
            array('Sqlite', QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'),
            array('Sqlite', QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'),
            array('Sqlite', QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'),
            array('Sqlsrv', QueryFactory::COMMON, 'Select', 'Aura\Sql_Query\Common\Select'),
            array('Sqlsrv', QueryFactory::COMMON, 'Insert', 'Aura\Sql_Query\Common\Insert'),
            array('Sqlsrv', QueryFactory::COMMON, 'Update', 'Aura\Sql_Query\Common\Update'),
            array('Sqlsrv', QueryFactory::COMMON, 'Delete', 'Aura\Sql_Query\Common\Delete'),
        );
    }
}
