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

namespace Tobento\Service\ReadWrite\Test\Row;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkippableInterface;
use Tobento\Service\Collection\Collection;

class SkipRowTest extends TestCase
{
    public function testItImplementsSkippableInterface(): void
    {
        $row = new SkipRow(1, ['id' => 1], 'Invalid JSON');
        $this->assertInstanceOf(SkippableInterface::class, $row);
    }

    public function testKeyIsStored(): void
    {
        $row = new SkipRow('abc', ['id' => 1], 'Reason');
        $this->assertSame('abc', $row->key());
    }

    public function testAttributesAreStored(): void
    {
        $row = new SkipRow(1, ['id' => 1, 'name' => 'Alice'], 'Reason');
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $row->all());
    }

    public function testReasonIsStored(): void
    {
        $row = new SkipRow(1, ['id' => 1], 'Invalid JSON');
        $this->assertSame('Invalid JSON', $row->reason());
    }

    public function testHasAndGetWorkLikeRow(): void
    {
        $row = new SkipRow(1, ['name' => 'Alice'], 'Reason');

        $this->assertTrue($row->has('name'));
        $this->assertSame('Alice', $row->get('name'));
        $this->assertSame('default', $row->get('missing', 'default'));
    }

    public function testCollectionReturnsCollection(): void
    {
        $row = new SkipRow(1, ['id' => 1], 'Reason');
        $collection = $row->collection();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame(['id' => 1], $collection->toArray());
    }

    public function testIteratorYieldsAttributes(): void
    {
        $row = new SkipRow(1, ['id' => 1, 'name' => 'Alice'], 'Reason');

        $result = [];
        foreach ($row as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result);
    }

    public function testToArray(): void
    {
        $row = new SkipRow(1, ['id' => 1, 'name' => 'Alice'], 'Reason');
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $row->toArray());
    }

    public function testToJson(): void
    {
        $row = new SkipRow(1, ['id' => 1, 'name' => 'Alice'], 'Reason');
        $json = $row->toJson();

        $this->assertJson($json);
        $this->assertSame('{"id":1,"name":"Alice"}', $json);
    }

    public function testCount(): void
    {
        $row = new SkipRow(1, ['a' => 1, 'b' => 2], 'Reason');
        $this->assertCount(2, $row);
    }

    public function testArrayAccess(): void
    {
        $row = new SkipRow(1, ['id' => 1], 'Reason');

        $this->assertTrue(isset($row['id']));
        $this->assertSame(1, $row['id']);

        $row['name'] = 'Alice';
        $this->assertSame('Alice', $row['name']);

        unset($row['id']);
        $this->assertFalse(isset($row['id']));
    }

    public function testOffsetSetThrowsExceptionForNonStringOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $row = new SkipRow(1, ['id' => 1], 'Reason');
        $row[0] = 'invalid';
    }
}