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
use Tobento\Service\ReadWrite\Reader\CsvStream;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use GuzzleHttp\Psr7\Utils;

class CsvStreamTest extends TestCase
{
    public function testReadsHeaders(): void
    {
        $csv = "id,name\n1,Alice\n2,Bob\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream, previewRows: 3);

        // Headers
        $this->assertSame(['id', 'name'], $reader->columns());

        // Preview (aggregated from first 3 rows, but only 2 exist)
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
        $csv = "id,name\n1,Alice\n2,Bob\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => '1', 'name' => 'Alice'], $rows[0]->all());

        $this->assertInstanceOf(Row::class, $rows[1]);
        $this->assertSame(['id' => '2', 'name' => 'Bob'], $rows[1]->all());
    }

    public function testReadsQuotedValues(): void
    {
        $csv = "id,name\n1,\"Alice Smith\"\n2,\"Bob, Jr\"\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertSame('Alice Smith', $rows[0]->get('name'));
        $this->assertSame('Bob, Jr', $rows[1]->get('name'));
    }

    public function testInvalidRowProducesSkipRow(): void
    {
        $csv = "id,name\n1,Alice\nINVALID\n2,Bob\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        $rows = iterator_to_array($reader->read());

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertInstanceOf(SkipRow::class, $rows[1]);
        $this->assertInstanceOf(Row::class, $rows[2]);
    }

    public function testOffsetReading(): void
    {
        $csv = "id,name\n1,Alice\n2,Bob\n3,Charlie\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        // Find byte offset of second data row
        $offset = strpos($csv, "2,Bob");

        $rows = iterator_to_array($reader->read(offset: $offset));

        $this->assertCount(2, $rows);
        $this->assertSame(['id' => '2', 'name' => 'Bob'], $rows[0]->all());
        $this->assertSame(['id' => '3', 'name' => 'Charlie'], $rows[1]->all());
    }

    public function testLimitReading(): void
    {
        $csv = "id,name\n1,Alice\n2,Bob\n3,Charlie\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        $rows = iterator_to_array($reader->read(limit: 1));

        $this->assertCount(1, $rows);
        $this->assertSame(['id' => '1', 'name' => 'Alice'], $rows[0]->all());
    }

    public function testRemovesBom(): void
    {
        $csv = "\xEF\xBB\xBFid,name\n1,Alice\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        $this->assertSame(['id', 'name'], $reader->columns());
    }

    public function testIsFinished(): void
    {
        $csv = "id,name\n1,Alice\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        $this->assertFalse($reader->isFinished());

        iterator_to_array($reader->read());

        $this->assertTrue($reader->isFinished());
    }

    public function testCurrentOffsetUpdates(): void
    {
        $csv = "id,name\n1,Alice\n2,Bob\n";
        $stream = Utils::streamFor($csv);

        $reader = new CsvStream($stream);

        iterator_to_array($reader->read());

        // After reading everything, offset should be at end of stream
        $this->assertSame(strlen($csv), $reader->currentOffset());
    }
}