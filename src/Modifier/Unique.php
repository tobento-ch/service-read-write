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
 
namespace Tobento\Service\ReadWrite\Modifier;

use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\ModifierInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class Unique implements ModifierInterface
{
    /**
     * @var array<string, array<string, bool>> In-memory store of seen values per field.
     */
    private array $seen = [];

    /**
     * Create a new instance.
     *
     * @param array|string $fields Field or fields to enforce uniqueness on.
     * @param null|callable $lookup Optional lookup callable:
     *     fn(string $field, mixed $value, RowInterface $row): bool
     *     Should return true if the value already exists (duplicate).
     *
     *     If null, in-memory uniqueness is used.
     *
     * @param string $onFail One of:
     *     - 'fail' → throw ModifyException
     *     - 'skip' → return SkipRow
     */
    public function __construct(
        private array|string $fields,
        private $lookup = null,
        private string $onFail = 'fail',
    ) {
        $this->fields = (array) $fields;
        
        if ($this->lookup !== null && !is_callable($this->lookup)) {
            throw new \InvalidArgumentException('Lookup must be a callable or null');
        }
    }
    
    /**
     * Returns the modified row.
     *
     * @param RowInterface $row
     * @param ReaderInterface $reader
     * @param WriterInterface $writer
     * @return RowInterface
     * @throws ModifyException
     */
    public function modify(RowInterface $row, ReaderInterface $reader, WriterInterface $writer): RowInterface
    {
        foreach ($this->fields as $field) {
            $value = $row->get($field);

            // Null values are ignored
            if ($value === null) {
                continue;
            }

            // Use lookup callable if provided
            if ($this->lookup !== null) {
                $isDuplicate = ($this->lookup)($field, $value, $row);

                if ($isDuplicate) {
                    return $this->handleDuplicate($row, $field, $value);
                }

                continue;
            }

            // Default: in-memory uniqueness
            $key = $this->stringify($value);

            if (isset($this->seen[$field][$key])) {
                return $this->handleDuplicate($row, $field, $value);
            }

            // Mark as seen
            $this->seen[(string)$field][$key] = true;
        }

        return $row;
    }
    
    /**
     * Handle duplicate value according to onFail mode.
     */
    private function handleDuplicate(RowInterface $row, string $field, mixed $value): RowInterface
    {
        $message = sprintf(
            'Duplicate value for unique field "%s": %s',
            $field,
            $this->stringify($value),
        );

        if ($this->onFail === 'skip') {
            return new SkipRow(
                key: $row->key(),
                attributes: $row->all(),
                reason: $message,
            );
        }

        throw new ModifyException(row: $row, message: $message);
    }
    
    /**
     * Converts value to string.
     */
    private function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: var_export($value, true);
    }
}