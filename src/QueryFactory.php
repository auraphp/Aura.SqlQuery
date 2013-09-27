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
    public function newInstance($query, $type = null)
    {
        $query = ucfirst(strtolower($query));
        
        if (! $type) {
            $class = "Aura\Sql_Query\\{$query}";
        } else {
            $type = ucfirst(strtolower($type));
            $class = "Aura\Sql_Query\\{$type}\\{$query}";
        }
        
        return new $class;
    }
}
