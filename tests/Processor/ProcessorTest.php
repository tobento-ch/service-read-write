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
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Modifier\CallableModifier;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\Processor\Processor;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\ResultInterface;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\Test\Helper\FakeWriter;
use Tobento\Service\ReadWrite\Test\Helper\FakeResultHandler;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\ModeAwareInterface;
use Tobento\Service\ReadWrite\WriterInterface;

class ProcessorTest extends TestCase
{
    public function testSuccessfulProcessing(): void
    {
        $reader = new IterableReader([
            new Row(1, ['value' => 10]),
            new Row(2, ['value' => 20]),
        ]);

        $writer = new FakeWriter();

        $modifiers = new Modifiers(
            new CallableModifier(fn(Row $row) =>
                new Row($row->key(), ['value' => $row->get('value') * 2])
            )
        );

        $processor = new Processor($modifiers);

        $result = $processor->process($reader, $writer);

        $this->assertSame(2, $result->successfulRows());
        $this->assertSame(0, $result->failedRows());
        $this->assertSame(0, $result->skippedRows());

        $this->assertCount(2, $writer->written);
        $this->assertSame(20, $writer->written[0]->get('value'));
        $this->assertSame(40, $writer->written[1]->get('value'));

        $this->assertTrue($writer->started);
        $this->assertTrue($writer->finished);
    }

    public function testSkippedRowsAreCounted(): void
    {
        $skippable = new SkipRow(key: 1, attributes: [], reason: 'skip');

        $reader = new IterableReader([
            $skippable,
            new Row(2, ['value' => 10]),
        ]);

        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        $processor = new Processor(new Modifiers(), $handler);

        $result = $processor->process($reader, $writer);

        $this->assertSame(1, $result->skippedRows());
        $this->assertSame(1, $result->successfulRows());
        $this->assertCount(1, $writer->written);
        $this->assertCount(1, $handler->skipped);
    }

    public function testFailedRowsAreCounted(): void
    {
        $reader = new IterableReader([
            new Row(1, ['value' => 10]),
            new Row(2, ['value' => 20]),
        ]);

        $writer = new FakeWriter();

        $modifiers = new Modifiers(
            new CallableModifier(function (RowInterface $row) {
                if ($row->key() === 2) {
                    throw new ModifyException(row: $row, message: 'fail');
                }
                return $row;
            })
        );

        $handler = new FakeResultHandler();

        $processor = new Processor($modifiers, $handler);

        $result = $processor->process($reader, $writer);

        $this->assertSame(1, $result->successfulRows());
        $this->assertSame(1, $result->failedRows());
        $this->assertCount(1, $handler->failure);
    }

    public function testWriterModesAreSetCorrectly(): void
    {
        $processor = new Processor(new Modifiers());

        // offset = 0 and reader finished → Finalize
        $reader = new IterableReader([
            new Row(1, ['value' => 10]),
        ]);
        $writer = new FakeWriter();
        $processor->process($reader, $writer, offset: 0);
        $this->assertSame(Mode::Finalize, $writer->mode);

        // offset > 0 and reader not finished → Append
        $reader2 = new IterableReader([
            new Row(1, []),
            new Row(2, []),
        ]);
        $writer2 = new FakeWriter();
        // process only one row so reader not finished
        $processor->process($reader2, $writer2, offset: 1, limit: 1);
        $this->assertSame(Mode::Append, $writer2->mode);

        // offset > 0 and reader finished → Finalize
        $reader3 = new IterableReader([]);
        iterator_to_array($reader3->read()); // exhaust reader
        $writer3 = new FakeWriter();
        $processor->process($reader3, $writer3, offset: 5);
        $this->assertSame(Mode::Finalize, $writer3->mode);
    }

    public function testResultHandlerReceivesFinalResult(): void
    {
        $reader = new IterableReader([
            new Row(1, ['value' => 10]),
        ]);

        $writer = new FakeWriter();
        $handler = new FakeResultHandler();

        $processor = new Processor(new Modifiers(), $handler);

        $result = $processor->process($reader, $writer);

        $this->assertSame($result, $handler->result);
    }
    
    public function testJsonWriterClosesArrayOnFinalizeMode(): void
    {
        // InMemory resource to capture JSON output
        $resource = new \Tobento\Service\ReadWrite\Writer\Resource\InMemory();
        $writer = new \Tobento\Service\ReadWrite\Writer\JsonResource($resource);

        // Reader with one row, but offset > 0 forces Processor to choose Append
        $reader = new \Tobento\Service\ReadWrite\Reader\IterableReader([
            new \Tobento\Service\ReadWrite\Row\Row(1, ['name' => 'Alice']),
        ]);

        $processor = new \Tobento\Service\ReadWrite\Processor\Processor(
            modifiers: new \Tobento\Service\ReadWrite\Modifier\Modifiers()
        );

        // offset > 0 → Processor will choose Append mode
        $processor->process($reader, $writer, offset: 0);

        // Get the JSON output
        $json = $resource->getContent();

        // Ensure JSON is valid
        $this->assertJson($json);

        // Ensure the decoded JSON matches expected structure
        $decoded = json_decode($json, true);

        $this->assertSame([['name' => 'Alice']], $decoded);
    }
}