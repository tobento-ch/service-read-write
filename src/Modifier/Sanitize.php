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

use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\ModifierInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;
use Tobento\Service\Sanitizer\SanitizerInterface;

final class Sanitize implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array<string, string> $rules
     * @param SanitizerInterface $sanitizer
     * @param bool $strictSanitation If true, sanitizes missing data too, otherwise not.
     * @param bool $returnSanitizedOnly If true, returns only the sanitized data, otherwise all.
     */
    public function __construct(
        private array $rules,
        private SanitizerInterface $sanitizer,
        private bool $strictSanitation = false,
        private bool $returnSanitizedOnly = false,
    ) {
        if (empty($this->rules)) {
            throw new \InvalidArgumentException('No sanitize rules defined');
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
        $sanitized = $this->sanitizer->sanitize(
            data: $row->all(),
            sanitation: $this->rules,
            strictSanitation: $this->strictSanitation,
            returnSanitizedOnly: $this->returnSanitizedOnly,
        );

        return new Row(key: $row->key(), attributes: $sanitized);
    }
}