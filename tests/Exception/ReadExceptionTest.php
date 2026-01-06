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

namespace Tobento\Service\ReadWrite\Test\Exception;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Exception\ReadException;

class ReadExceptionTest extends TestCase
{
    public function testException()
    {
        $previous = new \RuntimeException();
        $e = new ReadException(offset: 5, limit: 2, message: 'msg', code: 1, previous: $previous);
        
        $this->assertInstanceof(\RuntimeException::class, $e);
        $this->assertSame(5, $e->offset());
        $this->assertSame(2, $e->limit());
        $this->assertSame('msg', $e->getMessage());
        $this->assertSame(1, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}