<?php
namespace Aura\Sql_Query;

interface QueryInterface
{
    /**
     * 
     * Builds this query object into a string.
     * 
     * @return string
     * 
     */
    public function __toString();
    
    public function getQuoteNamePrefix();
    
    public function getQuoteNameSuffix();
    
    /**
     * 
     * Adds values to bind into the query; merges with existing values.
     * 
     * @param array $bind_values Values to bind to the query.
     * 
     * @return null
     * 
     */
    public function bindValues(array $bind_values);

    public function bindValue($name, $value);
    
    /**
     * 
     * Gets the values to bind into the query.
     * 
     * @return array
     * 
     */
    public function getBindValues();
}
