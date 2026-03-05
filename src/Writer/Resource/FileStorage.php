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

use Tobento\Service\FileStorage\FileWriteException;
use Tobento\Service\FileStorage\StorageInterface;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Writer\ResourceInterface;

final class FileStorage implements ResourceInterface
{
    /**
     * @var resource|false
     */
    private $handle = false;

    /**
     * Create a new instance.
     *
     * @param StorageInterface $storage
     * @param string $filename
     */
    public function __construct(
        private StorageInterface $storage,
        private string $filename,
    ) {}
    
    /**
     * Returns the storage.
     *
     * @return StorageInterface
     */
    public function storage(): StorageInterface
    {
        return $this->storage;
    }
    
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
     * Open the resource for writing.
     *
     * @return void
     * @throws WriterException
     */
    public function open(): void
    {
        $this->handle = fopen('php://temp', 'w+');
        
        if ($this->handle === false) {
            throw new WriterException('Unable to open temporary stream');
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
            throw new WriterException('Failed to write to temporary stream');
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
            throw new WriterException('Failed to rewind temporary stream');
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
        if (!$this->handle) {
            return;
        }

        try {
            rewind($this->handle);
            
            // Commit the stream contents to the storage backend
            $this->storage->write(
                path: $this->filename,
                content: $this->handle
            );
        } catch (FileWriteException $e) {
            throw new WriterException(
                message: sprintf('Failed to write to storage %s %s', $this->storage->name(), $this->filename),
                previous: $e,
            );
        } finally {
            $handle = $this->handle;
            $this->handle = false;
            fclose($handle);
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