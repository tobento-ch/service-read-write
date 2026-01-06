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

interface ResultInterface
{
    /**
     * Returns the number of successful rows.
     *
     * @return int
     */
    public function successfulRows(): int;
    
    /**
     * Returns the number of failed rows.
     *
     * @return int
     */
    public function failedRows(): int;
    
    /**
     * Returns the number of skipped rows.
     *
     * @return int
     */
    public function skippedRows(): int;
    
    /**
     * Returns the reader.
     *
     * @return ReaderInterface
     */
    public function reader(): ReaderInterface;
    
    /**
     * Returns the writer.
     *
     * @return WriterInterface
     */
    public function writer(): WriterInterface;
    
    /**
     * Returns the modifiers.
     *
     * @return ModifiersInterface
     */
    public function modifiers(): ModifiersInterface;
    
    /**
     * Returns the meta data.
     *
     * @return array
     */
    public function meta(): array;
    
    /**
     * Returns the timestamp when processing started.
     *
     * @return DateTimeImmutable
     */
    public function startedAt(): DateTimeImmutable;
    
    /**
     * Returns the timestamp when processing finished.
     *
     * @return DateTimeImmutable
     */
    public function finishedAt(): DateTimeImmutable;
    
    /**
     * Returns the runtime in seconds.
     *
     * @return float
     */
    public function runtimeInSeconds(): float;
    
    /**
     * Returns a timeline summary for monitoring/logging.
     *
     * @return array
     */
    public function timeline(): array;
}