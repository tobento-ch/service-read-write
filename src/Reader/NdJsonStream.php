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

use Psr\Http\Message\StreamInterface;
use Tobento\Service\ReadWrite\Exception\ReadException;
use Tobento\Service\ReadWrite\Exception\ReaderException;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;

class NdJsonStream implements ReaderInterface
{
    /**
     * @var string
     */
    protected string $readBuffer = '';
    
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
        protected StreamInterface $stream,
        protected int $previewRows = 3,
    ) {}
    
    /**
     * Returns the stream.
     *
     * @return StreamInterface
     */
    public function stream(): StreamInterface
    {
        return $this->stream;
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
        $this->stream->rewind();

        $line = $this->readLine();
        $data = json_decode($line ?? '', true);

        return is_array($data) ? array_keys($data) : [];
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

        // Reset buffer so first line is read correctly
        $this->readBuffer = '';
        
        // Prepare accumulator
        $colValues = array_fill_keys($headers, []);

        // Save current stream position
        $pos = $this->stream->tell();

        // Rewind to start
        $this->stream->rewind();

        $count = 0;

        while ($count < $this->previewRows) {
            $line = $this->readLine();
            if ($line === null) {
                break;
            }

            $data = json_decode($line, true);

            if (!is_array($data) || count($data) !== count($headers)) {
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

        // Restore original stream position
        $this->stream->seek($pos);

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
        $limit ??= PHP_INT_MAX;
        
        // Reset buffer and seek
        $this->readBuffer = '';
        $this->stream->seek($offset);

        if ($offset > 0) {
            // Check if offset is at start of a line
            $this->stream->seek($offset - 1);
            $prev = $this->stream->read(1);

            // Restore offset
            $this->stream->seek($offset);

            // Only skip if offset is NOT at start of a line
            if ($prev !== "\n") {
                $this->skipPartialLine();
            }
        }

        $count = 0;

        while ($count < $limit) {
            
            //$pos = $this->stream->tell();
            $line = $this->readLine();
            
            if ($line === null) {
                $this->finished = true;
                break;
            }
            
            $data = json_decode($line, true);

            if (is_array($data)) {
                yield new Row(key: $count, attributes: $data);
            } else {
                yield new SkipRow(key: $count, attributes: [], reason: 'Invalid JSON line');
            }

            $count++;
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
    
    protected function readLine(): null|string
    {
        $lastPos = $this->stream->tell();

        while (true) {
            // Do we have a full line?
            if (($pos = strpos($this->readBuffer, "\n")) !== false) {
                $line = substr($this->readBuffer, 0, $pos);
                $this->readBuffer = substr($this->readBuffer, $pos + 1);

                // Store the starting offset of this line
                $this->currentOffset = $this->stream->tell() - strlen($this->readBuffer) - strlen($line) - 1;
                
                return $this->normalizeLine($line);
            }

            // EOF: flush buffer
            if ($this->stream->eof()) {
                if ($this->readBuffer === '') {
                    return null;
                }

                $line = $this->readBuffer;
                $this->readBuffer = '';
                
                $this->currentOffset = $this->stream->tell() - strlen($line);
                
                return $this->normalizeLine($line);
            }

            // Try reading more data
            $chunk = $this->stream->read(8192);
            $currentPos = $this->stream->tell();

            // NO PROGRESS = STOP (prevents infinite loop)
            if ($chunk === '' || $currentPos === $lastPos) {
                return null;
            }

            $lastPos = $currentPos;
            $this->readBuffer .= $chunk;
        }
    }
    
    protected function skipPartialLine(): void
    {
        while (!$this->stream->eof()) {
            $chunk = $this->stream->read(8192);

            $newlinePos = strpos($chunk, "\n");

            if ($newlinePos !== false) {
                // Move pointer to JUST AFTER the newline
                $absolutePos = $this->stream->tell() - strlen($chunk) + $newlinePos + 1;
                $this->stream->seek($absolutePos);
                return;
            }
        }
    }

    protected function normalizeLine(string $line): null|string
    {
        // Remove BOM only at beginning
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
        $line = trim($line);

        return $line === '' ? null : $line;
    }
}