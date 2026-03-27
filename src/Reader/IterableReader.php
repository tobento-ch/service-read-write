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

use Iterator;
use Tobento\Service\ReadWrite\Exception\ReadException;
use Tobento\Service\ReadWrite\Exception\ReaderException;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;

class IterableReader implements ReaderInterface
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
     * @param iterable $iterable
     * @param int $previewRows
     */
    public function __construct(
        protected iterable $iterable,
        protected int $previewRows = 3,
    ) {}
    
    /**
     * Returns the iterable.
     *
     * @return iterable
     */
    public function iterable(): iterable
    {
        return $this->iterable;
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
        foreach($this->iterable as $value) {
            return is_array($value) ? array_keys($value) : [];
        }
        
        return [];
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

        // Prepare accumulator
        $colValues = array_fill_keys($headers, []);

        $count = 0;

        foreach ($this->iterable as $value) {
            if ($count >= $this->previewRows) {
                break;
            }

            // Normalize row to array
            if ($value instanceof RowInterface) {
                $data = $value->all();
            } elseif (is_array($value)) {
                $data = $value;
            } else {
                continue; // skip invalid rows
            }

            // Skip rows with mismatched columns
            if (count($data) !== count($headers)) {
                continue;
            }

            foreach ($headers as $col) {
                $val = $data[$col] ?? '';

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

            $count++;
        }

        // Convert arrays to "A | B | C"
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
        // If the iterable is an array, we can count directly
        if (is_array($this->iterable)) {
            return count($this->iterable);
        }

        // If the iterable implements Countable, use that
        if ($this->iterable instanceof \Countable) {
            return count($this->iterable);
        }

        // Fallback: unknown
        return null;
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
        $count = 0;
        $yielded = 0;
        
        foreach($this->iterable as $key => $value) {
            if ($count++ < $offset) {
                continue;
            }
            
            if ($limit !== null && $yielded >= $limit) {
                break;
            }
            
            $this->currentOffset = $key;
            
            if ($value instanceof RowInterface) {
                yield $value;
            } elseif (is_array($value)) {
                yield new Row(key: $key, attributes: $value);
            } else {
                yield new SkipRow(key: $key, attributes: [], reason: 'Unable to create row from non-array value');
            }
            
            $yielded++;
        }
        
        // Loop finished naturally: reader finished
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
}