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

namespace Tobento\Service\ReadWrite\Test\Modifier;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Modifier\ColumnMap;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class ColumnMapTest extends TestCase
{
    public function testMapsColumnsCorrectly(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(
            key: 1,
            attributes: [
                'title' => 'Hello',
                'status' => 'Draft',
            ]
        );

        $modifier = new ColumnMap([
            'title' => 'headline',
            'status' => 'state',
        ]);

        $result = $modifier->modify($row, $reader, $writer);

        $this->assertSame('Hello', $result->get('headline'));
        $this->assertSame('Draft', $result->get('state'));
        $this->assertFalse($result->has('title'));
        $this->assertFalse($result->has('status'));
    }

    public function testIgnoresMissingColumns(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(
            key: 1,
            attributes: [
                'title' => 'Hello',
            ]
        );

        $modifier = new ColumnMap([
            'title' => 'headline',
            'status' => 'state', // does not exist in row
        ]);

        $result = $modifier->modify($row, $reader, $writer);

        $this->assertSame('Hello', $result->get('headline'));
        $this->assertFalse($result->has('state'));
    }

    public function testKeyIsPreserved(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(key: 99, attributes: ['title' => 'Hello']);

        $modifier = new ColumnMap(['title' => 'headline']);

        $result = $modifier->modify($row, $reader, $writer);

        $this->assertSame(99, $result->key());
    }
    
    public function testThrowsExceptionIfMapIsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ColumnMap([]);
    }
}