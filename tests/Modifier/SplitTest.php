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
use Tobento\Service\ReadWrite\Modifier\Split;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class SplitTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testSplitsIntoMultipleFields(): void
    {
        $modifier = new Split(
            field: 'full',
            into: ['first', 'last']
        );

        $row = new Row(1, ['full' => 'John Doe']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['full' => 'John Doe', 'first' => 'John', 'last' => 'Doe'],
            $modified->all()
        );
    }

    public function testCustomSeparator(): void
    {
        $modifier = new Split(
            field: 'coords',
            into: ['lat', 'lng'],
            separator: ','
        );

        $row = new Row(1, ['coords' => '47.2, 8.5']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['coords' => '47.2, 8.5', 'lat' => '47.2', 'lng' => '8.5'],
            $modified->all()
        );
    }

    public function testRemovesSourceField(): void
    {
        $modifier = new Split(
            field: 'full',
            into: ['first', 'last'],
            separator: ' ',
            removeSourceField: true
        );

        $row = new Row(1, ['full' => 'John Doe']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['first' => 'John', 'last' => 'Doe'],
            $modified->all()
        );
    }

    public function testSkipsEmptyParts(): void
    {
        $modifier = new Split(
            field: 'full',
            into: ['first', 'middle', 'last']
        );

        $row = new Row(1, ['full' => 'John  Doe']); // double space → empty middle part

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // middle should NOT be set
        $this->assertSame(
            ['full' => 'John  Doe', 'first' => 'John', 'last' => 'Doe'],
            $modified->all()
        );
    }

    public function testNestedTargetFields(): void
    {
        $modifier = new Split(
            field: 'name',
            into: ['person.first', 'person.last']
        );

        $row = new Row(1, ['name' => 'John Doe']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'name' => 'John Doe',
                'person' => [
                    'first' => 'John',
                    'last' => 'Doe',
                ],
            ],
            $modified->all()
        );
    }

    public function testSourceFieldMissingDoesNothing(): void
    {
        $modifier = new Split(
            field: 'missing',
            into: ['a', 'b']
        );

        $row = new Row(1, ['x' => 'y']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['x' => 'y'], $modified->all());
    }

    public function testNonStringSourceValueDoesNothing(): void
    {
        $modifier = new Split(
            field: 'numbers',
            into: ['a', 'b']
        );

        $row = new Row(1, ['numbers' => 12345]); // not a string

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['numbers' => 12345], $modified->all());
    }

    public function testThrowsExceptionWhenInvalidConstructorArguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Split(
            field: '',
            into: ['a']
        );
    }
}