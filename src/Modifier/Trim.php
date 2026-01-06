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

final class Trim implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array<array-key, string> $attributes
     * @param null|string $chars
     */
    public function __construct(
        private array $attributes,
        private null|string $chars = null,
    ) {
        if (empty($this->attributes)) {
            throw new \InvalidArgumentException('No attributes defined for trimming');
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

        foreach($this->attributes as $attr) {
            if ($row->has($attr) && is_string($value = $row->get($attr))) {
                $trimmed = $this->chars === null
                    ? trim($value)
                    : trim($value, $this->chars);

                $attributes = Arr::set($attributes, $attr, $trimmed);
            }
        }

        return new Row(key: $row->key(), attributes: $attributes);
    }
}