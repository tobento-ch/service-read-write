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
 
namespace Tobento\Service\ReadWrite\Row;

use Tobento\Service\Iterable\Iter;

class SkipRow extends Row implements SkippableInterface
{
    /**
     * Create a new instance.
     *
     * @param int|string $key
     * @param iterable<string, mixed> $attributes
     * @param string $reason
     */
    final public function __construct(
        protected int|string $key,
        iterable $attributes,
        protected string $reason,
    ){
        $this->attributes = Iter::toArray(iterable: $attributes);
    }

    /**
     * Returns the reason.
     *
     * @return string
     */
    public function reason(): string
    {
        return $this->reason;
    }
}