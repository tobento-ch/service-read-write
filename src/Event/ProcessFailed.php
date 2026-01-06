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
 
namespace Tobento\Service\ReadWrite\Event;

use Tobento\Service\ReadWrite\ResultInterface;
use Throwable;

final class ProcessFailed
{
    /**
     * Create a new instance.
     *
     * @param ResultInterface $result
     * @param Throwable $exception
     */
    public function __construct(
        private ResultInterface $result,
        private Throwable $exception,
    ) {}
    
    /**
     * Returns the result.
     *
     * @return ResultInterface
     */
    public function result(): ResultInterface
    {
        return $this->result;
    }
    
    /**
     * Returns the exception.
     *
     * @return Throwable
     */
    public function exception(): Throwable
    {
        return $this->exception;
    }
}