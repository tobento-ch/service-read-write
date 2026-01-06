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
 
namespace Tobento\Service\ReadWrite;

use DateTimeImmutable;

final class Result implements ResultInterface
{
    private readonly DateTimeImmutable $startedAt;
    private readonly DateTimeImmutable $finishedAt;
    private readonly float $runtimeInSeconds;
    
    /**
     * Create a new instance.
     *
     * @param int $successfulRows,
     * @param int $failedRows
     * @param int $skippedRows
     * @param ReaderInterface $reader
     * @param WriterInterface $writer
     * @param ModifiersInterface $modifiers
     * @param array $meta
     * @param null|DateTimeImmutable $startedAt
     * @param null|DateTimeImmutable $finishedAt
     */
    public function __construct(
        private int $successfulRows,
        private int $failedRows,
        private int $skippedRows,
        private ReaderInterface $reader,
        private WriterInterface $writer,
        private ModifiersInterface $modifiers,        
        private array $meta = [],
        null|DateTimeImmutable $startedAt = null,
        null|DateTimeImmutable $finishedAt = null,
    ) {
        $this->startedAt = $startedAt ?? new DateTimeImmutable();
        $this->finishedAt = $finishedAt ?? new DateTimeImmutable();
        $this->runtimeInSeconds = $this->finishedAt->getTimestamp() - $this->startedAt->getTimestamp();
    }
    
    /**
     * Returns the number of successful rows.
     *
     * @return int
     */
    public function successfulRows(): int
    {
        return $this->successfulRows;
    }
    
    /**
     * Returns the number of failed rows.
     *
     * @return int
     */
    public function failedRows(): int
    {
        return $this->failedRows;
    }
    
    /**
     * Returns the number of skipped rows.
     *
     * @return int
     */
    public function skippedRows(): int
    {
        return $this->skippedRows;
    }
    
    /**
     * Returns the reader.
     *
     * @return ReaderInterface
     */
    public function reader(): ReaderInterface
    {
        return $this->reader;
    }
    
    /**
     * Returns the writer.
     *
     * @return WriterInterface
     */
    public function writer(): WriterInterface
    {
        return $this->writer;
    }
    
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
     * Returns the meta data.
     *
     * @return array
     */
    public function meta(): array
    {
        return $this->meta;
    }
    
    /**
     * Returns the timestamp when processing started.
     *
     * @return DateTimeImmutable
     */
    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
    
    /**
     * Returns the timestamp when processing finished.
     *
     * @return DateTimeImmutable
     */
    public function finishedAt(): DateTimeImmutable
    {
        return $this->finishedAt;
    }
    
    /**
     * Returns the runtime in seconds.
     *
     * @return float
     */
    public function runtimeInSeconds(): float
    {
        return $this->runtimeInSeconds;
    }
    
    /**
     * Returns a timeline summary for monitoring/logging.
     *
     * @return array
     */
    public function timeline(): array
    {
        return [
            'started_at' => $this->startedAt->format(DATE_ATOM),
            'finished_at' => $this->finishedAt->format(DATE_ATOM),
            'runtime_seconds' => $this->runtimeInSeconds(),
            'rows' => [
                'successful' => $this->successfulRows,
                'failed' => $this->failedRows,
                'skipped' => $this->skippedRows,
                'total' => $this->successfulRows + $this->failedRows + $this->skippedRows,
            ],
        ];
    }
}