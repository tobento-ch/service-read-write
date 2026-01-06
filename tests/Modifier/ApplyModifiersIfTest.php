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
use Tobento\Service\ReadWrite\Modifier\ApplyModifiersIf;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\ModifierInterface;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\NullWriter;

class ApplyModifiersIfTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    private function simpleModifier(array $newAttributes): ModifierInterface
    {
        return new class($newAttributes) implements ModifierInterface {
            public function __construct(private array $newAttributes) {}

            public function modify(
                \Tobento\Service\ReadWrite\RowInterface $row,
                \Tobento\Service\ReadWrite\ReaderInterface $reader,
                \Tobento\Service\ReadWrite\WriterInterface $writer
            ): \Tobento\Service\ReadWrite\RowInterface {
                return new Row($row->key(), $this->newAttributes);
            }
        };
    }

    public function testBoolConditionTrueAppliesModifier(): void
    {
        $modifier = $this->simpleModifier(['modified' => true]);

        $apply = new ApplyModifiersIf(true, $modifier);

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['modified' => true], $modified->all());
    }

    public function testBoolConditionFalseSkipsModifier(): void
    {
        $modifier = $this->simpleModifier(['should_not' => 'run']);

        $apply = new ApplyModifiersIf(false, $modifier);

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testStringConditionFieldTruthy(): void
    {
        $modifier = $this->simpleModifier(['ok' => true]);

        $apply = new ApplyModifiersIf('status', $modifier);

        $row = new Row(1, ['status' => 'active']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['ok' => true], $modified->all());
    }

    public function testStringConditionFieldMissingSkips(): void
    {
        $modifier = $this->simpleModifier(['should_not' => 'run']);

        $apply = new ApplyModifiersIf('missing', $modifier);

        $row = new Row(1, ['foo' => 'bar']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testCallableCondition(): void
    {
        $modifier = $this->simpleModifier(['done' => true]);

        $apply = new ApplyModifiersIf(
            fn(Row $row) => $row->get('age') > 18,
            $modifier
        );

        $row = new Row(1, ['age' => 20]);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['done' => true], $modified->all());
    }

    public function testCallableConditionFalseSkips(): void
    {
        $modifier = $this->simpleModifier(['should_not' => 'run']);

        $apply = new ApplyModifiersIf(
            fn(Row $row) => false,
            $modifier
        );

        $row = new Row(1, ['age' => 20]);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testArrayConditionEquals(): void
    {
        $modifier = $this->simpleModifier(['match' => true]);

        $apply = new ApplyModifiersIf(
            ['field' => 'country', 'equals' => 'CH'],
            $modifier
        );

        $row = new Row(1, ['country' => 'CH']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['match' => true], $modified->all());
    }

    public function testArrayConditionMissingTrue(): void
    {
        $modifier = $this->simpleModifier(['missing' => true]);

        $apply = new ApplyModifiersIf(
            ['field' => 'email', 'missing' => true],
            $modifier
        );

        $row = new Row(1, ['name' => 'Alice']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['missing' => true], $modified->all());
    }

    public function testArrayConditionMissingFalseSkips(): void
    {
        $modifier = $this->simpleModifier(['should_not' => 'run']);

        $apply = new ApplyModifiersIf(
            ['field' => 'email', 'missing' => true],
            $modifier
        );

        $row = new Row(1, ['email' => 'test@example.com']);

        $modified = $apply->modify($row, $this->reader(), $this->writer());

        $this->assertSame($row, $modified);
    }

    public function testArrayConditionMissingFieldThrowsException(): void
    {
        $this->expectException(ModifyException::class);

        $apply = new ApplyModifiersIf(
            ['equals' => 'foo'], // missing 'field'
            $this->simpleModifier(['x' => 'y'])
        );

        $row = new Row(1, ['foo' => 'bar']);

        $apply->modify($row, $this->reader(), $this->writer());
    }
}