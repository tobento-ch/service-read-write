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

class CsvStream implements ReaderInterface
{
    protected null|array $headers = null;
    protected int $currentOffset = 0;
    protected bool $finished = false;

    /**
     * Create a new instance
     *
     * @param StreamInterface $stream
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     * @param int $previewRows
     */
    public function __construct(
        protected StreamInterface $stream,
        protected string $delimiter = ',',
        protected string $enclosure = '"',
        protected string $escape = '\\',
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
     * Returns the delimiter.
     *
     * @return string
     */
    public function delimiter(): string
    {
        return $this->delimiter;
    }
    
    /**
     * Returns the enclosure.
     *
     * @return string
     */
    public function enclosure(): string
    {
        return $this->enclosure;
    }
    
    /**
     * Returns the escape.
     *
     * @return string
     */
    public function escape(): string
    {
        return $this->escape;
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
        $this->prepareHeaders();
        $headers = $this->headers ?: [];
        return $headers;
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
        $this->prepareHeaders();
        $headers = $this->headers ?? [];

        if (empty($headers)) {
            return [];
        }

        // Save current position
        $pos = $this->stream->tell();

        // Rewind and skip header row
        $this->stream->rewind();
        $this->readCsvLine(); // discard header

        $colValues = array_fill_keys($headers, []);

        for ($i = 0; $i < $this->previewRows; $i++) {
            $line = $this->readCsvLine();
            if ($line === null) {
                break;
            }

            if (count($line) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $line);

            foreach ($data as $col => $value) {
                if ($value !== '' && !in_array($value, $colValues[$col], true)) {
                    $colValues[$col][] = $value;
                }
            }
        }

        // Restore original position
        $this->stream->seek($pos);

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
        $count = 0;

        // Always prepare headers
        $this->prepareHeaders();

        // If starting at beginning, skip header row
        if ($offset === 0) {
            $this->stream->rewind();
            $this->readCsvLine(); // discard header row
            $this->currentOffset = $this->stream->tell();
        } else {
            $this->stream->seek($offset);
            $this->currentOffset = $offset;
        }

        while ($count < $limit) {
            $line = $this->readCsvLine();
            if ($line === null) {
                $this->finished = true;
                break;
            }

            if ($this->headers && count($line) !== count($this->headers)) {
                yield new SkipRow($this->currentOffset, [], 'Invalid CSV row');
            } else {
                $data = array_combine($this->headers, $line);
                yield new Row($this->currentOffset, $data);
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

    protected function prepareHeaders(): void
    {
        if ($this->headers !== null) {
            return;
        }

        $this->stream->rewind();
        $line = $this->readCsvLine();
        $this->headers = $line ?: [];
    }

    protected function readCsvLine(): ?array
    {
        if ($this->stream->eof()) {
            return null;
        }

        $line = $this->stream->read(4096);

        if ($line === '') {
            return null;
        }

        // Read until newline
        while (!str_contains($line, "\n") && !$this->stream->eof()) {
            $line .= $this->stream->read(4096);
        }

        // Split at first newline
        $pos = strpos($line, "\n");

        if ($pos !== false) {
            $row = substr($line, 0, $pos + 1);
            $remaining = substr($line, $pos + 1);

            // Seek back unread bytes
            $this->stream->seek($this->stream->tell() - strlen($remaining));
        } else {
            $row = $line;
        }

        $this->currentOffset += strlen($row);

        return $this->parseCsvLine($row);
    }
    
    protected function parseCsvLine(string $line): array
    {
        $line = preg_replace('/^\xEF\xBB\xBF/', '', $line); // remove BOM
        return str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
    }
}