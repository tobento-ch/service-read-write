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

use Tobento\Service\ReadWrite\RowInterface;

interface SkippableInterface extends RowInterface
{
    /**
     * Returns the reason why the row was skipped.
     *
     * @return string
     */
    public function reason(): string;
}