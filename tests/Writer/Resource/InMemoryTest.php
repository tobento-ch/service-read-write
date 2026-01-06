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

namespace Tobento\Service\ReadWrite\Test\Writer\Resource;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;
use Tobento\Service\ReadWrite\Exception\WriterException;

class InMemoryTest extends TestCase
{
    public function testOpenMarksResourceAsOpen(): void
    {
        $writer = new InMemory();

        $this->assertFalse($writer->isOpen());

        $writer->open();

        $this->assertTrue($writer->isOpen());
    }

    public function testWriteAppendsData(): void
    {
        $writer = new InMemory();

        $writer->open();
        $writer->write("Hello");
        $writer->write(" World");

        $this->assertSame("Hello World", $writer->getContent());
    }

    public function testWriteThrowsIfNotOpen(): void
    {
        $this->expectException(WriterException::class);
        $this->expectExceptionMessage('Resource not open');

        $writer = new InMemory();
        $writer->write("Hello");
    }

    public function testRewindDoesNotModifyContent(): void
    {
        $writer = new InMemory();

        $writer->open();
        $writer->write("ABC");

        $writer->rewind(); // no-op

        $this->assertSame("ABC", $writer->getContent());
    }

    public function testCloseMarksResourceAsClosed(): void
    {
        $writer = new InMemory();

        $writer->open();
        $writer->write("Data");

        $writer->close();

        $this->assertFalse($writer->isOpen());
    }

    public function testGetHandleAlwaysReturnsNull(): void
    {
        $writer = new InMemory();

        $this->assertNull($writer->getHandle());

        $writer->open();
        $this->assertNull($writer->getHandle());

        $writer->close();
        $this->assertNull($writer->getHandle());
    }

    public function testContentPersistsAfterClose(): void
    {
        $writer = new InMemory();

        $writer->open();
        $writer->write("Persist me");
        $writer->close();

        $this->assertSame("Persist me", $writer->getContent());
    }
}