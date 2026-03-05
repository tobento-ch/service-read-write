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
 
namespace Tobento\Service\ReadWrite\Exception;

use Throwable;
use Tobento\Service\Message\MessagesInterface;
use Tobento\Service\ReadWrite\RowInterface;

class ModifyErrorsException extends ModifyException
{
    /**
     * Create a new instance.
     *
     * @param RowInterface $row
     * @param MessagesInterface $errors
     * @param string $message
     * @param int $code
     * @param null|Throwable $previous
     */
    public function __construct(
        RowInterface $row,
        protected MessagesInterface $errors,
        string $message = '',
        int $code = 0,
        null|Throwable $previous = null,
    ) {
        parent::__construct(
            row: $row,
            message: $message ?: (string)$errors,
            code: $code,
            previous: $previous
        );
    }

    /**
     * Returns the errors.
     *
     * @return MessagesInterface
     */
    public function errors(): MessagesInterface
    {
        return $this->errors;
    }
}