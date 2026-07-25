<?php
namespace Aura\SqlQuery;

use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    /**
     * issue #151: package exceptions are developer errors, so they
     * should be catchable (or ignorable) as \LogicException
     */
    public function testAllAreLogicExceptions()
    {
        $exceptions = array(
            new Exception\LogicException('message'),
            new Exception\BadMethodCallException('message'),
            new Exception\InvalidArgumentException('message'),
        );
        foreach ($exceptions as $e) {
            $this->assertInstanceOf(\LogicException::class, $e);
        }
    }

    /**
     * catch (Aura\SqlQuery\ExceptionInterface $e) catches every exception
     * thrown by this package, regardless of its SPL base class; the
     * deprecated Aura\SqlQuery\Exception must keep working until 7.x
     */
    public function testAllImplementMarkerInterface()
    {
        $exceptions = array(
            new Exception\LogicException('message'),
            new Exception\BadMethodCallException('message'),
            new Exception\InvalidArgumentException('message'),
        );
        foreach ($exceptions as $e) {
            $this->assertInstanceOf(ExceptionInterface::class, $e);
            $this->assertInstanceOf(Exception::class, $e);
        }
    }
}
