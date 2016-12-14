<?php

declare(strict_types=1); // @codeCoverageIgnore

namespace Recoil\Kernel;

use Eloquent\Phony\Phony;
use Throwable;

describe(StrandWaitAll::class, function () {
    beforeEach(function () {
        $this->api = Phony::mock(Api::class);

        $this->strand = Phony::mock(SystemStrand::class);

        $this->substrand1 = Phony::mock(SystemStrand::class);
        $this->substrand1->id->returns(1);

        $this->substrand2 = Phony::mock(SystemStrand::class);
        $this->substrand2->id->returns(2);

        $this->subject = new StrandWaitAll(
            $this->substrand1->get(),
            $this->substrand2->get()
        );

        $this->subject->await(
            $this->strand->get(),
            $this->api->get()
        );
    });

    describe('->await()', function () {
        it('resumes the strand when all substrands succeed', function () {
            $this->substrand1->setPrimaryListener->calledWith($this->subject);
            $this->substrand2->setPrimaryListener->calledWith($this->subject);

            $this->subject->send('<two>', $this->substrand2->get());

            $this->strand->send->never()->called();
            $this->strand->throw->never()->called();

            $this->subject->send('<one>', $this->substrand1->get());

            $this->strand->send->calledWith(
                [
                    1 => '<two>',
                    0 => '<one>',
                ]
            );
        });

        it('resumes the strand with an exception when any substrand fails', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->get(), $this->substrand1->get());

            $this->strand->throw->calledWith($exception, $this->substrand1->get());
        });

        it('terminates unused substrands', function () {
            $exception = Phony::mock(Throwable::class);
            $this->subject->throw($exception->get(), $this->substrand1->get());

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called(),
                $this->strand->throw->called()
            );
        });

        it('terminates remaining substrands when the calling strand is terminated', function () {
            $fn = $this->strand->setTerminator->called()->firstCall()->argument();
            expect($fn)->to->satisfy('is_callable');

            $this->subject->send('<one>', $this->substrand1->get());

            $fn();

            $this->substrand1->setPrimaryListener->never()->calledWith(null);
            $this->substrand1->terminate->never()->called();

            Phony::inOrder(
                $this->substrand2->clearPrimaryListener->called(),
                $this->substrand2->terminate->called()
            );
        });
    });
});
