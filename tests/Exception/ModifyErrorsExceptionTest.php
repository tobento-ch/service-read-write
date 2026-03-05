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
use Tobento\Service\Message\Messages;
use Tobento\Service\ReadWrite\Exception\ModifyErrorsException;
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\Row\Row;

class ModifyErrorsExceptionTest extends TestCase
{
    public function testException()
    {
        $row = new Row(key: 1, attributes: []);
        $errors = new Messages();
        $previous = new \RuntimeException();
        $e = new ModifyErrorsException(row: $row, errors: $errors, message: 'msg', code: 1, previous: $previous);
        
        $this->assertInstanceof(\RuntimeException::class, $e);
        $this->assertSame($row, $e->row());
        $this->assertSame($errors, $e->errors());
        $this->assertSame('msg', $e->getMessage());
        $this->assertSame(1, $e->getCode());
        $this->assertSame($previous, $e->getPrevious());
    }
}