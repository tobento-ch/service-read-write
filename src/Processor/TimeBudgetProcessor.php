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

namespace Tobento\Service\ReadWrite\Processor;

use DateTimeImmutable;
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\Exception\ProcessException;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\ModifiersInterface;
use Tobento\Service\ReadWrite\ProcessorInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Result;
use Tobento\Service\ReadWrite\ResultHandlerInterface;
use Tobento\Service\ReadWrite\ResultInterface;
use Tobento\Service\ReadWrite\Row\SkippableInterface;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\ModeAwareInterface;
use Tobento\Service\ReadWrite\WriterInterface;
use Throwable;

class TimeBudgetProcessor implements ProcessorInterface
{
    protected int $movingAverageWindow = 50; // last N rows to calculate average row time

    /**
     * Create a new instance.
     *
     * @param float $timeBudget in seconds
     * @param ModifiersInterface $modifiers
     * @param null|ResultHandlerInterface $resultHandler
     */
    public function __construct(
        protected float $timeBudget,
        protected ModifiersInterface $modifiers,
        protected null|ResultHandlerInterface $resultHandler = null,
    ) {}

    /**
     * Returns the modifiers.
     *
     * @return ModifiersInterface
     */
    public function modifiers(): ModifiersInterface
    {
        return $this->modifiers;
    }
    
    /**
     * Processes data by reading rows from the given reader
     * and writing them to the specified writer.
     *
     * The operation starts at the given offset and continues
     * for up to the specified limit of rows. If limit is null,
     * all available rows from the offset will be processed.
     *
     * @param ReaderInterface $reader The source of rows to read.
     * @param WriterInterface $writer The target to write rows into.
     * @param int $offset Row index or byte position, depending on reader implementation.
     * @param int|null $limit Maximum number of rows to process, or null for no limit.
     * @return ResultInterface Result object containing details about the processing outcome.
     * @throws ProcessException
     */
    public function process(ReaderInterface $reader, WriterInterface $writer, int $offset = 0, null|int $limit = null): ResultInterface
    {
        try {
            return $this->processing(reader: $reader, writer: $writer, offset: $offset, limit: $limit);
        } catch (Throwable $e) {
            throw new ProcessException($e->getMessage(), 0, $e);
        }
    }
        
    /**
     * Processes data by reading rows from the given reader
     * and writing them to the specified writer.
     */
    protected function processing(ReaderInterface $reader, WriterInterface $writer, int $offset = 0, null|int $limit = null): ResultInterface
    {
        $startedAt = new DateTimeImmutable();
        $startTime = hrtime(true); // nanoseconds
        $timeBudgetNs = $this->timeBudget * (float)1_000_000_000; // convert seconds to ns
        $recentTimes = [];
        $successfulRows = 0;
        $failedRows = 0;
        $skippedRows = 0;
        
        // If writer supports modes, set it based on offset and reader state:
        if ($writer instanceof ModeAwareInterface) {
            if ($offset === 0) {
                $writer->mode(Mode::Overwrite);
            } elseif (! $reader->isFinished()) {
                $writer->mode(Mode::Append);
            } else {
                $writer->mode(Mode::Finalize);
            }
        }
        
        $writer->start();
        
        foreach($reader->read(offset: $offset, limit: $limit) as $row) {
            $rowStart = hrtime(true);

            // Stop if predicted next row would exceed time budget
            if (!empty($recentTimes)) {
                $averageRowTime = array_sum($recentTimes) / count($recentTimes);
                $elapsed = hrtime(true) - $startTime;
                $remaining = $timeBudgetNs - (float)$elapsed;

                if ($averageRowTime > $remaining) {
                    break;
                }
            }

            // Handle row
            if ($row instanceof SkippableInterface) {
                $skippedRows++;
                $this->resultHandler?->handleRowSkip(row: $row);
            } else {
                try {
                    $row = $this->modifiers->modify(row: $row, reader: $reader, writer: $writer);
                    
                    if ($row instanceof SkippableInterface) {
                        $skippedRows++;
                        $this->resultHandler?->handleRowSkip(row: $row);
                        continue;
                    }
                    
                    $writer->write(row: $row);
                    $successfulRows++;
                    $this->resultHandler?->handleRowSuccess(row: $row);
                } catch (ModifyException|WriteException $e) {
                    $failedRows++;
                    $this->resultHandler?->handleRowFailure(row: $row, exception: $e);
                }
            }

            // Update recent row times for moving average
            $rowTime = hrtime(true) - $rowStart;
            $recentTimes[] = $rowTime;
            if (count($recentTimes) > $this->movingAverageWindow) {
                array_shift($recentTimes);
            }
        }
        
        // If writer supports modes, set it based on offset and reader state:
        if ($writer instanceof ModeAwareInterface && $reader->isFinished()) {
            $writer->mode(Mode::Finalize);
        }

        $writer->finish();

        $result = new Result(
            successfulRows: $successfulRows,
            failedRows: $failedRows,
            skippedRows: $skippedRows,
            reader: $reader,
            writer: $writer,
            modifiers: $this->modifiers,
            startedAt: $startedAt,
            finishedAt: new DateTimeImmutable(),
        );
        
        $this->resultHandler?->handleResult(result: $result);
        
        return $result;
    }
}