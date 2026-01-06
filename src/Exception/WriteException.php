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
use Tobento\Service\ReadWrite\RowInterface;

class WriteException extends WriterException
{
    /**
     * Create a new instance.
     *
     * @param RowInterface $row
     * @param string $message
     * @param int $code
     * @param null|Throwable $previous
     */
    public function __construct(
        protected RowInterface $row,
        string $message = '',
        int $code = 0,
        null|Throwable $previous = null,
    ) {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }
    
    /**
     * Returns the row.
     *
     * @return RowInterface
     */
    public function row(): RowInterface
    {
        return $this->row;
    }
}