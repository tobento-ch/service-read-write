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
use Tobento\Service\ReadWrite\Modifier\RemoveFields;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class RemoveFieldsTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testRemovesSingleField(): void
    {
        $modifier = new RemoveFields('password');

        $row = new Row(1, [
            'username' => 'john',
            'password' => 'secret',
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['username' => 'john'],
            $modified->all()
        );
    }

    public function testRemovesMultipleFields(): void
    {
        $modifier = new RemoveFields(['a', 'c']);

        $row = new Row(1, [
            'a' => 1,
            'b' => 2,
            'c' => 3,
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['b' => 2],
            $modified->all()
        );
    }

    public function testRemovesNestedField(): void
    {
        $modifier = new RemoveFields('meta.secret.token');

        $row = new Row(1, [
            'meta' => [
                'secret' => [
                    'token' => 'abc123',
                    'other' => 'keep',
                ],
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'secret' => [
                        'other' => 'keep',
                    ],
                ],
            ],
            $modified->all()
        );
    }

    public function testMissingFieldsAreIgnored(): void
    {
        $modifier = new RemoveFields(['x', 'y']);

        $row = new Row(1, [
            'x' => 'value',
            'z' => 'keep',
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // x exists → removed
        // y missing → ignored
        $this->assertSame(
            ['z' => 'keep'],
            $modified->all()
        );
    }

    public function testRowKeyIsPreserved(): void
    {
        $modifier = new RemoveFields('foo');

        $row = new Row('abc', ['foo' => 'bar']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame('abc', $modified->key());
    }

    public function testThrowsExceptionWhenNoFieldsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RemoveFields([]);
    }
}