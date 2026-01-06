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
use Tobento\Service\ReadWrite\Modifier\Format;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class FormatTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testFormatsExistingField(): void
    {
        $modifier = new Format(
            field: 'name',
            formatter: fn($value) => strtoupper($value)
        );

        $row = new Row(1, ['name' => 'john']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['name' => 'JOHN'], $modified->all());
    }

    public function testFormatsMissingField(): void
    {
        $modifier = new Format(
            field: 'missing',
            formatter: fn($value) => $value === null ? 'default' : $value
        );

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['foo' => 'bar', 'missing' => 'default'],
            $modified->all()
        );
    }

    public function testFormatsNestedField(): void
    {
        $modifier = new Format(
            field: 'meta.info.version',
            formatter: fn($value) => 'v' . $value
        );

        $row = new Row(1, [
            'meta' => [
                'info' => [
                    'version' => '1.0',
                ],
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'info' => [
                        'version' => 'v1.0',
                    ],
                ],
            ],
            $modified->all()
        );
    }

    public function testOverwritesExistingValue(): void
    {
        $modifier = new Format(
            field: 'count',
            formatter: fn($value) => $value + 1
        );

        $row = new Row(1, ['count' => 5]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['count' => 6], $modified->all());
    }

    public function testThrowsExceptionForEmptyField(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Format(
            field: '',
            formatter: fn() => 'x'
        );
    }

    public function testThrowsExceptionForNonCallableFormatter(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Format(
            field: 'name',
            formatter: 'not-a-callable'
        );
    }
}