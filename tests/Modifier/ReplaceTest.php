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
use Tobento\Service\ReadWrite\Modifier\Replace;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class ReplaceTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testStrictExactMatchReplacement(): void
    {
        $modifier = new Replace(
            fields: 'status',
            replacements: ['A' => 'Active'],
            strict: true
        );

        $row = new Row(1, ['status' => 'A']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['status' => 'Active'], $modified->all());
    }

    public function testStrictNoMatchDoesNothing(): void
    {
        $modifier = new Replace(
            fields: 'status',
            replacements: ['A' => 'Active'],
            strict: true
        );

        $row = new Row(1, ['status' => 'X']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['status' => 'X'], $modified->all());
    }

    public function testNonStrictSubstringReplacement(): void
    {
        $modifier = new Replace(
            fields: 'text',
            replacements: ['foo' => 'bar'],
            strict: false
        );

        $row = new Row(1, ['text' => 'foo123foo']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['text' => 'bar123bar'], $modified->all());
    }

    public function testMultipleFieldsReplacement(): void
    {
        $modifier = new Replace(
            fields: ['a', 'b'],
            replacements: ['x' => 'y'],
            strict: false
        );

        $row = new Row(1, ['a' => 'x1', 'b' => '2x', 'c' => 'keep']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 'y1', 'b' => '2y', 'c' => 'keep'],
            $modified->all()
        );
    }

    public function testNestedFieldReplacement(): void
    {
        $modifier = new Replace(
            fields: 'meta.code',
            replacements: ['123' => 'XYZ'],
            strict: true
        );

        $row = new Row(1, [
            'meta' => [
                'code' => '123',
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'code' => 'XYZ',
                ],
            ],
            $modified->all()
        );
    }

    public function testNullValuesAreSkippedWhenNotForced(): void
    {
        $modifier = new Replace(
            fields: 'field',
            replacements: ['x' => 'y'],
            strict: true,
            forceNullReplacement: false
        );

        $row = new Row(1, ['field' => null]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['field' => null], $modified->all());
    }

    public function testNullValuesAreReplacedWhenForced(): void
    {
        $modifier = new Replace(
            fields: 'field',
            replacements: [null => 'NULL_REPLACED'],
            strict: true,
            forceNullReplacement: true
        );

        $row = new Row(1, ['field' => null]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['field' => 'NULL_REPLACED'], $modified->all());
    }

    public function testThrowsModifyExceptionOnReplacementFailure(): void
    {
        $this->expectException(ModifyException::class);

        // Non-strict mode so str_replace is used
        $modifier = new Replace(
            fields: 'field',
            replacements: ['x' => fn() => 'never called'],
            strict: false
        );

        $row = new Row(1, ['field' => 'x']);

        // This will cause str_replace to attempt (string)$replace,
        // which throws a TypeError, which is wrapped in ModifyException.
        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testThrowsExceptionWhenNoFieldsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Replace([], ['x' => 'y']);
    }

    public function testThrowsExceptionWhenReplacementsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Replace('field', []);
    }
}