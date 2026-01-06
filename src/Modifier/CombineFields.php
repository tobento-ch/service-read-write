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
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class CombineFields implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array<int, string> $fields  Source fields (dot‑aware) to combine.
     * @param string $into                Target field name for the combined value.
     * @param string $separator           Separator between values, default is space.
     * @param bool $removeSourceFields    Remove source fields after combining, default false.
     */
    public function __construct(
        private array $fields,
        private string $into,
        private string $separator = ' ',
        private bool $removeSourceFields = false,
    ) {
        if (empty($this->fields)) {
            throw new \InvalidArgumentException('Fields must not be empty');
        }

        if ($this->into === '') {
            throw new \InvalidArgumentException('Target field "into" must not be empty');
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
        $attributes = $row->all();
        $values = [];
        
        foreach($this->fields as $field) {
            if ($row->has($field)) {
                $value = $row->get($field);

                // Only include string or numeric values
                if (is_string($value)) {
                    $values[] = $value;
                } elseif (is_int($value) || is_float($value)) {
                    $values[] = (string)$value;
                }

                if ($this->removeSourceFields) {
                    $attributes = Arr::delete($attributes, $field);
                }
            }
        }

        if (!empty($values)) {
            $attributes = Arr::set($attributes, $this->into, implode($this->separator, $values));
        }

        return new Row(key: $row->key(), attributes: $attributes);
    }
}