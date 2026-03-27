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
use Tobento\Service\ReadWrite\Reader\NdJsonStream;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use GuzzleHttp\Psr7\Utils;

class NdJsonStreamTest extends TestCase
{
    protected function createStream(string $content)
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, $content);
        rewind($resource);

        return Utils::streamFor($resource);
    }
    
    public function testGetterMethods(): void
    {
        $ndjson = <<<TXT
{"id":1,"name":"Alice"}
{"id":2,"name":"Bob"}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);
        
        $this->assertSame($stream, $reader->stream());
        $this->assertSame(3, $reader->previewRows());
    }

    public function testColumns(): void
    {
        $ndjson = <<<TXT
{"id":1,"name":"Alice"}
{"id":2,"name":"Bob"}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $this->assertSame(['id', 'name'], $reader->columns());
    }

    public function testColumnsPreviewAggregatesValues(): void
    {
        $ndjson = <<<TXT
{"id":1,"name":"Alice"}
{"id":2,"name":"Bob"}
{"id":3,"name":"Alice"}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream, previewRows: 3);

        $this->assertSame(
            [
                'id'   => '1 | 2 | 3',
                'name' => 'Alice | Bob',
            ],
            $reader->columnsPreview()
        );
    }

    public function testColumnsPreviewAggregatesJsonValues(): void
    {
        $ndjson = <<<TXT
    {"id":1,"name":{"first":"Alice","role":"admin"}}
    {"id":2,"name":{"first":"Bob","role":"user"}}
    {"id":3,"name":{"first":"Alice","role":"admin"}}
    TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream, previewRows: 3);

        $preview = $reader->columnsPreview();

        // Scalars still aggregate correctly
        $this->assertSame('1 | 2 | 3', $preview['id']);

        // JSON objects become JSON strings and duplicates are removed
        $this->assertSame(
            json_encode(['first' => 'Alice', 'role' => 'admin']) . ' | ' .
            json_encode(['first' => 'Bob',   'role' => 'user']),
            $preview['name']
        );
    }

    public function testColumnsPreviewSkipsInvalidRows(): void
    {
        $ndjson = <<<TXT
{"id":1,"name":"Alice"}
INVALID
{"id":2,"name":"Bob"}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream, previewRows: 3);

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
        $ndjson = <<<TXT
{"id":1}
{"id":2}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testInvalidJsonProducesSkipRow(): void
    {
        $ndjson = <<<TXT
{"id":1}
INVALID
{"id":2}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertInstanceOf(SkipRow::class, $rows[1]);
        $this->assertInstanceOf(Row::class, $rows[2]);
    }

    public function testOffsetReading(): void
    {
        $ndjson = <<<TXT
{"id":1}
{"id":2}
{"id":3}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        // Offset into second line
        $offset = strpos($ndjson, '{"id":2}');

        $rows = iterator_to_array($reader->read(offset: $offset));

        $this->assertCount(2, $rows);
        $this->assertSame(['id' => 2], $rows[0]->all());
        $this->assertSame(['id' => 3], $rows[1]->all());
    }

    public function testLimitReading(): void
    {
        $ndjson = <<<TXT
{"id":1}
{"id":2}
{"id":3}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read(limit: 1));

        $this->assertCount(1, $rows);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testCurrentOffsetUpdates(): void
    {
        $ndjson = <<<TXT
{"id":1}
{"id":2}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        iterator_to_array($reader->read());

        // Should be offset of second line
        $this->assertGreaterThan(0, $reader->currentOffset());
    }

    public function testIsFinished(): void
    {
        $ndjson = <<<TXT
{"id":1}
TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $this->assertFalse($reader->isFinished());

        iterator_to_array($reader->read());

        $this->assertTrue($reader->isFinished());
    }

    public function testHandlesBom(): void
    {
        $ndjson = "\xEF\xBB\xBF" . "{\"id\":1}\n{\"id\":2}\n";

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertSame(['id' => 1], $rows[0]->all());
        $this->assertSame(['id' => 2], $rows[1]->all());
    }
    
    public function testExtremelyLongLineIsReadCorrectly(): void
    {
        // Create a long JSON line > 20 KB
        $longValue = str_repeat('A', 20000);
        $ndjson = '{"id":1,"data":"' . $longValue . "\"}\n" .
                  "{\"id\":2}\n";

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);
        $this->assertSame($longValue, $rows[0]->all()['data']);
        $this->assertSame(['id' => 2], $rows[1]->all());
    }
    
    public function testOffsetAtNewlineBoundary(): void
    {
        $ndjson = <<<TXT
    {"id":1}
    {"id":2}
    {"id":3}
    TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        // Offset exactly at the newline before {"id":2}
        $offset = strpos($ndjson, "\n") + 1;

        $rows = iterator_to_array($reader->read(offset: $offset));

        $this->assertCount(2, $rows);
        $this->assertSame(['id' => 2], $rows[0]->all());
        $this->assertSame(['id' => 3], $rows[1]->all());
    }
    
    public function testEmptyFile(): void
    {
        $stream = $this->createStream('');
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertSame([], $rows);
        $this->assertTrue($reader->isFinished());
    }

    public function testWhitespaceOnlyFile(): void
    {
        $stream = $this->createStream("   \n   \n");
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertSame([], $rows);
        $this->assertTrue($reader->isFinished());
    }
    
    public function testMalformedJsonInMiddle(): void
    {
        $ndjson = <<<TXT
    {"id":1}
    INVALID_JSON
    {"id":3}
    TXT;

        $stream = $this->createStream($ndjson);
        $reader = new NdJsonStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(3, $rows);

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1], $rows[0]->all());

        $this->assertInstanceOf(SkipRow::class, $rows[1]);

        $this->assertInstanceOf(Row::class, $rows[2]);
        $this->assertSame(['id' => 3], $rows[2]->all());
    }
}