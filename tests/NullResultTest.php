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

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\NullResult;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use DateTimeImmutable;

class NullResultTest extends TestCase
{
    public function testSuccessfulFailedSkippedRowsAreZero(): void
    {
        $result = new NullResult();

        $this->assertSame(0, $result->successfulRows());
        $this->assertSame(0, $result->failedRows());
        $this->assertSame(0, $result->skippedRows());
    }

    public function testReaderIsIterableReader(): void
    {
        $result = new NullResult();
        $this->assertInstanceOf(IterableReader::class, $result->reader());
    }

    public function testWriterIsNullWriter(): void
    {
        $result = new NullResult();
        $this->assertInstanceOf(NullWriter::class, $result->writer());
    }

    public function testModifiersIsModifiers(): void
    {
        $result = new NullResult();
        $this->assertInstanceOf(Modifiers::class, $result->modifiers());
    }

    public function testMetaIsEmptyArray(): void
    {
        $result = new NullResult();
        $this->assertSame([], $result->meta());
    }

    public function testStartedAndFinishedAtAreDateTimeImmutable(): void
    {
        $result = new NullResult();

        $this->assertInstanceOf(DateTimeImmutable::class, $result->startedAt());
        $this->assertInstanceOf(DateTimeImmutable::class, $result->finishedAt());
    }

    public function testRuntimeIsZero(): void
    {
        $result = new NullResult();
        $this->assertSame(0.0, $result->runtimeInSeconds());
    }

    public function testTimelineIsEmptyArray(): void
    {
        $result = new NullResult();
        $this->assertSame([], $result->timeline());
    }
}