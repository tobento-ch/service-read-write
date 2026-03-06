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
use Tobento\Service\Repository\ReadRepositoryInterface;
use Tobento\Service\Repository\RepositoryReadException;

class RepositoryReader implements ReaderInterface
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
     * @param ReadRepositoryInterface $repository
     * @param array $where
     * @param array $orderBy
     * @param null|callable $objectToArray
     * @param int $previewRows
     */
    public function __construct(
        protected ReadRepositoryInterface $repository,
        protected array $where = [],
        protected array $orderBy = [],
        protected $objectToArray = null,
        protected int $previewRows = 3,
    ) {
        if (!is_null($this->objectToArray) && !is_callable($this->objectToArray)) {
            throw new \InvalidArgumentException('objectToArray must be a valid callable');
        }
    }
    
    /**
     * Returns the repository.
     *
     * @return ReadRepositoryInterface
     */
    public function repository(): ReadRepositoryInterface
    {
        return $this->repository;
    }

    /**
     * Returns the where parameters.
     *
     * @return array
     */
    public function where(): array
    {
        return $this->where;
    }
    
    /**
     * Returns the orderBy parameters.
     *
     * @return array
     */
    public function orderBy(): array
    {
        return $this->orderBy;
    }
    
    /**
     * Returns the objectToArray.
     *
     * @return null|callable
     */
    public function objectToArray(): null|callable
    {
        return $this->objectToArray;
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
        $object = $this->repository()->findOne(where: $this->where());
        
        if (is_null($object)) {
            return [];
        }
        
        $data = $this->convertObjectToArray($object);
        
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

        $items = $this->repository()->findAll(where: $this->where(), orderBy: $this->orderBy(), limit: $this->previewRows());

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }
            
            $item = $this->convertObjectToArray($item);
            
            foreach ($headers as $col) {
                $val = $item[$col] ?? '';

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
        return $this->repository()->count(where: $this->where());
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
        $queryLimit = [null, $offset];
        
        if ($limit !== null) {
            $queryLimit = [$limit, $offset];
        }
        
        $items = $this->repository()->findAll(where: $this->where(), orderBy: $this->orderBy(), limit: $queryLimit);
        
        $yielded = 0;

        foreach ($items as $item) {
            $this->currentOffset = $offset + $yielded;
            
            if (is_object($item)) {
                $item = $this->convertObjectToArray($item);
                
                yield new Row(key: $this->currentOffset, attributes: $item);
            } else {
                yield new SkipRow(
                    key: $this->currentOffset,
                    attributes: [],
                    reason: 'Repository returned non-object item'
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
     * Converts an entity object into an associative array.
     *
     * @param object $object The entity to convert.
     * @return array The converted array representation.
     */
    protected function convertObjectToArray(object $object): array
    {
        if ($this->objectToArray() !== null) {
            return ($this->objectToArray())($object);
        }

        // Default behavior
        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        return get_object_vars($object);
    }
}