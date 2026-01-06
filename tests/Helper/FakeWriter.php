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

use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\ModeAwareInterface;
use Tobento\Service\ReadWrite\Writer\Type;
use Tobento\Service\ReadWrite\WriterInterface;

class FakeWriter implements WriterInterface, ModeAwareInterface
{
    public array $written = [];
    public ?Mode $mode = null;
    public bool $started = false;
    public bool $finished = false;

    public function mode(Mode $mode): void
    {
        $this->mode = $mode;
    }

    public function start(): void
    {
        $this->started = true;
    }

    public function write(RowInterface $row): void
    {
        $this->written[] = $row;
    }

    public function finish(): void
    {
        $this->finished = true;
    }

    public function type(): Type
    {
        return Type::Export;
    }

    public function columns(): array
    {
        return [];
    }

    public function columnsPreview(): array
    {
        return [];
    }
}
