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
 
namespace Tobento\Service\ReadWrite;

use Tobento\Service\ReadWrite\Row\SkippableInterface;
use Throwable;

interface ResultHandlerInterface
{
    /**
     * Handle a successfully processed row.
     *
     * @param RowInterface $row
     */
    public function handleRowSuccess(RowInterface $row): void;

    /**
     * Handle a skipped row.
     *
     * @param SkippableInterface $row
     */
    public function handleRowSkip(SkippableInterface $row): void;

    /**
     * Handle a failed row and its exception.
     *
     * @param RowInterface $row
     * @param Throwable $exception
     */
    public function handleRowFailure(RowInterface $row, Throwable $exception): void;

    /**
     * Handle the overall result after processing.
     *
     * @param ResultInterface $result
     */
    public function handleResult(ResultInterface $result): void;
}