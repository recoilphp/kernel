<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Exception;
use Recoil\Kernel;
use Recoil\Kernel\Exception\KernelStoppedException;
use Recoil\Strand;

describe(MainStrandListener::class, function () {
    beforeEach(function () {
        $this->kernel = Phony::mock(SystemKernel::class);
        $this->strand = Phony::mock(SystemStrand::class);
        $this->strand->kernel->returns($this->kernel);

        $this->subject = new MainStrandListener();
    });

    describe('->send()', function () {
        it('stops the kernel', function () {
            $this->subject->send('<value>', $this->strand->get());

            $this->kernel->stop->called();
        });
    });

    describe('->throw()', function () {
        it('stops the kernel', function () {
            $this->subject->throw(new Exception(), $this->strand->get());

            $this->kernel->stop->called();
        });
    });

    describe('->get()', function () {
        it('returns the strand result', function () {
            $this->subject->send('<value>', $this->strand->get());

            expect($this->subject->get())->to->equal('<value>');
        });

        it('rethrows the strand exception if execution fails', function () {
            $exception = new Exception();
            $this->subject->throw($exception, $this->strand->get());

            try {
                $this->subject->get();
                assert(false, 'expected exception was not thrown');
            } catch (Exception $e) {
                assert($e === $exception, 'expected exception was not thrown');
            }
        });

        it('throws a KernelStoppedException if the strand has not exited', function () {
            try {
                $this->subject->get();
            } catch (KernelStoppedException $e) {
                return;
            }

            assert(false, 'expected exception was not thrown');
        });
    });
});
