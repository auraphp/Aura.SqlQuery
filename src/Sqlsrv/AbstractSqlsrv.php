<?php
namespace Aura\Sql_Query\Sqlsrv;

use Aura\Sql_Query\AbstractQuery;

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
