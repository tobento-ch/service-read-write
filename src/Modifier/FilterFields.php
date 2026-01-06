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

final class FilterFields implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array|string $fields Fields to keep.
     */
    public function __construct(
        private array|string $fields,
    ) {
        $this->fields = (array) $fields;

        if (empty($this->fields)) {
            throw new \InvalidArgumentException('Fields must not be empty');
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
        $filtered = [];

        foreach ($this->fields as $field) {
            if (Arr::has($attributes, $field)) {
                $filtered = Arr::set($filtered, $field, Arr::get($attributes, $field));
            }
        }

        return new Row(
            key: $row->key(),
            attributes: $filtered,
        );
    }
}