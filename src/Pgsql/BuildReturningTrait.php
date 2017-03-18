<?php
namespace Aura\SqlQuery\Pgsql;

trait BuildReturningTrait
{
    /**
     *
     * Builds the `RETURNING` clause of the statement.
     *
     * @return string
     *
     */
    public function buildReturning($returning)
    {
        if (empty($returning)) {
            return ''; // not applicable
        }

        return PHP_EOL . 'RETURNING' . $this->indentCsv($returning);
    }
}
