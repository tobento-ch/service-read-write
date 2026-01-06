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
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;
use Tobento\Service\Validation\ValidatorInterface;

final class Validation implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array $rules Validation rules in service-validation format.
     * @param ValidatorInterface $validator The validator instance from service-validation.
     * @param string $onFail
     *     One of: 'fail', 'skip'
     *     - fail will throw ModifyException
     *     - skip wiil return SkipRow
     */
    public function __construct(
        private array $rules,
        private ValidatorInterface $validator,
        private string $onFail = 'fail',
    ) {}
    
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
        $validation = $this->validator->validate(
            data: $row->all(),
            rules: $this->rules,
        );

        // Validation passed: return row unchanged
        if ($validation->isValid()) {
            return $row;
        }
        
        // Validation failed: collect messages grouped by field
        $errors = [];
        
        foreach($validation->errors() as $error) {
            $errors[$error->key()][] = $error->message();
        }

        $messages = implode('; ', array_map(
            static fn(string $field, array $messages): string =>
                $field . ': ' . implode(', ', $messages),
            array_keys($errors),
            $errors
        ));

        if ($this->onFail === 'skip') {
            return new SkipRow(
                key: $row->key(),
                attributes: $row->all(),
                reason: sprintf('Validation failed: %s', $messages),
            );
        }
        
        throw new ModifyException(
            row: $row,
            message: sprintf('Validation failed: %s', $messages),
        );
    }
}