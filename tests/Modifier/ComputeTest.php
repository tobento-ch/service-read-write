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
use Tobento\Service\ReadWrite\Modifier\Compute;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class ComputeTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testComputesNewField(): void
    {
        $modifier = new Compute(
            field: 'computed',
            computeFn: fn(Row $row) => 'value'
        );

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['foo' => 'bar', 'computed' => 'value'],
            $modified->all()
        );
    }

    public function testComputesBasedOnExistingFields(): void
    {
        $modifier = new Compute(
            field: 'sum',
            computeFn: fn(Row $row) => $row->get('a') + $row->get('b')
        );

        $row = new Row(1, ['a' => 10, 'b' => 5]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 10, 'b' => 5, 'sum' => 15],
            $modified->all()
        );
    }

    public function testOverwritesExistingField(): void
    {
        $modifier = new Compute(
            field: 'name',
            computeFn: fn(Row $row) => strtoupper($row->get('name'))
        );

        $row = new Row(1, ['name' => 'john']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['name' => 'JOHN'],
            $modified->all()
        );
    }

    public function testComputesNestedField(): void
    {
        $modifier = new Compute(
            field: 'meta.score.total',
            computeFn: fn(Row $row) => 42
        );

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'foo' => 'bar',
                'meta' => [
                    'score' => [
                        'total' => 42,
                    ],
                ],
            ],
            $modified->all()
        );
    }

    public function testEmptyFieldThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Compute(
            field: '',
            computeFn: fn() => 'x'
        );
    }

    public function testNonCallableThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Compute(
            field: 'x',
            computeFn: 'not-a-callable'
        );
    }
}