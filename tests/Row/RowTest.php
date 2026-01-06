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
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\Collection\Collection;

class RowTest extends TestCase
{
    public function testKeyIsReturned(): void
    {
        $row = new Row(5, ['id' => 1]);
        $this->assertSame(5, $row->key());

        $row = new Row('abc', ['id' => 1]);
        $this->assertSame('abc', $row->key());
    }

    public function testHasReturnsTrueForExistingAttribute(): void
    {
        $row = new Row(1, ['name' => 'Alice']);
        $this->assertTrue($row->has('name'));
    }

    public function testHasReturnsFalseForMissingAttribute(): void
    {
        $row = new Row(1, ['name' => 'Alice']);
        $this->assertFalse($row->has('age'));
    }

    public function testGetReturnsValue(): void
    {
        $row = new Row(1, ['name' => 'Alice']);
        $this->assertSame('Alice', $row->get('name'));
    }

    public function testGetReturnsDefaultIfMissing(): void
    {
        $row = new Row(1, ['name' => 'Alice']);
        $this->assertSame('unknown', $row->get('age', 'unknown'));
    }

    public function testAllReturnsAttributes(): void
    {
        $data = ['id' => 1, 'name' => 'Alice'];
        $row = new Row(1, $data);

        $this->assertSame($data, $row->all());
    }

    public function testCollectionReturnsCollectionInstance(): void
    {
        $row = new Row(1, ['id' => 1]);
        $collection = $row->collection();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame(['id' => 1], $collection->toArray());
    }

    public function testIteratorYieldsAttributes(): void
    {
        $row = new Row(1, ['id' => 1, 'name' => 'Alice']);

        $result = [];
        foreach ($row as $key => $value) {
            $result[$key] = $value;
        }

        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result);
    }

    public function testToArrayReturnsAttributes(): void
    {
        $row = new Row(1, ['id' => 1, 'name' => 'Alice']);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $row->toArray());
    }

    public function testToJsonReturnsJsonString(): void
    {
        $row = new Row(1, ['id' => 1, 'name' => 'Alice']);
        $json = $row->toJson();

        $this->assertJson($json);
        $this->assertSame('{"id":1,"name":"Alice"}', $json);
    }

    public function testCountReturnsNumberOfAttributes(): void
    {
        $row = new Row(1, ['a' => 1, 'b' => 2, 'c' => 3]);
        $this->assertCount(3, $row);
    }

    public function testOffsetExists(): void
    {
        $row = new Row(1, ['id' => 1]);
        $this->assertTrue(isset($row['id']));
        $this->assertFalse(isset($row['name']));
    }

    public function testOffsetGet(): void
    {
        $row = new Row(1, ['id' => 1]);
        $this->assertSame(1, $row['id']);
    }

    public function testOffsetSet(): void
    {
        $row = new Row(1, ['id' => 1]);
        $row['name'] = 'Alice';

        $this->assertSame('Alice', $row['name']);
    }

    public function testOffsetSetThrowsExceptionForNonStringOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $row = new Row(1, ['id' => 1]);
        $row[0] = 'invalid';
    }

    public function testOffsetUnset(): void
    {
        $row = new Row(1, ['id' => 1, 'name' => 'Alice']);
        unset($row['name']);

        $this->assertFalse(isset($row['name']));
        $this->assertSame(['id' => 1], $row->all());
    }
}