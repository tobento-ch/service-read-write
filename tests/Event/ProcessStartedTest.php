<?php

/**
 * TOBENTO
 *
 * @copyright   Tobias Strub, TOBENTO
 * @license     MIT License, see LICENSE file distributed with this source code.
 * @author      Tobias Strub
 * @link        https://www.tobento.ch
 */

declare(strict_types=1);

namespace Tobento\Service\ReadWrite\Test\Event;

use Exception;
use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Event\ProcessStarted;
use Tobento\Service\ReadWrite\NullResult;

class ProcessStartedTest extends TestCase
{
    public function testEvent()
    {
        $result = new NullResult();
        $event = new ProcessStarted(result: $result);
        
        $this->assertSame($result, $event->result());
    }
}