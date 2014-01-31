<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @package Aura.Sql_Query
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */
namespace Aura\Sql_Query\Common;

use Aura\Sql_Query\AbstractQuery;

/**
 *
 * An object for INSERT queries.
 *
 * @package Aura.Sql_Query
 *
 */
class Insert extends AbstractQuery implements InsertInterface
{
    /**
     *
     * The table to insert into.
     *
     * @var string
     *
     */
    protected $into;

    /**
     *
     * Sets the table to insert into.
     *
     * @param string $into The table to insert into.
     *
     * @return $this
     *
     */
    public function into($into)
    {
        // don't quote yet, we might need it for getLastInsertIdName()
        $this->into = $into;
        return $this;
    }

    /**
     * 
     * Builds this query object into a string.
     * 
     * @return string
     * 
     */
    protected function build()
    {
        return 'INSERT'
            . $this->buildFlags()
            . $this->buildInto()
            . $this->buildValuesForInsert()
            . $this->buildReturning();
    }
    
    /**
     * 
     * Builds the INTO clause.
     * 
     * @return string
     * 
     */
    protected function buildInto()
    {
        return " INTO " . $this->quoteName($this->into);
    }
    
    /**
     * 
     * Returns the proper name for passing to `PDO::lastInsertId()`.
     * 
     * @param string $col The last insert ID column.
     * 
     * @return null Normally null, since most drivers do not need a name.
     * 
     */
    public function getLastInsertIdName($col)
    {
        return null;
    }

    /**
     *
     * Sets one column value placeholder; if an optional second parameter is
     * passed, that value is bound to the placeholder.
     *
     * @param string $col The column name.
     *
     * @param mixed  $val Optional: a value to bind to the placeholder.
     *
     * @return $this
     *
     */
    public function col($col)
    {
        return call_user_func_array(array($this, 'addCol'), func_get_args());
    }

    /**
     *
     * Sets multiple column value placeholders. If an element is a key-value
     * pair, the key is treated as the column name and the value is bound to
     * that column.
     *
     * @param array $cols A list of column names, optionally as key-value
     *                    pairs where the key is a column name and the value is a bind value for
     *                    that column.
     *
     * @return $this
     *
     */
    public function cols(array $cols)
    {
        return $this->addCols($cols);
    }

    /**
     *
     * Sets a column value directly; the value will not be escaped, although
     * fully-qualified identifiers in the value will be quoted.
     *
     * @param string $col   The column name.
     *
     * @param string $value The column value expression.
     *
     * @return $this
     *
     */
    public function set($col, $value)
    {
        return $this->setCol($col, $value);
    }

    /**
     *
     * Gets the values to bind to placeholders.
     *
     * @return array
     *
     */
    public function getBindValues()
    {
        return $this->bind_values;
    }
}
