<?php
/**
 * 
 * This file is part of Aura for PHP.
 * 
 * @package Aura.SqlQuery
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\SqlQuery;

/**
 * 
 * Creates query statement objects.
 * 
 * @package Aura.SqlQuery
 * 
 */
class QueryFactory
{
    const COMMON = 'common';
    
    /**
     * 
     * What database are we building for?
     * 
     * @param string
     * 
     */
    protected $db;
    
    /**
     * 
     * Build "common" query objects regardless of database type?
     * 
     * @param bool
     * 
     */
    protected $common = false;
    
    /**
     * 
     * The quote prefix/suffix to use for each type.
     * 
     * @param array
     * 
     */
    protected $quotes = array(
        'Common' => array('"', '"'),
        'Mysql'  => array('`', '`'),
        'Pgsql'  => array('"', '"'),
        'Sqlite' => array('"', '"'),
        'Sqlsrv' => array('[', ']'),
    );
    
    /**
     * 
     * The quote name prefix extracted from `$quotes`.
     * 
     * @var string
     * 
     */
    protected $quote_name_prefix;
    
    /**
     * 
     * The quote name suffix extracted from `$quotes`.
     * 
     * @var string
     * 
     */
    protected $quote_name_suffix;
    
    /**
     * 
     * Constructor.
     * 
     * @param string $db The database type.
     * 
     * @param string $common Pass the constant self::COMMON to force common
     * query objects instead of db-specific ones.
     * 
     */
    public function __construct($db, $common = false)
    {
        $this->db = ucfirst(strtolower($db));
        $this->common = ($common === self::COMMON);
        $this->quote_name_prefix = $this->quotes[$this->db][0];
        $this->quote_name_suffix = $this->quotes[$this->db][1];
    }
    
    /**
     * 
     * Returns a new SELECT object.
     * 
     * @return Common\SelectInterface
     * 
     */
    public function newSelect()
    {
        return $this->newInstance('Select');
    }
    
    /**
     * 
     * Returns a new INSERT object.
     * 
     * @return Common\InsertInterface
     * 
     */
    public function newInsert()
    {
        return $this->newInstance('Insert');
    }
    
    /**
     * 
     * Returns a new UPDATE object.
     * 
     * @return Common\UpdateInterface
     * 
     */
    public function newUpdate()
    {
        return $this->newInstance('Update');
    }
    
    /**
     * 
     * Returns a new DELETE object.
     * 
     * @return Common\DeleteInterface
     * 
     */
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
     * @return AbstractQuery
     * 
     */
    protected function newInstance($query)
    {
        if ($this->common) {
            $class = "Aura\SqlQuery\Common";
        } else {
            $class = "Aura\SqlQuery\\{$this->db}";
        }
        
        $class .= "\\{$query}";
        
        return new $class(new Quoter(
            $this->quote_name_prefix,
            $this->quote_name_suffix
        ));
    }
}
