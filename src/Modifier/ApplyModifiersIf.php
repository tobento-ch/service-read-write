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
use Tobento\Service\ReadWrite\ModifiersInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class ApplyModifiersIf implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * Applies one or more modifiers only when the given condition evaluates to true.
     * If the condition does not match, the wrapped modifiers are skipped and the
     * original row is returned unchanged.
     *
     * Supported condition types:
     * - bool:       true = always apply, false = never apply
     * - string:     apply if the field exists and is not empty
     * - array:      structured rule, e.g. ['field' => 'country', 'equals' => 'CH']
     * - callable:   fn(RowInterface $row): bool - custom evaluation logic
     *
     * @param callable|array|string|bool $condition
     *     The condition that determines whether the wrapped modifiers should be applied.
     *
     * @param ModifierInterface|ModifiersInterface $modifiers
     *     A single modifier or a chain of modifiers to apply when the condition matches.
     */
    public function __construct(
        private $condition,
        private ModifierInterface|ModifiersInterface $modifiers,
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
        // Condition not met return row unchanged
        if (! $this->shouldApply($row)) {
            return $row;
        }

        // Apply wrapped modifier(s)
        return $this->modifiers->modify($row, $reader, $writer);
    }
    
    /**
     * Evaluates whether the condition matches for the given row.
     *
     * @param RowInterface $row
     * @return bool True if modifiers should be applied.
     */
    private function shouldApply(RowInterface $row): bool
    {
        // bool
        if (is_bool($this->condition)) {
            return $this->condition;
        }

        // callable
        if (is_callable($this->condition)) {
            return (bool) ($this->condition)($row);
        }

        // string: field must exist and be truthy
        if (is_string($this->condition)) {
            return $row->has($this->condition) && !empty($row->get($this->condition));
        }

        // array: structured rule
        if (is_array($this->condition)) {
            $field = $this->condition['field'] ?? null;

            if (!is_string($field) || $field === '') {
                throw new ModifyException(row: $row, message: 'Invalid ApplyModifiersIf condition: missing field');
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