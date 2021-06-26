<?php

namespace Aura\SqlQuery;

class TestCase extends \PHPUnit\Framework\TestCase
{

    public function setExpectedException($class, $message = '', $exception_code = null)
    {
        if (method_exists($this, 'expectException')) {
            $this
                ->expectException($class);
            if (! empty($message)) {
                $this
                    ->expectExceptionMessage($message);
            }
            if ($exception_code !== null) {
                $this
                    ->expectExceptionCode($exception_code);
            }
        } else {
            parent::setExpectedException($class, $message, $exception_code);
        }
    }
}