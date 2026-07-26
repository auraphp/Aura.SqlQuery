<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Sqlite;

use Aura\SqlQuery\Exception;

/**
 *
 * Common code for the SQLite conflict clauses, which INSERT and UPDATE
 * both accept.
 *
 * @package Aura.SqlQuery
 *
 */
trait OrConflictTrait
{
    /**
     *
     * The conflict clauses; a statement takes at most one of them.
     *
     * @var string[]
     *
     */
    protected $or_conflict_flags = [
        'OR ABORT',
        'OR FAIL',
        'OR IGNORE',
        'OR REPLACE',
        'OR ROLLBACK',
    ];

    /**
     *
     * Throws if more than one conflict clause was set; they are alternatives
     * to each other, so SQLite rejects a statement carrying two.
     *
     * @return void
     *
     * @throws Exception\LogicException
     *
     */
    protected function assertOneOrConflictFlag()
    {
        $set = [];
        foreach ($this->or_conflict_flags as $flag) {
            if ($this->hasFlag($flag)) {
                $set[] = $flag;
            }
        }

        if (count($set) > 1) {
            throw new Exception\LogicException(
                'A statement takes only one conflict clause; got '
                . implode(' and ', $set) . '.'
            );
        }
    }
}
