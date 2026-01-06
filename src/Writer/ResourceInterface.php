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
 
namespace Tobento\Service\ReadWrite\Writer;

use Tobento\Service\ReadWrite\Exception\WriterException;

interface ResourceInterface
{
    /**
     * Open the resource for writing.
     *
     * @return void
     * @throws WriterException
     */
    public function open(): void;
    
    /**
     * Check if the resource is currently open.
     *
     * @return bool
     */
    public function isOpen(): bool;

    /**
     * Write data to the resource.
     *
     * @param string $data
     * @return void
     * @throws WriterException
     */
    public function write(string $data): void;

    /**
     * Rewind the resource pointer.
     *
     * @return void
     * @throws WriterException
     */
    public function rewind(): void;

    /**
     * Close the resource and release handles.
     *
     * @return void
     * @throws WriterException
     */
    public function close(): void;

    /**
     * Get the underlying handle or identifier.
     *
     * @return mixed
     */
    public function getHandle(): mixed;
}