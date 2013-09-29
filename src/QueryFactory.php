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
    const COMMON = 'common';
    
    protected $type;
    
    protected $common = false;
    
    protected $quotes = [
        'Common' => ['"', '"'],
        'Mysql'  => ['`', '`'],
        'Pgsql'  => ['"', '"'],
        'Sqlite' => ['"', '"'],
        'Sqlsrv' => ['[', ']'],
    ];
    
    protected $quote_name_prefix;
    
    protected $quote_name_suffix;
    
    public function __construct($type, $common = false)
    {
        $this->type = ucfirst(strtolower($type));
        $this->quote_name_prefix = $this->quotes[$this->type][0];
        $this->quote_name_suffix = $this->quotes[$this->type][1];
    }
    
    public function newSelect()
    {
        return $this->newInstance('Select');
    }
    
    public function newInsert()
    {
        return $this->newInstance('Insert');
    }
    
    public function newUpdate()
    {
        return $this->newInstance('Update');
    }
    
    public function newDelete()
    {
        return $this->newInstance('Delete');
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
        if ($this->common) {
            $class = "Aura\Sql_Query\Common";
        } else {
            $class = "Aura\Sql_Query\\{$this->type}";
        }
        
        $class .= "\\{$query}";
        
        return new $class(
            $this->quote_name_prefix,
            $this->quote_name_suffix
        );
    }
}
