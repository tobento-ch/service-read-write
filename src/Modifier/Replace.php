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

final class Replace implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array|string $fields Field or fields to apply replacements on.
     * @param array $replacements Key/value pairs of search => replace.
     * @param bool $strict If true, only exact matches are replaced.
     *                     If false, substring replacement is used.
     * @param bool $forceNullReplacement If true, null values are replaced too.
     */
    public function __construct(
        private array|string $fields,
        private array $replacements,
        private bool $strict = true,
        private bool $forceNullReplacement = false,
    ) {
        $this->fields = (array) $fields;

        if (empty($this->fields)) {
            throw new \InvalidArgumentException('No fields defined for replacement');
        }
        
        if (empty($this->replacements)) {
            throw new \InvalidArgumentException('Replacements must not be empty');
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

            // Replace null values if enabled
            if ($value === null && $this->forceNullReplacement) {
                if (array_key_exists('', $this->replacements)) {
                    $attributes = Arr::set($attributes, $field, $this->replacements['']);
                }
                continue;
            }

            // Skip null values if not forcing
            if ($value === null) {
                continue;
            }
            
            try {
                $newValue = $this->applyReplacements($value);
            } catch (\Throwable $e) {
                throw new ModifyException(
                    row: $row,
                    message: sprintf('Replacement failed for field "%s": %s', $field, $e->getMessage()),
                    previous: $e
                );
            }

            $attributes = Arr::set($attributes, $field, $newValue);
        }

        return new Row(
            key: $row->key(),
            attributes: $attributes,
        );
    }
    
    /**
     * Apply replacements to a value.
     */
    private function applyReplacements(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        
        foreach($this->replacements as $search => $replace) {
            if ($this->strict) {
                if ($value === (string)$search) {
                    return $replace;
                }
            } else {
                $value = str_replace((string) $search, (string) $replace, $value);
            }
        }

        return $value;
    }
}