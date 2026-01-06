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
use Tobento\Service\ReadWrite\Modifier\Lookup;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class LookupTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testArrayLookupMapsValue(): void
    {
        $modifier = new Lookup(
            field: 'status',
            into: 'label',
            lookup: [
                'A' => 'Active',
                'I' => 'Inactive',
            ]
        );

        $row = new Row(1, ['status' => 'A']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['status' => 'A', 'label' => 'Active'],
            $modified->all()
        );
    }

    public function testArrayLookupNoMatchDoesNothing(): void
    {
        $modifier = new Lookup(
            field: 'status',
            into: 'label',
            lookup: [
                'A' => 'Active',
            ]
        );

        $row = new Row(1, ['status' => 'X']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // No mapping → no "label" field
        $this->assertSame(
            ['status' => 'X'],
            $modified->all()
        );
    }

    public function testCallableLookup(): void
    {
        $modifier = new Lookup(
            field: 'value',
            into: 'mapped',
            lookup: fn($value, $field) => $value * 2
        );

        $row = new Row(1, ['value' => 10]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['value' => 10, 'mapped' => 20],
            $modified->all()
        );
    }

    public function testNestedFieldLookup(): void
    {
        $modifier = new Lookup(
            field: 'meta.code',
            into: 'meta.label',
            lookup: [
                1 => 'One',
                2 => 'Two',
            ]
        );

        $row = new Row(1, [
            'meta' => [
                'code' => 2,
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'code' => 2,
                    'label' => 'Two',
                ],
            ],
            $modified->all()
        );
    }

    public function testRemoveSourceField(): void
    {
        $modifier = new Lookup(
            field: 'type',
            into: 'type_label',
            lookup: ['A' => 'Alpha'],
            removeSourceField: true
        );

        $row = new Row(1, ['type' => 'A']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['type_label' => 'Alpha'],
            $modified->all()
        );
    }

    public function testMissingSourceFieldDoesNothing(): void
    {
        $modifier = new Lookup(
            field: 'missing',
            into: 'mapped',
            lookup: ['x' => 'y']
        );

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // No "missing" field → no mapping
        $this->assertSame(
            ['foo' => 'bar'],
            $modified->all()
        );
    }

    public function testThrowsExceptionWhenFieldOrIntoEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Lookup(
            field: '',
            into: 'x',
            lookup: []
        );
    }
}