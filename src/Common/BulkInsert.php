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
namespace Aura\SqlQuery\Common;

/**
 *
 * An object for bulk INSERT queries.
 *
 * @package Aura.SqlQuery
 *
 */
class BulkInsert extends Insert implements BulkInsertInterface
{
    /**
     * @var int
     */
    protected $rows = 0;

    /**
     * @var array
     */
    protected $col_values = array();

    /**
     * @var array
     */
    protected $cols = array();

    /**
     * {@inheritdoc}
     */
    protected function addCols(array $cols)
    {
        foreach ($cols as $val) {
            $this->addCol($val);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function addCol($col)
    {
        $key = $this->quoter->quoteName($col);
        $this->col_values[$key] = "?";
        $this->cols[] = $col;
        return $this;
    }

    /**
     * Remove the new lines from the indentation. With bulk inserts it makes it hard to
     * read.
     *
     * {@inheritdoc}
     */
    protected function indentCsv(array $list)
    {
        return str_replace(array(" ", PHP_EOL), "", parent::indentCsv($list));
    }

    /**
     * Build the value list
     *
     * @return string
     */
    protected function buildValuesForInsert()
    {
        $rows = $this->rows > 0 ? $this->rows : count($this->bind_values);
        $rows = $rows == 0 ? 1 : $rows;

        $placeholder = $this->indentCsv(array_values($this->col_values));
        $keys = $this->indentCsv(array_keys($this->col_values));

        $return = PHP_EOL
            . "(" . $keys . ")" . PHP_EOL
            . "VALUES" . PHP_EOL
            . str_repeat("(" . $placeholder . ")," . PHP_EOL, $rows);

        return rtrim($return, "," . PHP_EOL);
    }

    /**
     * {@inheritdoc}
     */
    public function rows($rows)
    {
        $this->rows = $rows;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValues(array $bind_values)
    {
        $this->bind_values = $bind_values;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function bindValue($name, $value)
    {
        $value = (array) $value;

        foreach ($value as $key => $val) {
            if (isset($this->bind_values[$key])) {
                $this->bind_values[$key][$name] = $val;
            } else {
                $this->bind_values[$key] = array($name => $val);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getBindValues()
    {
        $return = array();

        foreach ($this->bind_values as $entry) {
            $values = array_intersect_key($entry, array_flip($this->cols));

            foreach ($values as $val) {
                $return[] = $val;
            }
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    protected function buildReturning()
    {
        if (! $this->returning) return ''; // not applicable
        return PHP_EOL . 'RETURNING ' . $this->indentCsv($this->returning);
    }
}
