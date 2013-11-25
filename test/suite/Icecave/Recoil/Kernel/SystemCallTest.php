<?php
namespace Icecave\Recoil\Kernel;

use BadMethodCallException;
use Exception;
use Icecave\Recoil\Recoil;
use Phake;
use PHPUnit_Framework_TestCase;

class SystemCallTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->api = Phake::partialMock(KernelApi::CLASS);
        $this->kernel = new Kernel($this->api);
    }

    public function testTick()
    {
        $coroutine = function () {
            yield Recoil::return_(123);
        };

        $strand = $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();

        Phake::verify($this->api)->return_($this->identicalTo($strand), 123);
    }

    public function testTickFailure()
    {
        $coroutine = function () {
            try {
                yield Recoil::foo();
                $this->fail('Expected exception was not thrown.');
            } catch (BadMethodCallException $e) {
                $this->assertSame(
                    'Kernel API does not support the "foo" system-call.',
                    $e->getMessage()
                );
            }
        };

        $this->kernel->execute($coroutine());

        $this->kernel->eventLoop()->run();
    }
}
