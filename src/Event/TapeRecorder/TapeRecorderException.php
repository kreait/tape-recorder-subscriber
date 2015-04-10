<?php

/*
 * This file is part of the tape-recorder-subscriber package.
 *
 * (c) Jérôme Gamez <jerome@kreait.com>
 * (c) kreait GmbH <info@kreait.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Kreait\Ivory\HttpAdapter\Event\TapeRecorder;

use Ivory\HttpAdapter\HttpAdapterException;

/**
 * TapeRecorder Exception.
 *
 * A special type of exception which can be used to intercept a request and set the request's
 * response and exception with recorded values
 *
 * @author Jérôme Gamez <jerome@gamez.name>
 */
class TapeRecorderException extends HttpAdapterException
{
    /**
     * @return TapeRecorderException
     */
    public static function interceptingRequest()
    {
        return new static('Intercepting request.');
    }
}
