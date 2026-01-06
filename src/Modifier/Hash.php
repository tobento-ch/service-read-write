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

final class Hash implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array|string $fields Field or fields to hash.
     * @param callable $hasher fn(mixed $value): string
     */
    public function __construct(
        private array|string $fields,
        private $hasher,
    ) {
        $this->fields = (array) $fields;
        
        if (empty($this->fields)) {
            throw new \InvalidArgumentException('No fields defined for hashing');
        }

        if (!is_callable($this->hasher)) {
            throw new \InvalidArgumentException('Hasher must be a valid callable');
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

        foreach ($this->fields as $field) {
            $value = Arr::get($attributes, $field);

            // Skip null values
            if ($value === null) {
                continue;
            }

            try {
                $hashed = ($this->hasher)($value);
            } catch (\Throwable $e) {
                throw new ModifyException(
                    row: $row,
                    message: sprintf('Hashing failed for field "%s": %s', $field, $e->getMessage()),
                    previous: $e
                );
            }

            $attributes = Arr::set($attributes, $field, $hashed);
        }

        return new Row(
            key: $row->key(),
            attributes: $attributes,
        );
    }
}