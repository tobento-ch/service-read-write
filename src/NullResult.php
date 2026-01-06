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

final class NullResult implements ResultInterface
{
    /**
     * Returns the number of successful rows.
     *
     * @return int
     */
    public function successfulRows(): int
    {
        return 0;
    }
    
    /**
     * Returns the number of failed rows.
     *
     * @return int
     */
    public function failedRows(): int
    {
        return 0;
    }
    
    /**
     * Returns the number of skipped rows.
     *
     * @return int
     */
    public function skippedRows(): int
    {
        return 0;
    }
    
    /**
     * Returns the reader.
     *
     * @return ReaderInterface
     */
    public function reader(): ReaderInterface
    {
        return new Reader\IterableReader([]);
    }
    
    /**
     * Returns the writer.
     *
     * @return WriterInterface
     */
    public function writer(): WriterInterface
    {
        return new Writer\NullWriter();
    }
    
    /**
     * Returns the modifiers.
     *
     * @return ModifiersInterface
     */
    public function modifiers(): ModifiersInterface
    {
        return new Modifier\Modifiers();
    }
    
    /**
     * Returns the meta data.
     *
     * @return array
     */
    public function meta(): array
    {
        return [];
    }
    
    /**
     * Returns the timestamp when processing started.
     *
     * @return DateTimeImmutable
     */
    public function startedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
    
    /**
     * Returns the timestamp when processing finished.
     *
     * @return DateTimeImmutable
     */
    public function finishedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
    
    /**
     * Returns the runtime in seconds.
     *
     * @return float
     */
    public function runtimeInSeconds(): float
    {
        return 0;
    }
    
    /**
     * Returns a timeline summary for monitoring/logging.
     *
     * @return array
     */
    public function timeline(): array
    {
        return [];
    }
}