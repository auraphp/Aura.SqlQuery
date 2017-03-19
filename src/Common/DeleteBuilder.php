<?php
namespace Aura\SqlQuery\Common;

class DeleteBuilder extends AbstractBuilder
{
    /**
     *
     * Builds the FROM clause.
     *
     * @return string
     *
     */
    public function buildFrom($from)
    {
        return " FROM {$from}";
    }
}
