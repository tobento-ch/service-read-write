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
 
namespace Tobento\Service\ReadWrite\Writer\Resource;

use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Writer\ResourceInterface;

final class InMemory implements ResourceInterface
{
    /**
     * @var string
     */
    private string $buffer = '';
    
    /**
     * @var bool
     */
    private bool $open = false;

    /**
     * Open the resource for writing.
     *
     * @return void
     * @throws WriterException
     */
    public function open(): void
    {
        $this->open = true;
    }
    
    /**
     * Check if the resource is currently open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->open;
    }

    /**
     * Write data to the resource.
     *
     * @param string $data
     * @return void
     * @throws WriterException
     */
    public function write(string $data): void
    {
        if (!$this->open) {
            throw new WriterException('Resource not open');
        }
        
        $this->buffer .= $data;
    }

    /**
     * Rewind the resource pointer.
     *
     * @return void
     * @throws WriterException
     */
    public function rewind(): void
    {
        // For memory, rewind just resets a pointer conceptually.
        // You could implement a cursor if needed, but often noop.
    }

    /**
     * Close the resource and release handles.
     *
     * @return void
     * @throws WriterException
     */
    public function close(): void
    {
        $this->open = false;
    }

    /**
     * Get the underlying handle or identifier.
     *
     * @return mixed
     */
    public function getHandle(): mixed
    {
        return null;
    }
    
    /**
     * Get the full written content.
     *
     * @return string
     */
    public function getContent(): string
    {
        return $this->buffer;
    }
}