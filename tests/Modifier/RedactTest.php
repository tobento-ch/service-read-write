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
use Tobento\Service\ReadWrite\Modifier\Redact;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class RedactTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testRedactsSingleFieldToNull(): void
    {
        $modifier = new Redact('secret');

        $row = new Row(1, ['secret' => 'abc']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['secret' => null],
            $modified->all()
        );
    }

    public function testRedactsMultipleFields(): void
    {
        $modifier = new Redact(['a', 'b']);

        $row = new Row(1, ['a' => 'x', 'b' => 'y', 'c' => 'z']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => null, 'b' => null, 'c' => 'z'],
            $modified->all()
        );
    }

    public function testRedactsNestedField(): void
    {
        $modifier = new Redact('meta.token');

        $row = new Row(1, [
            'meta' => [
                'token' => 'abc123',
                'other' => 'keep',
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'token' => null,
                    'other' => 'keep',
                ],
            ],
            $modified->all()
        );
    }

    public function testUsesCustomReplacement(): void
    {
        $modifier = new Redact('field', 'REDACTED');

        $row = new Row(1, ['field' => 'value']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['field' => 'REDACTED'],
            $modified->all()
        );
    }

    public function testMissingFieldsAreIgnored(): void
    {
        $modifier = new Redact(['x', 'y']);

        $row = new Row(1, ['x' => 'value']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // x exists → redacted
        // y missing → ignored
        $this->assertSame(
            ['x' => null],
            $modified->all()
        );
    }

    public function testRowKeyIsPreserved(): void
    {
        $modifier = new Redact('foo');

        $row = new Row('abc', ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame('abc', $modified->key());
    }

    public function testThrowsExceptionWhenNoFieldsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Redact([]);
    }
}