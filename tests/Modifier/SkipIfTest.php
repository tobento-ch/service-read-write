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
use Tobento\Service\ReadWrite\Modifier\SkipIf;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class SkipIfTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testSkipAlways(): void
    {
        $modifier = new SkipIf(true, 'always skip');

        $row = new Row(1, ['a' => 'b']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('always skip', $modified->reason());
    }

    public function testSkipNever(): void
    {
        $modifier = new SkipIf(false);

        $row = new Row(1, ['a' => 'b']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testSkipIfFieldMissingOrEmpty(): void
    {
        $modifier = new SkipIf('email', 'email missing');

        $row = new Row(1, ['name' => 'John']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('email missing', $modified->reason());
    }

    public function testSkipIfFieldEmpty(): void
    {
        $modifier = new SkipIf('email');

        $row = new Row(1, ['email' => '']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
    }

    public function testCallableCondition(): void
    {
        $modifier = new SkipIf(
            condition: fn($row) => $row->get('age') < 18,
            reason: 'underage'
        );

        $row = new Row(1, ['age' => 15]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('underage', $modified->reason());
    }

    public function testArrayConditionMissing(): void
    {
        $modifier = new SkipIf(
            condition: ['field' => 'email', 'missing' => true],
            reason: 'email required'
        );

        $row = new Row(1, ['name' => 'John']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('email required', $modified->reason());
    }

    public function testArrayConditionEquals(): void
    {
        $modifier = new SkipIf(
            condition: ['field' => 'status', 'equals' => 'inactive'],
            reason: 'inactive user'
        );

        $row = new Row(1, ['status' => 'inactive']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('inactive user', $modified->reason());
    }

    public function testArrayConditionEqualsDoesNotSkip(): void
    {
        $modifier = new SkipIf(
            condition: ['field' => 'status', 'equals' => 'inactive']
        );

        $row = new Row(1, ['status' => 'active']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testInvalidArrayConditionThrowsException(): void
    {
        $this->expectException(ModifyException::class);

        $modifier = new SkipIf(
            condition: ['missing' => true] // no field key
        );

        $row = new Row(1, ['a' => 'b']);

        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testSkipRowPreservesKeyAndAttributes(): void
    {
        $modifier = new SkipIf(true, 'test');

        $row = new Row('abc', ['x' => 'y']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('abc', $modified->key());
        $this->assertSame(['x' => 'y'], $modified->all());
    }
}