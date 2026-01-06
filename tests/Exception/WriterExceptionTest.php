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
use Tobento\Service\ReadWrite\Exception\WriterException;

class WriterExceptionTest extends TestCase
{
    public function testException()
    {
        $e = new WriterException(message: 'msg');
        
        $this->assertInstanceof(\RuntimeException::class, $e);
        $this->assertSame('msg', $e->getMessage());
    }
}