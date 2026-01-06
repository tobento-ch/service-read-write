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
use Tobento\Service\ReadWrite\Modifier\Trim;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class TrimTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testTrimsSimpleField(): void
    {
        $modifier = new Trim(['name']);

        $row = new Row(1, ['name' => '  John  ']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['name' => 'John'], $modified->all());
    }

    public function testTrimsNestedField(): void
    {
        $modifier = new Trim(['user.name']);

        $row = new Row(1, [
            'user' => [
                'name' => '  Alice  ',
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'user' => [
                    'name' => 'Alice',
                ],
            ],
            $modified->all()
        );
    }

    public function testTrimsWithCustomCharacters(): void
    {
        $modifier = new Trim(['code'], '-');

        $row = new Row(1, ['code' => '--ABC--']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['code' => 'ABC'], $modified->all());
    }

    public function testIgnoresNonStringValues(): void
    {
        $modifier = new Trim(['value']);

        $row = new Row(1, ['value' => 123]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // unchanged
        $this->assertSame(['value' => 123], $modified->all());
    }

    public function testIgnoresMissingFields(): void
    {
        $modifier = new Trim(['missing']);

        $row = new Row(1, ['name' => 'John']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // unchanged
        $this->assertSame(['name' => 'John'], $modified->all());
    }

    public function testRowKeyIsPreserved(): void
    {
        $modifier = new Trim(['x']);

        $row = new Row('abc', ['x' => ' test ']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame('abc', $modified->key());
    }

    public function testThrowsExceptionWhenNoAttributesProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Trim([]);
    }
}