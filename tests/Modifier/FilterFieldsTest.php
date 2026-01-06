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
use Tobento\Service\ReadWrite\Modifier\FilterFields;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class FilterFieldsTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testKeepsOnlySpecifiedFields(): void
    {
        $modifier = new FilterFields(['a', 'c']);

        $row = new Row(1, [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 1, 'c' => 3],
            $modified->all()
        );
    }

    public function testKeepsNestedFields(): void
    {
        $modifier = new FilterFields(['meta.info.version']);

        $row = new Row(1, [
            'name' => 'Test',
            'meta' => [
                'info' => [
                    'version' => '1.0',
                    'other'   => 'x',
                ],
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'info' => [
                        'version' => '1.0',
                    ],
                ],
            ],
            $modified->all()
        );
    }

    public function testMissingFieldsAreIgnored(): void
    {
        $modifier = new FilterFields(['x', 'y']);

        $row = new Row(1, [
            'x' => 'value',
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // Only x exists
        $this->assertSame(
            ['x' => 'value'],
            $modified->all()
        );
    }

    public function testRowKeyIsPreserved(): void
    {
        $modifier = new FilterFields(['foo']);

        $row = new Row('abc', ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame('abc', $modified->key());
    }

    public function testThrowsExceptionWhenFieldsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new FilterFields([]);
    }
}