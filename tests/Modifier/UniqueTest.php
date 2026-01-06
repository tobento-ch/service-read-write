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
use Tobento\Service\ReadWrite\Modifier\Unique;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class UniqueTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testAllowsFirstOccurrence(): void
    {
        $modifier = new Unique('email');

        $row = new Row(1, ['email' => 'a@example.com']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testThrowsOnDuplicate(): void
    {
        $this->expectException(ModifyException::class);

        $modifier = new Unique('email');

        $row1 = new Row(1, ['email' => 'a@example.com']);
        $row2 = new Row(2, ['email' => 'a@example.com']);

        $modifier->modify($row1, $this->reader(), $this->writer());
        $modifier->modify($row2, $this->reader(), $this->writer()); // duplicate
    }

    public function testSkipRowOnDuplicate(): void
    {
        $modifier = new Unique('email', lookup: null, onFail: 'skip');

        $row1 = new Row(1, ['email' => 'a@example.com']);
        $row2 = new Row(2, ['email' => 'a@example.com']);

        $modifier->modify($row1, $this->reader(), $this->writer());
        $result = $modifier->modify($row2, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $result);
        $this->assertStringContainsString('Duplicate value for unique field "email"', $result->reason());
    }

    public function testNullValuesAreIgnored(): void
    {
        $modifier = new Unique('email');

        $row1 = new Row(1, ['email' => null]);
        $row2 = new Row(2, ['email' => null]);

        $modified1 = $modifier->modify($row1, $this->reader(), $this->writer());
        $modified2 = $modifier->modify($row2, $this->reader(), $this->writer());

        $this->assertSame($row1, $modified1);
        $this->assertSame($row2, $modified2);
    }

    public function testMultipleFields(): void
    {
        $modifier = new Unique(['email', 'username']);

        $row1 = new Row(1, ['email' => 'a@example.com', 'username' => 'john']);
        $row2 = new Row(2, ['email' => 'b@example.com', 'username' => 'john']); // duplicate username

        $modifier->modify($row1, $this->reader(), $this->writer());

        $this->expectException(ModifyException::class);
        $modifier->modify($row2, $this->reader(), $this->writer());
    }

    public function testLookupCallableDetectsDuplicate(): void
    {
        $lookup = function (string $field, mixed $value, Row $row): bool {
            return $value === 'duplicate@example.com';
        };

        $modifier = new Unique('email', lookup: $lookup);

        $row = new Row(1, ['email' => 'duplicate@example.com']);

        $this->expectException(ModifyException::class);
        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testLookupCallableAllowsUnique(): void
    {
        $lookup = fn() => false;

        $modifier = new Unique('email', lookup: $lookup);

        $row = new Row(1, ['email' => 'unique@example.com']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testSkipRowPreservesKeyAndAttributes(): void
    {
        $modifier = new Unique('email', lookup: null, onFail: 'skip');

        $row1 = new Row('abc', ['email' => 'a@example.com']);
        $row2 = new Row('abc', ['email' => 'a@example.com']);

        $modifier->modify($row1, $this->reader(), $this->writer());
        $result = $modifier->modify($row2, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $result);
        $this->assertSame('abc', $result->key());
        $this->assertSame(['email' => 'a@example.com'], $result->all());
    }

    public function testStringifyHandlesArrays(): void
    {
        $modifier = new Unique('tags');

        $row1 = new Row(1, ['tags' => ['a', 'b']]);
        $row2 = new Row(2, ['tags' => ['a', 'b']]);

        $modifier->modify($row1, $this->reader(), $this->writer());

        $this->expectException(ModifyException::class);
        $modifier->modify($row2, $this->reader(), $this->writer());
    }
}