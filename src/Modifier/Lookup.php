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

final class Lookup implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param string $field   The source field (dot‑aware path).
     * @param string $into    The target field name to store the mapped value.
     * @param array<string, mixed>|callable(mixed, string):mixed $lookup
     *     Either a lookup array or a callable resolver.
     *     Callable signature: fn(mixed $value, string $field): mixed
     * @param bool $removeSourceField Remove the source field after mapping, default false.
     */
    public function __construct(
        private string $field,
        private string $into,
        private $lookup,
        private bool $removeSourceField = false,
    ) {
        if ($this->field === '' || $this->into === '') {
            throw new \InvalidArgumentException('Source and target fields must be defined');
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

        if ($row->has($this->field)) {
            $value = $row->get($this->field);
            $mapped = null;

            if (is_array($this->lookup)) {
                if (
                    (is_string($value) || is_int($value) || is_float($value))
                    && array_key_exists($value, $this->lookup)
                ) {
                    $mapped = $this->lookup[$value];
                }
            } elseif (is_callable($this->lookup)) {
                $mapped = ($this->lookup)($value, $this->field);
            }

            if ($mapped !== null) {
                $attributes = Arr::set($attributes, $this->into, $mapped);
            }

            if ($this->removeSourceField) {
                $attributes = Arr::delete($attributes, $this->field);
            }
        }

        return new Row(key: $row->key(), attributes: $attributes);
    }
}