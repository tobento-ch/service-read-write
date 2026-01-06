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

final class DefaultValue implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array<string, mixed> $values
     */
    public function __construct(
        private array $values,
    ) {
        if (empty($this->values)) {
            throw new \InvalidArgumentException('No default values defined');
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

        foreach($this->values as $attr => $value) {
            if (!$row->has($attr) || $row->get($attr) === null || $row->get($attr) === '') {
                $attributes = Arr::set($attributes, $attr, $value);
            }
        }

        return new Row(key: $row->key(), attributes: $attributes);
    }
}