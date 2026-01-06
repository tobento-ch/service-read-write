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

final class Compute implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param string $field Field to write the computed value to.
     * @param callable $computeFn A callable: function(RowInterface $row): mixed
     */
    public function __construct(
        private string $field,
        private $computeFn,
    ) {
        if ($this->field === '') {
            throw new \InvalidArgumentException('Field must not be empty');
        }
        
        if (!is_callable($this->computeFn)) {
            throw new \InvalidArgumentException('computeFn parameter must be a callable');
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

        $value = ($this->computeFn)($row);

        $attributes = Arr::set($attributes, $this->field, $value);

        return new Row(
            key: $row->key(),
            attributes: $attributes,
        );
    }
}