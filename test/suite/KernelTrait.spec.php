<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Closure;
use Eloquent\Phony\Phony;
use Exception;
use Hamcrest\Core\IsInstanceOf;
use Recoil\Exception\KernelException;
use Recoil\Exception\StrandException;
use Recoil\Exception\TerminatedException;

abstract class MockKernel implements SystemKernel {
    use KernelTrait;

    static $instance;
    static $args;

    static function create(...$args): self {
        self::$args = $args;
        return self::$instance;
    }
}

describe(KernelTrait::class, function () {
    beforeEach(function () {
        $this->subject = Phony::partialMock(MockKernel::class);
        MockKernel::$instance = $this->subject->get();

        $this->strand = Phony::mock(SystemStrand::class);
        $this->strand->kernel->returns($this->subject);
    });

    describe('::start()', function () {
        beforeEach(function () {
            $this->subject->execute->returns($this->strand);
            $this->strand->setPrimaryListener->does(function ($listener) {
                $listener->send('<value>', $this->strand->get());
            });
        });

        it('forwards arguments to create()', function () {
            $fn = [$this->subject->className(), 'start'];
            $fn('<coroutine>', 1, 2, 3);

            expect(MockKernel::$args)->to->equal([1, 2, 3]);
        });

        it('uses a MainStrandListener', function () {
            $fn = [$this->subject->className(), 'start'];
            $fn('<coroutine>', 1, 2, 3);

            $this->strand->setPrimaryListener->calledWith(
                IsInstanceOf::anInstanceOf(MainStrandListener::class)
            );
        });

        it('returns the captured value', function () {
            $fn = [$this->subject->className(), 'start'];
            $value = $fn('<coroutine>', 1, 2, 3);

            expect($value)->to->equal('<value>');
        });
    });

    describe('->run()', function () {
        it('calls loop()', function () {
            $this->subject->get()->run();
            $this->subject->loop->once()->called();
        });

        it('exits immediately if already running', function () {
            $this->subject->loop->does(function () {
                $this->subject->get()->run();
            });

            $this->subject->get()->run();
            $this->subject->loop->once()->called();
        });

        it('handles exceptions thrown from loop()', function () {
            $handler = Phony::spy();
            $this->subject->get()->setExceptionHandler($handler);

            $exception = new Exception();
            $this->subject->loop->throws($exception);

            $this->subject->get()->run();

            $handler->calledWith(
                KernelException::create($exception)
            );
        });

        context('when there are unhandled exceptions', function () {
            it('throws before calling loop()', function () {
                $exception = new Exception();
                $this->subject->get()->throw($exception);

                try {
                    $this->subject->get()->run();
                } catch (KernelException $e) {
                    assert($e->getPrevious() === $exception);
                }
            });

            it('throws after calling loop()', function () {
                $exception = new Exception();
                $this->subject->loop->throws($exception);

                try {
                    $this->subject->get()->run();
                } catch (KernelException $e) {
                    assert($e->getPrevious() === $exception);
                }
            });
        });
    });

    describe('->stop()', function () {
        it('sets the stopping state', function () {
            $this->subject->loop->does(Closure::bind(
                function () {
                    $this->stop();
                    expect($this->state)->to->equal(KernelState::STOPPING);
                },
                $this->subject->get(),
                MockKernel::class
            ));

            $this->subject->get()->run();
        });
    });

    describe('->send()', function () {
        it('accepts strands from this kernel', function () {
            $this->subject->get()->send('<value>', $this->strand->get());
        });
    });

    describe('->throw()', function () {
        context('when there is an exception handler', function () {
            beforeEach(function () {
                $this->handler = Phony::stub();
                $this->subject->get()->setExceptionHandler($this->handler);
            });

            it('does not stop the kernel if the handler returns', function () {
                $this->subject->get()->throw(new Exception(), $this->strand->get());

                $this->subject->stop->never()->called();
            });

            it('wraps exceptions from strands in StrandException', function () {
                $exception = new Exception();
                $this->subject->get()->throw($exception, $this->strand->get());

                $this->handler->calledWith(
                    StrandException::create($this->strand->get(), $exception)
                );
            });

            it('wraps exceptions from the kernel in KernelException', function () {
                $exception = new Exception();
                $this->subject->get()->throw($exception);

                $this->handler->calledWith(
                    KernelException::create($exception)
                );
            });

            it('stops the kernel if the handler throws a panic exception', function () {
                $this->handler->does(function ($exception) {
                    throw $exception;
                });

                $exception = new Exception();
                $this->subject->get()->throw($exception, $this->strand->get());

                $this->subject->stop->called();
            });

            it('stops the kernel if the handler throws any other exception', function () {
                $this->handler->throws(new Exception());

                $exception = new Exception();
                $this->subject->get()->throw($exception, $this->strand->get());

                $this->subject->stop->called();
            });
        });

        context('when there is an no handler', function () {
            it('stops the kernel unconditionally', function () {
                $exception = new Exception();
                $this->subject->get()->throw($exception, $this->strand->get());
                $this->subject->stop->called();
            });
        });

        it('ignores terminated exceptions from the current strand', function () {
            $exception = TerminatedException::create($this->strand->get());
            $this->subject->get()->throw($exception, $this->strand->get());
            $this->subject->stop->never()->called();
        });

        it('does not ignore terminated exceptions from other strands', function () {
            $strand = Phony::mock(SystemStrand::class);
            $strand->kernel->returns($this->subject);

            $exception = TerminatedException::create($this->strand->get());
            $this->subject->get()->throw($exception, $strand->get());
            $this->subject->stop->called();
        });
    });
});
