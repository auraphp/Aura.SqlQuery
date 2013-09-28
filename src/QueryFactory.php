<?php
/**
 * 
 * This file is part of Aura for PHP.
 * 
 * @package Aura.Sql
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Sql_Query;

/**
 * 
 * Creates query statement objects.
 * 
 * @package Aura.Sql
 * 
 */
class QueryFactory
{
    protected $type = 'Common';
    
    public function __construct($type = null)
    {
        if ($type) {
            $this->type = ucfirst(strtolower($type));
        }
    }
    
    public function newSelect()
    {
        return $this->newInstance('select');
    }
    
    public function newInsert()
    {
        return $this->newInstance('insert');
    }
    
    public function newUpdate()
    {
        return $this->newInstance('update');
    }
    
    public function newDelete()
    {
        return $this->newInstance('delete');
    }
    
    /**
     * 
     * Returns a new query object.
     * 
     * @param string $query The query object type.
     * 
     * @param string $type The database backend type to use; if empty,
     * defaults to the 'Common' type.
     * 
     * @return AbstractQuery
     * 
     */
    protected function newInstance($query)
    {
        $query = ucfirst(strtolower($query));
        $class = "Aura\Sql_Query\\{$this->type}\\{$query}";
        return new $class;
    }
    
}
