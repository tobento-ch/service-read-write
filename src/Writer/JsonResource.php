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

final class JsonResource implements WriterInterface, ModeAwareInterface
{
    /**
     * @var Mode
     */
    private Mode $mode = Mode::Overwrite;
    
    /**
     * @var bool
     */
    private bool $first = true;
    
    /**
     * Create a new instance.
     *
     * @param ResourceInterface $resource
     */
    public function __construct(
        private ResourceInterface $resource,
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

        switch ($this->mode) {
            case Mode::Overwrite:
                // First batch: start a new JSON array
                $this->resource->write('[');
                $this->first = true;
                break;
            case Mode::Append:
                // Intermediate batch: continue writing, assume array is already open
                $this->first = false;
                break;
            case Mode::Finalize:
                // Final batch: continue writing, will close array in finish()
                $this->first = false;
                break;
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

        if (!$this->first) {
            $this->resource->write(',');
        }

        $this->first = false;

        $encoded = json_encode($row->all(), JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new WriterException('Failed to encode row to JSON');
        }

        $this->resource->write($encoded);
    }
    
    /**
     * Finish writing (close file, commit transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function finish(): void
    {
        if (!$this->resource->isOpen()) {
            throw new WriterException('Resource not open');
        }

        if ($this->mode === Mode::Finalize) {
            // Only close the JSON array in finalize mode
            $this->resource->write(']');
        }

        $this->resource->rewind();
        $this->first = true;
        $this->resource->close();
    }
}