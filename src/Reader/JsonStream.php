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

use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Items;
use Psr\Http\Message\StreamInterface;
use Tobento\Service\ReadWrite\Exception\ReadException;
use Tobento\Service\ReadWrite\Exception\ReaderException;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;

class JsonStream implements ReaderInterface
{
    /**
     * @var resource|null
     */
    protected $resource = null;
    
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
     * @param StreamInterface $stream
     * @param int $previewRows
     */
    public function __construct(
        StreamInterface $stream,
        protected int $previewRows = 3,
    ) {
        $this->resource = $stream->detach();
    }
    
    /**
     * Returns the resource.
     *
     * @return resource|null
     */
    public function resource()
    {
        return $this->resource;
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
        if (is_null($this->resource)) {
            return [];
        }
        
        rewind($this->resource);
        
        $items = Items::fromStream($this->resource, ['decoder' => new ExtJsonDecoder(true)]);
        
        foreach($items as $value) {
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

        if (empty($headers) || is_null($this->resource)) {
            return [];
        }

        // Prepare accumulator
        $colValues = array_fill_keys($headers, []);

        // Save current stream position
        $pos = ftell($this->resource);

        // Rewind to start
        rewind($this->resource);

        $items = Items::fromStream($this->resource, ['decoder' => new ExtJsonDecoder(true)]);

        $count = 0;

        foreach ($items as $item) {
            if ($count >= $this->previewRows) {
                break;
            }

            if (!is_array($item) || count($item) !== count($headers)) {
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

            $count++;
        }

        // Restore original stream position
        fseek($this->resource, $pos);

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
     * @psalm-suppress UnusedForeachValue
     */
    public function totalRows(): null|int
    {
        if (is_null($this->resource)) {
            return null;
        }

        rewind($this->resource);

        $items = Items::fromStream($this->resource, ['decoder' => new ExtJsonDecoder(true)]);

        $count = 0;
        
        foreach($items as $item) {
            $count++;
        }

        return $count;
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
        if (is_null($this->resource)) {
            return [];
        }
        
        rewind($this->resource);
        $items = Items::fromStream($this->resource, ['decoder' => new ExtJsonDecoder(true)]);
        
        $count = 0;
        $yielded = 0;
        
        foreach ($items as $key => $item) {
            if ($count++ < $offset) {
                continue;
            }
            
            if ($limit !== null && $yielded >= $limit) {
                break;
            }
            
            $this->currentOffset = $key;
            
            if (is_array($item)) {
                yield new Row(key: $key, attributes: $item);
            } else {
                yield new SkipRow(key: $key, attributes: [], reason: 'Unable to create row from non-array value');
            }
            
            $yielded++;
        }
        
        // Mark finished if we reached the end or consumed all available items
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