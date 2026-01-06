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
use Tobento\Service\ReadWrite\Modifier\CombineFields;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class CombineFieldsTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testCombinesStringFields(): void
    {
        $modifier = new CombineFields(
            fields: ['first', 'last'],
            into: 'full_name'
        );

        $row = new Row(1, [
            'first' => 'John',
            'last'  => 'Doe',
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['first' => 'John', 'last' => 'Doe', 'full_name' => 'John Doe'],
            $modified->all()
        );
    }

    public function testCombinesNumericFields(): void
    {
        $modifier = new CombineFields(
            fields: ['a', 'b'],
            into: 'combined'
        );

        $row = new Row(1, [
            'a' => 10,
            'b' => 20.5,
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 10, 'b' => 20.5, 'combined' => '10 20.5'],
            $modified->all()
        );
    }

    public function testMissingFieldsAreIgnored(): void
    {
        $modifier = new CombineFields(
            fields: ['x', 'y'],
            into: 'result'
        );

        $row = new Row(1, [
            'x' => 'Hello',
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // Only x exists → result = "Hello"
        $this->assertSame(
            ['x' => 'Hello', 'result' => 'Hello'],
            $modified->all()
        );
    }

    public function testCustomSeparator(): void
    {
        $modifier = new CombineFields(
            fields: ['a', 'b', 'c'],
            into: 'joined',
            separator: ','
        );

        $row = new Row(1, [
            'a' => 'A',
            'b' => 'B',
            'c' => 'C',
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 'A', 'b' => 'B', 'c' => 'C', 'joined' => 'A,B,C'],
            $modified->all()
        );
    }

    public function testRemoveSourceFields(): void
    {
        $modifier = new CombineFields(
            fields: ['first', 'last'],
            into: 'full',
            separator: ' ',
            removeSourceFields: true
        );

        $row = new Row(1, [
            'first' => 'Jane',
            'last'  => 'Doe',
            'age'   => 30,
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['age' => 30, 'full' => 'Jane Doe'],
            $modified->all()
        );
    }

    public function testEmptyFieldsThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CombineFields(
            fields: [],
            into: 'x'
        );
    }

    public function testEmptyIntoThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CombineFields(
            fields: ['a'],
            into: ''
        );
    }
}