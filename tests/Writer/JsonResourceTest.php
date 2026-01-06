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
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\JsonResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;

class JsonResourceTest extends TestCase
{
    public function testStartWritesOpeningBracketInOverwriteMode(): void
    {
        $resource = new InMemory();
        $writer = new JsonResource($resource);

        $writer->start();

        $this->assertSame('[', $resource->getContent());
    }

    public function testStartThrowsIfResourceAlreadyOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $resource->open(); // already open

        $writer = new JsonResource($resource);
        $writer->start();
    }

    public function testWriteSingleRow(): void
    {
        $resource = new InMemory();
        $writer = new JsonResource($resource);

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice', 'age' => 30]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $expected = '[' . json_encode(['name' => 'Alice', 'age' => 30], JSON_UNESCAPED_UNICODE) . ']';

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteMultipleRowsAddsCommas(): void
    {
        $resource = new InMemory();
        $writer = new JsonResource($resource);

        $writer->start();
        $writer->write(new Row(1, ['a' => 1]));
        $writer->write(new Row(2, ['a' => 2]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $expected =
            '[' .
            json_encode(['a' => 1], JSON_UNESCAPED_UNICODE) .
            ',' .
            json_encode(['a' => 2], JSON_UNESCAPED_UNICODE) .
            ']';

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new JsonResource($resource);

        $writer->write(new Row(1, ['x' => 1])); // not open
    }

    public function testFinishClosesResourceAndRewinds(): void
    {
        $resource = new InMemory();
        $writer = new JsonResource($resource);

        $writer->start();
        $writer->write(new Row(1, ['x' => 1]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $this->assertFalse($resource->isOpen());
        $this->assertSame(
            '[' . json_encode(['x' => 1], JSON_UNESCAPED_UNICODE) . ']',
            $resource->getContent()
        );
    }

    public function testAppendModeDoesNotWriteOpeningBracket(): void
    {
        $resource = new InMemory();

        // First batch
        $writer1 = new JsonResource($resource);
        $writer1->start();
        $writer1->write(new Row(1, ['a' => 1]));
        $writer1->mode(Mode::Append);
        $writer1->finish();

        // Second batch (append, then finalize)
        $writer2 = new JsonResource($resource);
        $writer2->mode(Mode::Append);
        $writer2->start();
        $writer2->write(new Row(2, ['a' => 2]));
        $writer2->mode(Mode::Finalize);
        $writer2->finish();

        $expected =
            '[' .
            json_encode(['a' => 1], JSON_UNESCAPED_UNICODE) .
            ',' .
            json_encode(['a' => 2], JSON_UNESCAPED_UNICODE) .
            ']';

        $this->assertSame($expected, $resource->getContent());
    }

    public function testFinishThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new JsonResource($resource);

        $writer->finish(); // not open
    }
}