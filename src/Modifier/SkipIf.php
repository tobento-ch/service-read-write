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

use Tobento\Service\Collection\Arr;
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\ModifierInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class SkipIf implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param callable|array|string|bool $condition
     *     Supported forms:
     *     - bool: skip always (true) or never (false)
     *     - string: skip if field missing or empty
     *     - array: structured rule (field + equals/missing)
     *     - callable: fn(RowInterface $row): bool
     * @param string $reason A human-readable explanation for why the row is skipped.
     */
    public function __construct(
        private $condition,
        private string $reason = '',
    ) {}
    
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
        if ($this->shouldSkip($row)) {
            return new SkipRow(
                key: $row->key(),
                attributes: $row->all(),
                reason: $this->reason,
            );
        }

        return $row;
    }
    
    /**
     * Evaluate the skip condition.
     */
    private function shouldSkip(RowInterface $row): bool
    {
        // bool: skip always or never
        if (is_bool($this->condition)) {
            return $this->condition;
        }

        // callable: user-defined logic
        if (is_callable($this->condition)) {
            return (bool) ($this->condition)($row);
        }

        // string: skip if field missing or empty
        if (is_string($this->condition)) {
            return !$row->has($this->condition) || empty($row->get($this->condition));
        }

        // array: structured rule
        if (is_array($this->condition)) {
            $field = $this->condition['field'] ?? null;

            if (!is_string($field) || $field === '') {
                throw new ModifyException(row: $row, message: 'Invalid SkipIf modifier condition: missing field');
            }

            // missing check
            if (($this->condition['missing'] ?? false) === true) {
                return !$row->has($field) || empty($row->get($field));
            }

            // equals check
            if (array_key_exists('equals', $this->condition)) {
                return $row->get($field) === $this->condition['equals'];
            }
        }

        return false;
    }
}