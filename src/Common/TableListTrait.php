<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

/**
 *
 * Reads a comma-separated list of table names, for the queries that take a
 * table: SELECT, UPDATE and DELETE.
 *
 * @package Aura.SqlQuery
 *
 */
trait TableListTrait
{
    /**
     *
     * Splits a comma-separated list of table names into its parts.
     *
     * quoteName() reads a space as the boundary between a name and its alias
     * and has no idea what to do with a list, so 't1, t2' came out as
     * `"t1," "t2"` -- an identifier no database has. Splitting first leaves
     * each caller to decide whether a list means anything where it stands: a
     * SELECT takes several tables, an UPDATE or a DELETE takes one.
     *
     * A comma inside quotes belongs to the name, so the scan steps over
     * quoted stretches. It tracks the ordinary identifier quotes rather than
     * asking the quoter for this dialect's, since a caller may hand-quote in
     * any of them and the answer is the same either way: not a separator.
     *
     * @param string $spec One or more table names, comma-separated.
     *
     * @return array The individual names, trimmed, with empty parts dropped.
     *
     */
    protected function splitNamesList($spec)
    {
        $names = [];
        $name = '';
        $closer = null;
        $len = strlen($spec);

        for ($i = 0; $i < $len; $i ++) {
            $char = $spec[$i];

            if ($closer !== null) {
                $name .= $char;
                if ($char !== $closer) {
                    continue;
                }

                // a doubled closer is an escaped one, part of the name: the
                // SQL Server identifier [odd]],name] is `odd],name`, comma
                // and all. Symmetric quotes survive without this, since
                // closing and reopening on the doubled character lands back
                // inside the name, but `[` and `]` cannot do that.
                if (isset($spec[$i + 1]) && $spec[$i + 1] === $closer) {
                    $i ++;
                    $name .= $spec[$i];
                    continue;
                }

                $closer = null;
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $closer = $char;
            } elseif ($char === '[') {
                $closer = ']';
            } elseif ($char === ',') {
                $names[] = $name;
                $name = '';
                continue;
            }

            $name .= $char;
        }

        $names[] = $name;

        $list = [];
        foreach ($names as $one) {
            $one = trim($one);
            if ($one !== '') {
                $list[] = $one;
            }
        }
        return $list;
    }
}
