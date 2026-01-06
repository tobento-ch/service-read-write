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
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;

class IterableReaderTest extends TestCase
{
    public function testColumns(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $reader = new IterableReader($data);

        $this->assertSame(['id', 'name'], $reader->columns());
    }

    public function testColumnsPreviewAggregatesValues(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Alice'],
        ];

        $reader = new IterableReader($data, previewRows: 3);

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
        $data = [
            ['id' => 1, 'name' => 'Alice'],
            'INVALID',
            ['id' => 2, 'name' => 'Bob'],
        ];

        $reader = new IterableReader($data, previewRows: 3);

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
        $data = [
            ['id' => 1],
            ['id' => 2],
        ];

        $reader = new IterableReader($data);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testReadsRowInterfaceDirectly(): void
    {
        $row = new Row(key: 0, attributes: ['id' => 1]);

        $reader = new IterableReader([$row]);

        $rows = iterator_to_array($reader->read());

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testInvalidValueProducesSkipRow(): void
    {
        $data = [
            ['id' => 1],
            'INVALID',
            ['id' => 2],
        ];

        $reader = new IterableReader($data);

        $rows = iterator_to_array($reader->read());

        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertInstanceOf(SkipRow::class, $rows[1]);
        $this->assertInstanceOf(Row::class, $rows[2]);
    }

    public function testOffsetReading(): void
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];

        $reader = new IterableReader($data);

        $rows = iterator_to_array($reader->read(offset: 1));

        $this->assertCount(2, $rows);
        $this->assertSame(['id' => 2], $rows[0]->all());
        $this->assertSame(['id' => 3], $rows[1]->all());
    }

    public function testLimitReading(): void
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];

        $reader = new IterableReader($data);

        $rows = iterator_to_array($reader->read(limit: 1));

        $this->assertCount(1, $rows);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testCurrentOffsetUpdates(): void
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
        ];

        $reader = new IterableReader($data);

        iterator_to_array($reader->read());

        $this->assertSame(1, $reader->currentOffset());
    }

    public function testIsFinished(): void
    {
        $data = [
            ['id' => 1],
        ];

        $reader = new IterableReader($data);

        $this->assertFalse($reader->isFinished());

        iterator_to_array($reader->read());

        $this->assertTrue($reader->isFinished());
    }

    public function testTotalRows(): void
    {
        $data = [
            ['id' => 1],
            ['id' => 2],
        ];

        $reader = new IterableReader($data);

        $this->assertSame(2, $reader->totalRows());
    }
}