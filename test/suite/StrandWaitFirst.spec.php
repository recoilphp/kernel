<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Throwable;

describe(StrandWaitFirst::class, function () {
    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(SystemStrand::class);

        $this->substrand1 = Phony::mock(SystemStrand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(SystemStrand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitFirst(
            $this->substrand1->get(),
            $this->substrand2->get()
        );

        $this->subject->await(
            $this->strand->get(),
            $this->api->get()
        );
    });

    describe('->await()', function () {
        it('resumes the strand when any substrand succeeds', function () {
            $this->substrand1->setPrimaryListener->calledWith($this->subject);
            $this->substrand2->setPrimaryListener->calledWith($this->subject);

            $this->subject->send('<one>', $this->substrand1->get());

            $this->strand->send->calledWith('<one>', $this->substrand1->get());
        });

        it('resumes the strand with an exception when any substrand fails', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->get(), $this->substrand1->get());

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called(),
                $this->strand->throw->calledWith($exception, $this->substrand1->get())
            );
        });

        it('terminates unused substrands', function () {
            $this->subject->send('<one>', $this->substrand1->get());

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called(),
                $this->strand->send->called()
            );
        });

        it('terminates remaining substrands when the calling strand is terminated', function () {
            $fn = $this->strand->setTerminator->called()->firstCall()->argument();
            expect($fn)->to->satisfy('is_callable');

            $fn();

            Phony::inOrder(
                $this->substrand1->clearPrimaryListener->called(),
                $this->substrand1->terminate->called()
            );

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called()
            );
        });

        it('does not terminate strands more than once', function () {
            $fn = $this->strand->setTerminator->called()->firstCall()->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->subject->send('<one>', $this->substrand1->get());

            $fn();

            $this->substrand1->clearPrimaryListener->never()->called();
            $this->substrand1->terminate->never()->called();

            $this->substrand2->clearPrimaryListener->once()->called();
            $this->substrand2->terminate->once()->called();
        });
    });
});
