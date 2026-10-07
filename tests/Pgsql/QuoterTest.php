<?php
namespace Aura\SqlQuery\Pgsql;

use Aura\SqlQuery\QueryFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuoterTest extends TestCase
{
    /**
     * Issue #274: a dollar-quoted string is a literal, so a dotted name in
     * one is text, not a table.column reference.
     */
    #[DataProvider('provideDollarQuotedStrings')]
    public function testQuoteNamesInLeavesDollarQuotedStrings(string $input, string $expect)
    {
        $quoter = new Quoter();
        $this->assertSame($expect, $quoter->quoteNamesIn($input));
    }

    public static function provideDollarQuotedStrings(): array
    {
        return [
            'aliased' => ['$$x.y$$ AS lit', '$$x.y$$ AS "lit"'],
            'compared' => ['a = $$t.c$$', 'a = $$t.c$$'],
            'name after' => ['$$t.c$$ = t.c', '$$t.c$$ = "t"."c"'],
            'tagged' => ['$q$t.c$q$ = t.c', '$q$t.c$q$ = "t"."c"'],
            'non-ASCII tag' => ['$é$t.c$é$ = t.c', '$é$t.c$é$ = "t"."c"'],
            'empty' => ['$$$$ = t.c', '$$$$ = "t"."c"'],
            'other tag inside' => [
                '$q$ a.b $$ c.d $r$ e.f $q$ = t.c',
                '$q$ a.b $$ c.d $r$ e.f $q$ = "t"."c"',
            ],
            'quote inside' => [
                "\$\$it's t.c\$\$ = t.c",
                "\$\$it's t.c\$\$ = \"t\".\"c\"",
            ],
            'across lines' => ["\$\$x.y\nt.c\$\$ = t.c", "\$\$x.y\nt.c\$\$ = \"t\".\"c\""],
            'two strings' => [
                '$$a.b$$ || t.c || $$d.e$$',
                '$$a.b$$ || "t"."c" || $$d.e$$',
            ],
            'beside quoted' => [
                "'a.b' || \$\$c.d\$\$ || \"x.y\" || t.c",
                "'a.b' || \$\$c.d\$\$ || \"x.y\" || \"t\".\"c\"",
            ],
            // a $ inside an identifier does not open a dollar quote
            'dollar in identifier' => ['a$$b + t.c + c$$d', 'a$$b + "t"."c" + c$$d'],
            'dollar after non-ASCII' => ['aé$$b + t.c + c$$d', 'aé$$b + "t"."c" + c$$d'],
            // nor does a positional parameter
            'positional parameter' => ['t.c = $1', '"t"."c" = $1'],
        ];
    }

    public function testQueryFactoryUsesIt()
    {
        $select = (new QueryFactory('pgsql'))->newSelect()
            ->cols(['$$x.y$$ AS lit'])
            ->from('t')
            ->where('a = $$t.c$$');

        $expect = 'SELECT $$x.y$$ AS "lit" FROM "t" WHERE a = $$t.c$$';
        $actual = preg_replace('/\s+/', ' ', $select->getStatement());
        $this->assertSame($expect, $actual);
    }
}
