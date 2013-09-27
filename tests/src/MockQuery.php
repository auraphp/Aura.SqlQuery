<?php
namespace Aura\Sql\Query;

class MockQuery extends AbstractQuery
{
    public function build()
    {
        return 'Hello Query!';
    }
    
    public function quoteName($text)
    {
        return parent::quoteName($text);
    }
    
    public function quoteNamesIn($text)
    {
        return parent::quoteNamesIn($text);
    }
    
    public function autobind($text, $bind = null)
    {
        return parent::autobind($text, $bind);
    }
}
