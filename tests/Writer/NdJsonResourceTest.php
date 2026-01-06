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

namespace Tobento\Service\ReadWrite\Test\Writer;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Writer\NdJsonResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;
use Tobento\Service\ReadWrite\Row\Row;

class NdJsonResourceTest extends TestCase
{
    public function testStartOpensResource(): void
    {
        $resource = new InMemory();
        $writer = new NdJsonResource($resource);

        $writer->start();

        $this->assertTrue($resource->isOpen());
    }

    public function testStartThrowsIfResourceAlreadyOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $resource->open();

        $writer = new NdJsonResource($resource);
        $writer->start();
    }

    public function testWriteSingleRow(): void
    {
        $resource = new InMemory();
        $writer = new NdJsonResource($resource);

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice', 'age' => 30]));

        $expected = '{"name":"Alice","age":30}' . "\n";

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteMultipleRows(): void
    {
        $resource = new InMemory();
        $writer = new NdJsonResource($resource);

        $writer->start();
        $writer->write(new Row(1, ['a' => 1]));
        $writer->write(new Row(2, ['a' => 2]));

        $expected =
            '{"a":1}' . "\n" .
            '{"a":2}' . "\n";

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new NdJsonResource($resource);

        $writer->write(new Row(1, ['x' => 1]));
    }

    public function testFinishClosesResource(): void
    {
        $resource = new InMemory();
        $writer = new NdJsonResource($resource);

        $writer->start();
        $this->assertTrue($resource->isOpen());

        $writer->finish();
        $this->assertFalse($resource->isOpen());
    }

    public function testFinishThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new NdJsonResource($resource);

        $writer->finish();
    }
}