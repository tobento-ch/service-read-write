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

final class LocalFile implements ResourceInterface
{
    /**
     * @var resource|false
     */
    private $handle = false;

    /**
     * Create a new instance.
     *
     * @param string $filename
     * @param string $mode
     */
    public function __construct(
        private string $filename,
        private string $mode = 'w',
    ) {}
    
    /**
     * Returns the filename.
     *
     * @return string
     */
    public function filename(): string
    {
        return $this->filename;
    }
    
    /**
     * Returns the mode.
     *
     * @return string
     */
    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * Open the resource for writing.
     *
     * @return void
     * @throws WriterException
     */
    public function open(): void
    {
        $this->handle = @fopen($this->filename, $this->mode);

        if ($this->handle === false) {
            throw new WriterException(sprintf('Unable to open file: %s', $this->filename));
        }
    }
    
    /**
     * Check if the resource is currently open.
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->handle !== false;
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
        if (!$this->handle) {
            throw new WriterException('Resource not open');
        }
        
        if (fwrite($this->handle, $data) === false) {
            throw new WriterException(sprintf('Failed to write to file: %s', $this->filename));
        }
    }

    /**
     * Rewind the resource pointer.
     *
     * @return void
     * @throws WriterException
     */
    public function rewind(): void
    {
        if (!$this->handle || !rewind($this->handle)) {
            throw new WriterException(sprintf('Failed to rewind file: %s', $this->filename));
        }
    }

    /**
     * Close the resource and release handles.
     *
     * @return void
     * @throws WriterException
     */
    public function close(): void
    {
        if ($this->handle) {
            $handle = $this->handle;

            if (!fclose($handle)) {
                throw new WriterException(
                    sprintf('Failed to close file: %s', $this->filename)
                );
            }

            $this->handle = false;
        }
    }

    /**
     * Get the underlying handle or identifier.
     *
     * @return mixed
     */
    public function getHandle(): mixed
    {
        return $this->handle;
    }
}