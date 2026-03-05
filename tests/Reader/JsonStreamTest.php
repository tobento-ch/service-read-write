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

namespace Tobento\Service\ReadWrite\Test\Reader;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Reader\JsonStream;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use GuzzleHttp\Psr7\Utils;

class JsonStreamTest extends TestCase
{
    protected function createStream(string $json)
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $json);
        rewind($resource);

        return Utils::streamFor($resource);
    }

    public function testGetterMethods(): void
    {
        $json = <<<JSON
[
    {"id": 1, "name": "Alice"},
    {"id": 2, "name": "Bob"}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);
        
        $this->assertTrue(is_resource($reader->resource()));
        $this->assertSame(3, $reader->previewRows());
    }
    
    public function testColumns(): void
    {
        $json = <<<JSON
[
    {"id": 1, "name": "Alice"},
    {"id": 2, "name": "Bob"}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $this->assertSame(['id', 'name'], $reader->columns());
    }

    public function testColumnsPreviewAggregatesValues(): void
    {
        $json = <<<JSON
[
    {"id": 1, "name": "Alice"},
    {"id": 2, "name": "Bob"},
    {"id": 3, "name": "Alice"}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream, previewRows: 3);

        $this->assertSame(
            [
                'id'   => '1 | 2 | 3',
                'name' => 'Alice | Bob',
            ],
            $reader->columnsPreview()
        );
    }

    public function testColumnsPreviewSkipsInvalidRows(): void
    {
        $json = <<<JSON
[
    {"id": 1, "name": "Alice"},
    "INVALID",
    {"id": 2, "name": "Bob"}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream, previewRows: 3);

        $this->assertSame(
            [
                'id'   => '1 | 2',
                'name' => 'Alice | Bob',
            ],
            $reader->columnsPreview()
        );
    }

    public function testReadsRows(): void
    {
        $json = <<<JSON
[
    {"id": 1},
    {"id": 2}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testInvalidValueProducesSkipRow(): void
    {
        $json = <<<JSON
[
    {"id": 1},
    "INVALID",
    {"id": 2}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertInstanceOf(SkipRow::class, $rows[1]);
        $this->assertInstanceOf(Row::class, $rows[2]);
    }

    public function testOffsetReading(): void
    {
        $json = <<<JSON
[
    {"id": 1},
    {"id": 2},
    {"id": 3}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $rows = iterator_to_array($reader->read(offset: 1));

        $this->assertCount(2, $rows);
        $this->assertSame(['id' => 2], $rows[0]->all());
        $this->assertSame(['id' => 3], $rows[1]->all());
    }

    public function testLimitReading(): void
    {
        $json = <<<JSON
[
    {"id": 1},
    {"id": 2},
    {"id": 3}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $rows = iterator_to_array($reader->read(limit: 1));

        $this->assertCount(1, $rows);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testCurrentOffsetUpdates(): void
    {
        $json = <<<JSON
[
    {"id": 1},
    {"id": 2}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        iterator_to_array($reader->read());

        $this->assertSame(1, $reader->currentOffset());
    }

    public function testIsFinished(): void
    {
        $json = <<<JSON
[
    {"id": 1}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $this->assertFalse($reader->isFinished());

        iterator_to_array($reader->read());

        $this->assertTrue($reader->isFinished());
    }

    public function testTotalRows(): void
    {
        $json = <<<JSON
[
    {"id": 1},
    {"id": 2}
]
JSON;

        $stream = $this->createStream($json);
        $reader = new JsonStream($stream);

        $this->assertSame(2, $reader->totalRows());
    }
}