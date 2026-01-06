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

final class Mask implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array|string $fields Field or fields to mask.
     * @param null|callable $masker fn(mixed $value): string
     *     If null, the default masking rules are applied.
     */
    public function __construct(
        private array|string $fields,
        private $masker = null,
    ) {
        $this->fields = (array) $fields;

        if (empty($this->fields)) {
            throw new \InvalidArgumentException('No fields defined for masking');
        }
        
        if ($this->masker !== null && !is_callable($this->masker)) {
            throw new \InvalidArgumentException('Masker must be a valid callable');
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
            $value = Arr::get($attributes, $field);

            // Skip null values
            if ($value === null) {
                continue;
            }

            try {
                $masked = $this->masker
                    ? ($this->masker)($value)
                    : $this->defaultMask($value);
            } catch (\Throwable $e) {
                throw new ModifyException(
                    row: $row,
                    message: sprintf('Masking failed for field "%s": %s', $field, $e->getMessage()),
                    previous: $e
                );
            }

            $attributes = Arr::set($attributes, $field, $masked);
        }

        return new Row(
            key: $row->key(),
            attributes: $attributes,
        );
    }
    
    /**
     * Default masking logic for common data types.
     */
    private function defaultMask(mixed $value): string
    {
        // Email masking: john@example.com to j***@example.com
        if (is_string($value) && str_contains($value, '@')) {
            [$local, $domain] = explode('@', $value, 2);
            $localMasked = $this->maskString($local);
            return $localMasked . '@' . $domain;
        }

        // String masking: "Jonathan" to "J*****n"
        if (is_string($value)) {
            return $this->maskString($value);
        }

        // Number masking: 123456789 to 12****89
        if (is_numeric($value)) {
            return $this->maskString((string) $value);
        }

        // Fallback: convert to string and mask
        return $this->maskString((string) $value);
    }

    /**
     * Masks a string by keeping first and last character.
     */
    private function maskString(string $value): string
    {
        $len = strlen($value);

        if ($len <= 2) {
            return str_repeat('*', $len);
        }

        return $value[0] . str_repeat('*', $len - 2) . $value[$len - 1];
    }
}