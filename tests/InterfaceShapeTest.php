<?php
namespace Aura\SqlQuery;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 *
 * The parts of the interfaces and properties a subclass or implementation
 * depends on, which cannot change after 7.0 without breaking it.
 *
 */
class InterfaceShapeTest extends TestCase
{
    public function testJoinTakesBindValuesThroughTheInterface()
    {
        $select = (new QueryFactory('sqlite'))->newSelect();
        $this->assertInstanceOf(Common\SelectInterface::class, $select);

        $select->cols(['*'])
            ->from('t')
            ->join('LEFT', 'u', 'u.id = t.id AND u.kind = :kind', ['kind' => 'a'])
            ->joinSubSelect('INNER', 'SELECT id FROM v', 'v', 'v.id = t.id AND v.n > :n', ['n' => 3]);

        $this->assertSame(['kind' => 'a', 'n' => 3], $select->getBindValues());

        foreach (['join' => 3, 'joinSubSelect' => 4] as $method => $position) {
            $param = (new \ReflectionMethod(Common\SelectInterface::class, $method))->getParameters()[$position];
            $this->assertSame('bind', $param->getName());
        }
    }

    public function testResetReturnsTheQuery()
    {
        $select = (new QueryFactory('sqlite'))->newSelect()->cols(['a'])->from('t');
        $this->assertSame($select, $select->reset());
    }

    public function testResetBindValuesIsPartOfQueryInterface()
    {
        $this->assertTrue((new ReflectionClass(QueryInterface::class))->hasMethod('resetBindValues'));
    }

    public function testEveryPropertyButTheBuilderHasANativeType()
    {
        $untyped = [];
        foreach (glob(dirname(__DIR__) . '/src/{,*/}*.php', GLOB_BRACE) as $file) {
            $class = 'Aura\\SqlQuery\\' . str_replace(['/', '.php'], ['\\', ''], substr($file, strlen(dirname(__DIR__) . '/src/')));
            if (! class_exists($class) && ! trait_exists($class)) {
                continue;
            }
            foreach ((new ReflectionClass($class))->getProperties() as $property) {
                if ($property->getDeclaringClass()->getName() !== $class) {
                    continue;
                }
                if ($property->getName() === 'builder') {
                    // narrowed by each query class in its docblock, which a
                    // native type -- invariant in PHP -- would not allow
                    continue;
                }
                if (! $property->getType() instanceof \ReflectionType) {
                    $untyped[] = "{$class}::\${$property->getName()}";
                }
            }
        }

        $this->assertSame([], $untyped);
    }
}
