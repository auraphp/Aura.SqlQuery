<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/mit-license.php MIT
 *
 */
namespace Aura\SqlQuery\Common;

use Aura\SqlQuery\AbstractQueryTest;

class DeleteTest extends AbstractQueryTest
{
    use WithTestTrait;

    protected $query_type = 'delete';

    protected function withBody()
    {
        $this->query
            ->from('t1')
            ->where('id IN (SELECT c1 FROM cte)');
        return '
            DELETE FROM <<t1>>
            WHERE
                id IN (SELECT c1 FROM cte)
        ';
    }

    /**
     *
     * `DELETE FROM a, b` is not valid anywhere -- MySQL's multi-table delete
     * is `DELETE a, b FROM a JOIN b`, a different shape altogether. The list
     * used to be quoted whole as <<t1,>> <<t2>>, so the statement failed at
     * execute time with nothing to say why. See #160.
     *
     */
    public function testFromRejectsMultipleTables()
    {
        $this->expectException(\Aura\SqlQuery\Exception\LogicException::class);
        $this->expectExceptionMessage('one table');
        $this->query->from('t1, t2');
    }

    /**
     *
     * A comma inside a quoted identifier is part of the name, so this is one
     * table and must not be refused as a list.
     *
     */
    public function testFromAcceptsAQuotedComma()
    {
        $name = $this->query->getQuoteNamePrefix()
              . 'odd,name'
              . $this->query->getQuoteNameSuffix();

        $this->query->from($name);

        $this->assertSameSql("DELETE FROM {$name}", $this->query->__toString());
    }

    public function testCommon()
    {
        $this->query->from('t1')
                    ->where('foo = :foo', ['foo' => 'bar'])
                    ->where('baz = :baz', ['baz' => 'dib'])
                    ->orWhere('zim = gir');

        $actual = $this->query->__toString();
        $expect = "
            DELETE FROM <<t1>>
            WHERE
                foo = :foo
                AND baz = :baz
                OR zim = gir
        ";

        $this->assertSameSql($expect, $actual);

        $actual = $this->query->getBindValues();
        $expect = [
            'foo' => 'bar',
            'baz' => 'dib',
        ];
        $this->assertSame($expect, $actual);
    }
}
