<?php
namespace Aura\SqlQuery\Mysql;

use Aura\SqlQuery\Common;

class Quoter extends Common\Quoter
{
    /**
     *
     * The prefix to use when quoting identifier names.
     *
     * @var string
     *
     */
    protected $quote_name_prefix = '`';

    /**
     *
     * The suffix to use when quoting identifier names.
     *
     * @var string
     *
     */
    protected $quote_name_suffix = '`';
}
