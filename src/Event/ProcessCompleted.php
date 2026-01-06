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

final class ProcessCompleted
{
    /**
     * Create a new instance.
     *
     * @param ResultInterface $result
     */
    public function __construct(
        private ResultInterface $result,
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
}