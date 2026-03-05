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

use Throwable;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;
use Tobento\Service\Storage\StorageInterface;

final class StorageWriter implements WriterInterface
{
    /**
     * Create a new instance.
     *
     * @param StorageInterface $storage
     * @param null|callable $writer function(RowInterface, StorageInterface): void
     * @param string $idName
     * @param array<int, string> $columns
     * @param array<string, string> $columnsPreview
     */
    public function __construct(
        private StorageInterface $storage,
        private $writer = null,
        private string $idName = 'id',
        private array $columns = [],
        private array $columnsPreview = [],
    ) {
        if (!is_null($this->writer) && !is_callable($this->writer)) {
            throw new \InvalidArgumentException('Writer must be a valid callable');
        }
    }
    
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
     * Returns the writer.
     *
     * @return null|callable
     */
    public function writer(): null|callable
    {
        return $this->writer;
    }
    
    /**
     * Returns the id name.
     *
     * @return string
     */
    public function idName(): string
    {
        return $this->idName;
    }
    
    /**
     * Returns the writer type.
     *
     * @return Type
     */
    public function type(): Type
    {
        // Writing into storage is an import operation
        return Type::Import;
    }
    
    /**
     * Returns the column names (schema definition).
     *
     * Example: ['title', 'status', 'created_at']
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * Returns a preview of column values.
     * Keys are column names, values are representative or aggregated values.
     *
     * Example: ['title' => 'Lorem', 'status' => 'Draft | Pending']
     *
     * @return array<string, string>
     */
    public function columnsPreview(): array
    {
        return $this->columnsPreview;
    }
    
    /**
     * Start writing (open file, init transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function start(): void
    {
        // No-op: storage usually doesn’t need explicit start
    }
    
    /**
     * Write a row.
     *
     * @param RowInterface $row
     * @return void
     * @throws WriteException
     * @throws WriterException
     */
    public function write(RowInterface $row): void
    {
        try {
            if ($this->writer) {
                ($this->writer)($row, $this->storage);
                return;
            }
            
            $attributes = $row->all();
            
            if (isset($attributes[$this->idName])) {
                $this->storage->where($this->idName, '=', $attributes[$this->idName])->update($attributes);
                return;
            }
            
            $this->storage->insert($attributes);

        } catch (Throwable $e) {
            throw new WriteException(row: $row, message: $e->getMessage(), previous: $e);
        }
    }
    
    /**
     * Finish writing (close file, commit transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function finish(): void
    {
        // No-op: storage usually doesn’t need explicit finish
    }
}