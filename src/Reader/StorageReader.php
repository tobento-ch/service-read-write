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
 
namespace Tobento\Service\ReadWrite\Reader;

use Tobento\Service\ReadWrite\Exception\ReadException;
use Tobento\Service\ReadWrite\Exception\ReaderException;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\Storage\StorageInterface;
use Tobento\Service\Storage\ItemInterface;

class StorageReader implements ReaderInterface
{
    /**
     * @var int
     */
    protected int $currentOffset = 0;
    
    /**
     * @var bool
     */
    protected bool $finished = false;
    
    /**
     * Create a new instance.
     *
     * @param StorageInterface $storage
     * @param string $table
     * @param null|callable $query
     * @param int $previewRows
     */
    public function __construct(
        protected StorageInterface $storage,
        protected string $table,
        protected $query = null,
        protected int $previewRows = 3,
    ) {
        if (!is_null($this->query) && !is_callable($this->query)) {
            throw new \InvalidArgumentException('query must be a valid callable');
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
     * Returns the table.
     *
     * @return string
     */
    public function table(): string
    {
        return $this->table;
    }
    
    /**
     * Returns the query.
     *
     * @return null|callable
     */
    public function query(): null|callable
    {
        return $this->query;
    }
    
    /**
     * Returns the preview rows.
     *
     * @return int
     */
    public function previewRows(): int
    {
        return $this->previewRows;
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
        $item = $this->applyQuery($this->storage())->first();
        
        $data = $this->convertItemToArray($item);
        
        if (is_null($data)) {
            return [];
        }
        
        return array_keys($data);
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
        $headers = $this->columns();

        if (empty($headers)) {
            return [];
        }

        $colValues = array_fill_keys($headers, []);
        
        $items = $this->applyQuery($this->storage())->limit(number: $this->previewRows(), offset: 0)->get();
        
        foreach ($items as $item) {

            if (is_object($item)) {
                $item = $this->convertItemToArray($item);
            }
            
            if (!is_array($item)) {
                continue;
            }

            foreach ($headers as $col) {
                $val = $item[$col] ?? '';

                // Normalize JSON values to strings
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val);
                } elseif (is_scalar($val)) {
                    $val = (string)$val;
                } else {
                    $val = '';
                }

                if ($val !== '' && !in_array($val, $colValues[$col], true)) {
                    $colValues[$col][] = $val;
                }
            }
        }        

        return array_map(
            fn(array $vals) => implode(' | ', $vals),
            $colValues
        );
    }
    
    /**
     * Returns the total number of rows available in the data source.
     * For large or streaming data sets, this may be unknown.
     *
     * @return int|null Null if the total cannot be determined.
     */
    public function totalRows(): null|int
    {
        return $this->applyQuery($this->storage())->count();
    }
    
    /**
     * Reads rows from the data source.
     *
     * @param int $offset The starting position (zero-based) from which to read rows.
     * @param int|null $limit The maximum number of rows to read, or null to read all available.
     * @return iterable<RowInterface> An iterable collection of row objects.
     * @throws ReadException
     * @throws ReaderException
     */
    public function read(int $offset = 0, null|int $limit = null): iterable
    {
        $items = $this->applyQuery($this->storage())->limit(number: $limit, offset: $offset)->get();
        
        $yielded = 0;

        foreach ($items as $item) {
            $this->currentOffset = $offset + $yielded;
            
            $item = $this->convertItemToArray($item);
            
            if (!is_null($item)) {
                yield new Row(key: $this->currentOffset, attributes: $item);
            } else {
                yield new SkipRow(
                    key: $this->currentOffset,
                    attributes: [],
                    reason: 'Storage returned unsupported item type'
                );
            }
            
            $yielded++;
        }

        if ($limit === null || $yielded < $limit) {
            $this->finished = true;
        }
    }
    
    /**
     * Returns the current offset position after the last read operation.
     *
     * @return int
     */
    public function currentOffset(): int
    {
        return $this->currentOffset;
    }
    
    /**
     * Indicates whether the reader has reached the end of the data source.
     *
     * @return bool True if no more rows are available, false otherwise.
     */
    public function isFinished(): bool
    {
        return $this->finished;
    }
    
    /**
     * Applies query.
     *
     * @param StorageInterface $storage
     * @return StorageInterface
     */
    protected function applyQuery(StorageInterface $storage): StorageInterface
    {
        $storage = clone $storage->table($this->table());
        
        if ($this->query() !== null) {
            ($this->query())($storage);
        }
        
        return $storage;
    }
    
    /**
     * Converts an item into an associative array.
     *
     * @param mixed $item The entity to convert.
     * @return null|array The converted array representation or null.
     */
    protected function convertItemToArray(mixed $item): null|array
    {
        if (is_array($item)) {
            return $item;
        }
        
        if ($item instanceof ItemInterface) {
            return $item->all();
        }
        
        // Default behavior
        if (is_object($item) && method_exists($item, 'toArray')) {
            return $item->toArray();
        }

        return null;
    }
}