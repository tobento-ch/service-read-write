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

use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class CsvResource implements WriterInterface, ModeAwareInterface
{
    /**
     * @var Mode
     */
    private Mode $mode = Mode::Overwrite;
    
    /**
     * @var bool
     */
    private bool $headerWritten = false;

    /**
     * Create a new instance.
     *
     * @param ResourceInterface $resource
     * @param string $delimiter
     * @param string $enclosure
     * @param string $escape
     * @param bool $writeBom
     */
    public function __construct(
        private ResourceInterface $resource,
        private string $delimiter = ',',
        private string $enclosure = '"',
        private string $escape = '\\',
        private bool $writeBom = true,
    ) {}
    
    /**
     * Returns the resource.
     *
     * @return ResourceInterface
     */
    public function resource(): ResourceInterface
    {
        return $this->resource;
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
     * Returns whetger to write Bom.
     *
     * @return bool
     */
    public function writeBom(): bool
    {
        return $this->writeBom;
    }
    
    /**
     * Sets the mode.
     *
     * @param Mode $mode
     * @return void
     */
    public function mode(Mode $mode): void
    {
        $this->mode = $mode;
    }

    /**
     * Returns the writer type.
     *
     * @return Type
     */
    public function type(): Type
    {
        return Type::Export;
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
        return [];
    }
    
    /**
     * Start writing (open file, init transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function start(): void
    {
        if ($this->resource->isOpen()) {
            throw new WriterException('Resource already open');
        }

        $this->resource->open();

        // Only write BOM if starting fresh
        if ($this->mode === Mode::Overwrite && $this->writeBom) {
            $this->resource->write("\xEF\xBB\xBF");
        }
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
        if (!$this->resource->isOpen()) {
            throw new WriterException('Resource not open');
        }

        $data = $row->all();

        // Write header if not yet written
        if (!$this->headerWritten) {
            $this->resource->write($this->toCsv(array_keys($data)));
            $this->headerWritten = true;
        }

        // Write row
        $this->resource->write($this->toCsv($data));
    }
    
    /**
     * Finish writing (close file, commit transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function finish(): void
    {
        if ($this->resource->isOpen()) {
            $this->resource->close();
        }
    }
    
    /**
     * Convert array to CSV line.
     *
     * @param array $fields
     * @return string
     */
    private function toCsv(array $fields): string
    {
        $fields = array_map(
            fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : $v,
            $fields
        );
        
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $fields, $this->delimiter, $this->enclosure, $this->escape);
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string)$csv;
    }
}