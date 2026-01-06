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

final class Split implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param string $field The source field (dot-aware path) to split.
     * @param array<int, string> $into Target fields (dot-aware paths) to receive the split parts.
     * @param string $separator Separator used to split the string.
     * @param bool $removeSourceField Remove the source field after splitting.
     */
    public function __construct(
        private string $field,
        private array $into,
        private string $separator = ' ',
        private bool $removeSourceField = false,
    ) {
        if ($this->field === '' || empty($this->into)) {
            throw new \InvalidArgumentException('Source field and target fields must be defined');
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

            if (is_string($value)) {
                $parts = array_map('trim', explode($this->separator, $value));

                foreach ($this->into as $index => $targetField) {
                    if ($targetField === '') {
                        continue;
                    }

                    $part = $parts[$index] ?? null;
                    
                    if ($part !== null && $part !== '') {
                        $attributes = Arr::set($attributes, $targetField, $part);
                    }
                }
            }

            if ($this->removeSourceField) {
                $attributes = Arr::delete($attributes, $this->field);
            }
        }

        return new Row(key: $row->key(), attributes: $attributes);
    }
}