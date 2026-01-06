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

namespace Tobento\Service\ReadWrite\Test\Processor;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Processor\TimeBudgetProcessor;
use Tobento\Service\ReadWrite\Modifier\CallableModifier;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Test\Helper\FakeWriter;
use Tobento\Service\ReadWrite\Test\Helper\FakeResultHandler;
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Writer\Mode;

class TimeBudgetProcessorTest extends TestCase
{
    public function testStopsEarlyWhenTimeBudgetExceeded(): void
    {
        $reader = new IterableReader([
            new Row(1, ['v' => 1]),
            new Row(2, ['v' => 2]),
            new Row(3, ['v' => 3]),
        ]);

        // Modifier that sleeps to simulate slow processing
        $modifiers = new Modifiers(
            new CallableModifier(function (RowInterface $row) {
                usleep(50_000); // 50ms
                return $row;
            })
        );

        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        // Time budget = 0.05 seconds → only 1 row should fit
        $processor = new TimeBudgetProcessor(
            timeBudget: 0.05,
            modifiers: $modifiers,
            resultHandler: $handler
        );

        $result = $processor->process($reader, $writer);

        $this->assertSame(1, $result->successfulRows());
        $this->assertCount(1, $writer->written);
    }

    public function testProcessesAllRowsWhenBudgetIsLarge(): void
    {
        $reader = new IterableReader([
            new Row(1, ['v' => 1]),
            new Row(2, ['v' => 2]),
            new Row(3, ['v' => 3]),
        ]);

        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        $processor = new TimeBudgetProcessor(
            timeBudget: 5, // 5 seconds → plenty of time
            modifiers: new Modifiers(),
            resultHandler: $handler
        );

        $result = $processor->process($reader, $writer);

        $this->assertSame(3, $result->successfulRows());
        $this->assertCount(3, $writer->written);
    }

    public function testSkippedRowsAreCounted(): void
    {
        $skippable = new SkipRow(key: 1, attributes: [], reason: 'skip');

        $reader = new IterableReader([
            $skippable,
            new Row(2, ['v' => 10]),
        ]);

        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        $processor = new TimeBudgetProcessor(
            timeBudget: 5,
            modifiers: new Modifiers(),
            resultHandler: $handler
        );

        $result = $processor->process($reader, $writer);

        $this->assertSame(1, $result->skippedRows());
        $this->assertSame(1, $result->successfulRows());
        $this->assertCount(1, $writer->written);
        $this->assertCount(1, $handler->skipped);
    }

    public function testModifierFailureIsCounted(): void
    {
        $reader = new IterableReader([
            new Row(1, ['v' => 1]),
            new Row(2, ['v' => 2]),
        ]);

        $modifiers = new Modifiers(
            new CallableModifier(function (RowInterface $row) {
                if ($row->key() === 2) {
                    throw new ModifyException(row: $row, message: 'fail');
                }
                return $row;
            })
        );

        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        $processor = new TimeBudgetProcessor(
            timeBudget: 5,
            modifiers: $modifiers,
            resultHandler: $handler
        );

        $result = $processor->process($reader, $writer);

        $this->assertSame(1, $result->successfulRows());
        $this->assertSame(1, $result->failedRows());
        $this->assertCount(1, $handler->failure);
    }

    public function testWriterFailureIsCounted(): void
    {
        $reader = new IterableReader([
            new Row(1, ['v' => 1]),
        ]);

        $writer = new class extends FakeWriter {
            public function write(RowInterface $row): void
            {
                throw new WriteException(row: $row, message: 'write failed');
            }
        };

        $handler = new FakeResultHandler();

        $processor = new TimeBudgetProcessor(
            timeBudget: 5,
            modifiers: new Modifiers(),
            resultHandler: $handler
        );

        $result = $processor->process($reader, $writer);

        $this->assertSame(0, $result->successfulRows());
        $this->assertSame(1, $result->failedRows());
        $this->assertCount(1, $handler->failure);
    }

    public function testWriterModesAreSetCorrectly(): void
    {
        $processor = new TimeBudgetProcessor(5, new Modifiers());

        // Overwrite → reader finished → Finalize
        $reader = new IterableReader([new Row(1, [])]);
        $writer = new FakeWriter();
        $processor->process($reader, $writer, offset: 0);
        $this->assertSame(Mode::Finalize, $writer->mode);

        // Append → reader not finished
        $reader2 = new IterableReader([1 => new Row(1, []), 2 => new Row(2, [])]);
        $writer2 = new FakeWriter();
        // process only one row so reader not finished
        $processor->process($reader2, $writer2, offset: 1, limit: 1);
        $this->assertSame(Mode::Append, $writer2->mode);

        // Finalize → offset > 0 and reader finished
        $reader3 = new IterableReader([]);
        iterator_to_array($reader3->read()); // exhaust reader
        $writer3 = new FakeWriter();
        $processor->process($reader3, $writer3, offset: 5);
        $this->assertSame(Mode::Finalize, $writer3->mode);
    }

    public function testResultHandlerReceivesFinalResult(): void
    {
        $reader = new IterableReader([new Row(1, ['v' => 1])]);
        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        $processor = new TimeBudgetProcessor(
            timeBudget: 5,
            modifiers: new Modifiers(),
            resultHandler: $handler
        );

        $result = $processor->process($reader, $writer);

        $this->assertSame($result, $handler->result);
    }
}