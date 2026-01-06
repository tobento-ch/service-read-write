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

final class Redact implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array|string $fields Field or fields to redact.
     * @param mixed $replacement Value to replace with (default: null).
     */
    public function __construct(
        private array|string $fields,
        private mixed $replacement = null,
    ) {
        $this->fields = (array) $fields;
        
        if (empty($this->fields)) {
            throw new \InvalidArgumentException('No fields defined for redaction');
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

        foreach($this->fields as $field) {
            // If field does not exist, skip silently
            if (!Arr::has($attributes, $field)) {
                continue;
            }

            $attributes = Arr::set($attributes, $field, $this->replacement);
        }

        return new Row(
            key: $row->key(),
            attributes: $attributes,
        );
    }
}