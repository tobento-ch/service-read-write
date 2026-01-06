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
use Tobento\Service\ReadWrite\Writer\Resource\FileStorage;
use Tobento\Service\FileStorage\StorageInterface;
use Tobento\Service\FileStorage\FileWriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;

class FileStorageTest extends TestCase
{
    protected function createStorageMock(): StorageInterface
    {
        return $this->createStub(StorageInterface::class);
    }

    public function testOpenCreatesHandle(): void
    {
        $storage = $this->createStorageMock();
        $writer = new FileStorage($storage, 'file.txt');

        $writer->open();

        $this->assertTrue($writer->isOpen());
        $this->assertIsResource($writer->getHandle());
    }

    public function testWriteWritesToStream(): void
    {
        $storage = $this->createStorageMock();
        $writer = new FileStorage($storage, 'file.txt');

        $writer->open();
        $writer->write("Hello");

        $writer->rewind();
        $content = stream_get_contents($writer->getHandle());

        $this->assertSame("Hello", $content);
    }

    public function testWriteThrowsIfNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $storage = $this->createStorageMock();
        $writer = new FileStorage($storage, 'file.txt');

        $writer->write("Hello");
    }

    public function testRewindRewindsStream(): void
    {
        $storage = $this->createStorageMock();
        $writer = new FileStorage($storage, 'file.txt');

        $writer->open();
        $writer->write("ABC");
        $writer->rewind();

        $this->assertSame("ABC", stream_get_contents($writer->getHandle()));
    }

    public function testRewindThrowsIfNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $storage = $this->createStorageMock();
        $writer = new FileStorage($storage, 'file.txt');

        $writer->rewind();
    }

    public function testCloseCommitsToStorage(): void
    {
        // MUST be a mock, not a stub
        $storage = $this->createMock(StorageInterface::class);

        $storage->expects($this->once())
            ->method('write')
            ->with(
                $this->equalTo('file.txt'),
                $this->callback(fn($arg) => is_resource($arg))
            );

        $writer = new FileStorage($storage, 'file.txt');

        $writer->open();
        $writer->write("DATA");

        $writer->close();

        $this->assertFalse($writer->isOpen());
        $this->assertFalse($writer->getHandle());
    }

    public function testCloseThrowsWriterExceptionOnStorageFailure(): void
    {
        $storage = $this->createStorageMock();

        $storage->method('name')->willReturn('mock-storage');

        $storage->method('write')
            ->willThrowException(new FileWriteException('fail', 'file.txt'));

        $writer = new FileStorage($storage, 'file.txt');

        $writer->open();
        $writer->write("DATA");

        $this->expectException(WriterException::class);
        $this->expectExceptionMessage('Failed to write to storage mock-storage file.txt');

        $writer->close();
    }

    public function testCloseDoesNothingIfNotOpen(): void
    {
        $storage = $this->createMock(StorageInterface::class);

        // write() must NOT be called
        $storage->expects($this->never())->method('write');

        $writer = new FileStorage($storage, 'file.txt');

        $writer->close();

        $this->assertFalse($writer->isOpen());
    }
}