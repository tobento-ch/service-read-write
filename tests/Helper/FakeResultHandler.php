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

namespace Tobento\Service\ReadWrite\Test\Helper;

use Throwable;
use Tobento\Service\ReadWrite\ResultHandlerInterface;
use Tobento\Service\ReadWrite\ResultInterface;
use Tobento\Service\ReadWrite\RowInterface;

class FakeResultHandler implements ResultHandlerInterface
{
    public array $success = [];
    public array $failure = [];
    public array $skipped = [];
    public ?ResultInterface $result = null;

    public function handleRowSuccess(RowInterface $row): void
    {
        $this->success[] = $row;
    }

    public function handleRowFailure(RowInterface $row, Throwable $exception): void
    {
        $this->failure[] = [$row, $exception];
    }

    public function handleRowSkip(RowInterface $row): void
    {
        $this->skipped[] = $row;
    }

    public function handleResult(ResultInterface $result): void
    {
        $this->result = $result;
    }
}