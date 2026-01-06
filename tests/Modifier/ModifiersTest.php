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
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\Modifier\CallableModifier;
use Tobento\Service\ReadWrite\Modifier\ColumnMap;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\NullWriter;

class ModifiersTest extends TestCase
{
    public function testExecutesModifiersInOrder(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(key: 1, attributes: ['value' => 1]);

        $modifier1 = new CallableModifier(function ($row) {
            return new Row(
                key: $row->key(),
                attributes: ['value' => $row->get('value') + 1]
            );
        });

        $modifier2 = new CallableModifier(function ($row) {
            return new Row(
                key: $row->key(),
                attributes: ['value' => $row->get('value') * 10]
            );
        });

        $modifiers = new Modifiers($modifier1, $modifier2);

        $result = $modifiers->modify($row, $reader, $writer);

        // (1 + 1) * 10 = 20
        $this->assertSame(20, $result->get('value'));
    }

    public function testAddModifierAppendsToEnd(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(key: 1, attributes: ['value' => 5]);

        $modifiers = new Modifiers(
            new CallableModifier(fn($row) => new Row($row->key(), ['value' => $row->get('value') + 1]))
        );

        // Append a second modifier
        $modifiers->add(
            new CallableModifier(fn($row) => new Row($row->key(), ['value' => $row->get('value') * 2]))
        );

        $result = $modifiers->modify($row, $reader, $writer);

        // (5 + 1) * 2 = 12
        $this->assertSame(12, $result->get('value'));
    }

    public function testPrependModifierAddsToBeginning(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(key: 1, attributes: ['value' => 3]);

        $modifiers = new Modifiers(
            new CallableModifier(fn($row) => new Row($row->key(), ['value' => $row->get('value') * 3]))
        );

        // Prepend a modifier that runs first
        $modifiers->prepend(
            new CallableModifier(fn($row) => new Row($row->key(), ['value' => $row->get('value') + 1]))
        );

        $result = $modifiers->modify($row, $reader, $writer);

        // (3 + 1) * 3 = 12
        $this->assertSame(12, $result->get('value'));
    }

    public function testThrowsModifyExceptionFromInnerModifier(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();
        $row = new Row(key: 1, attributes: []);

        $failingModifier = new CallableModifier(function () use ($row) {
            throw new ModifyException(row: $row, message: 'Fail');
        });

        $modifiers = new Modifiers($failingModifier);

        $this->expectException(ModifyException::class);
        $this->expectExceptionMessage('Fail');

        $modifiers->modify($row, $reader, $writer);
    }

    public function testIteratorReturnsModifiers(): void
    {
        $m1 = new CallableModifier(fn($row) => $row);
        $m2 = new CallableModifier(fn($row) => $row);

        $modifiers = new Modifiers($m1, $m2);

        $collected = [];
        foreach ($modifiers as $modifier) {
            $collected[] = $modifier;
        }

        $this->assertSame([$m1, $m2], $collected);
    }
    
    public function testStopsProcessingWhenModifierReturnsSkippableRow(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(key: 1, attributes: ['value' => 10]);

        // First modifier returns a SkipRow
        $skipModifier = new CallableModifier(function ($row) {
            return new \Tobento\Service\ReadWrite\Row\SkipRow(
                key: $row->key(),
                attributes: $row->all(),
                reason: '',
            );
        });

        // This modifier MUST NOT run
        $failingModifier = new CallableModifier(function () {
            throw new \Exception('This modifier should not be executed');
        });

        $modifiers = new Modifiers($skipModifier, $failingModifier);

        $result = $modifiers->modify($row, $reader, $writer);

        $this->assertInstanceOf(
            \Tobento\Service\ReadWrite\Row\SkippableInterface::class,
            $result,
            'Expected a SkippableInterface row to be returned'
        );

        // Ensure the second modifier was NOT executed
        $this->assertSame(10, $result->get('value'));
    }
}