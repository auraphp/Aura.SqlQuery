<?php
namespace Aura\SqlQuery;

use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testIsLogicException()
    {
        // issue #151: package exceptions are developer errors, so they
        // should be catchable (or ignorable) as \LogicException
        $e = new Exception('message');
        $this->assertInstanceOf(\LogicException::class, $e);
    }

    public function testImplementsExceptionInterface()
    {
        $e = new Exception('message');
        $this->assertInstanceOf(ExceptionInterface::class, $e);
    }

    public function testLegacyCatchesStillWork()
    {
        // BC: catch (\Exception) and catch (Aura\SqlQuery\Exception)
        // must both keep working
        $e = new Exception('message');
        $this->assertInstanceOf(\Exception::class, $e);
        $this->assertInstanceOf(Exception::class, $e);
    }
}
