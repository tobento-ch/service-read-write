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
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;
use Tobento\Service\ReadWrite\Exception\WriterException;

class LocalFileTest extends TestCase
{
    private function tempFile(): string
    {
        return tempnam(sys_get_temp_dir(), 'rw_');
    }
    
    public function testGetterMethods(): void
    {
        $file = $this->tempFile();
        $writer = new LocalFile($file);
        
        $this->assertSame($file, $writer->filename());
        $this->assertSame('w', $writer->mode());
    }

    public function testOpenCreatesHandle(): void
    {
        $file = $this->tempFile();
        $writer = new LocalFile($file);

        $writer->open();

        $this->assertTrue($writer->isOpen());
        $this->assertIsResource($writer->getHandle());

        $writer->close();
        unlink($file);
    }

    public function testOpenThrowsIfFileCannotBeOpened(): void
    {
        $this->expectException(WriterException::class);

        // Invalid path
        $writer = new LocalFile('/invalid/path/to/file.txt');
        $writer->open();
    }

    public function testWriteWritesToFile(): void
    {
        $file = $this->tempFile();
        $writer = new LocalFile($file);

        $writer->open();
        $writer->write("Hello");
        $writer->write(" World");
        $writer->close();

        $this->assertSame("Hello World", file_get_contents($file));

        unlink($file);
    }

    public function testWriteThrowsIfNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $file = $this->tempFile();
        $writer = new LocalFile($file);

        $writer->write("Hello"); // not open

        unlink($file);
    }

    public function testRewindMovesPointerToStart(): void
    {
        $file = $this->tempFile();
        $writer = new LocalFile($file, 'w+'); // <-- IMPORTANT

        $writer->open();
        $writer->write("ABC");
        $writer->rewind();

        $this->assertSame("ABC", stream_get_contents($writer->getHandle()));

        $writer->close();
        unlink($file);
    }

    public function testRewindThrowsIfNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $file = $this->tempFile();
        $writer = new LocalFile($file);

        $writer->rewind(); // not open

        unlink($file);
    }

    public function testCloseClosesHandle(): void
    {
        $file = $this->tempFile();
        $writer = new LocalFile($file);

        $writer->open();
        $writer->write("Test");
        $writer->close();

        $this->assertFalse($writer->isOpen());
        $this->assertFalse($writer->getHandle());

        unlink($file);
    }

    public function testCloseThrowsIfFcloseFails(): void
    {
        $this->expectException(\TypeError::class);

        $file = $this->tempFile();
        $writer = new LocalFile($file);

        $writer->open();

        // Force fclose() to fail by closing the handle manually
        fclose($writer->getHandle());

        $writer->close();

        unlink($file);
    }
}