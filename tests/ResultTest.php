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

namespace Tobento\Service\ReadWrite\Test;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\Result;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Writer\NullWriter;

class ResultTest extends TestCase
{
    public function testAccessorsReturnConstructorValues(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();
        $modifiers = new Modifiers();

        $result = new Result(
            successfulRows: 5,
            failedRows: 2,
            skippedRows: 3,
            reader: $reader,
            writer: $writer,
            modifiers: $modifiers,
            meta: ['foo' => 'bar']
        );

        $this->assertSame(5, $result->successfulRows());
        $this->assertSame(2, $result->failedRows());
        $this->assertSame(3, $result->skippedRows());
        $this->assertSame($reader, $result->reader());
        $this->assertSame($writer, $result->writer());
        $this->assertSame($modifiers, $result->modifiers());
        $this->assertSame(['foo' => 'bar'], $result->meta());
    }

    public function testStartedAndFinishedAtDefaults(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();
        $modifiers = new Modifiers();

        $result = new Result(1, 0, 0, $reader, $writer, $modifiers);

        $this->assertInstanceOf(DateTimeImmutable::class, $result->startedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $result->finishedAt());
        $this->assertIsFloat($result->runtimeInSeconds());
    }

    public function testStartedAndFinishedAtCustomValues(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();
        $modifiers = new Modifiers();

        $started = new DateTimeImmutable('2025-01-01 00:00:00');
        $finished = new DateTimeImmutable('2025-01-01 00:00:10');

        $result = new Result(1, 1, 1, $reader, $writer, $modifiers, [], $started, $finished);

        $this->assertSame($started, $result->startedAt());
        $this->assertSame($finished, $result->finishedAt());
        $this->assertSame(10.0, $result->runtimeInSeconds());
    }

    public function testTimelineSummary(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();
        $modifiers = new Modifiers();

        $started = new DateTimeImmutable('2025-01-01 00:00:00');
        $finished = new DateTimeImmutable('2025-01-01 00:00:05');

        $result = new Result(2, 1, 1, $reader, $writer, $modifiers, [], $started, $finished);

        $timeline = $result->timeline();

        $this->assertSame($started->format(DATE_ATOM), $timeline['started_at']);
        $this->assertSame($finished->format(DATE_ATOM), $timeline['finished_at']);
        $this->assertSame(5.0, $timeline['runtime_seconds']);
        $this->assertSame([
            'successful' => 2,
            'failed' => 1,
            'skipped' => 1,
            'total' => 4,
        ], $timeline['rows']);
    }
}