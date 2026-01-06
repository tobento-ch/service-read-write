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

class ReadException extends ReaderException
{
    /**
     * Create a new instance.
     *
     * @param int $offset
     * @param null|int $limit
     * @param string $message
     * @param int $code
     * @param null|Throwable $previous
     */
    public function __construct(
        protected int $offset,
        protected null|int $limit = null,
        string $message = '',
        int $code = 0,
        null|Throwable $previous = null,
    ) {
        parent::__construct(message: $message, code: $code, previous: $previous);
    }
    
    /**
     * Returns the offset.
     *
     * @return int
     */
    public function offset(): int
    {
        return $this->offset;
    }
    
    /**
     * Returns the limit.
     *
     * @return null|int
     */
    public function limit(): null|int
    {
        return $this->limit;
    }
}