<?php
namespace Aura\Sql\Query\Sqlsrv;

use Aura\Sql\Query\AbstractQuery;

abstract class AbstractSqlsrv extends AbstractQuery
{
    /**
     * 
     * The prefix to use when quoting identifier names.
     * 
     * @var string
     * 
     */
    protected $quote_name_prefix = '[';

    /**
     * 
     * The suffix to use when quoting identifier names.
     * 
     * @var string
     * 
     */
    protected $quote_name_suffix = ']';
}
