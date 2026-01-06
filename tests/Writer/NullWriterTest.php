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

namespace Tobento\Service\ReadWrite\Test\Writer;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Writer\Type;
use Tobento\Service\ReadWrite\Row\Row;

class NullWriterTest extends TestCase
{
    public function testTypeIsExport(): void
    {
        $writer = new NullWriter();
        $this->assertSame(Type::Export, $writer->type());
    }

    public function testColumnsReturnsEmptyArray(): void
    {
        $writer = new NullWriter();
        $this->assertSame([], $writer->columns());
    }

    public function testColumnsPreviewReturnsEmptyArray(): void
    {
        $writer = new NullWriter();
        $this->assertSame([], $writer->columnsPreview());
    }

    public function testStartDoesNothing(): void
    {
        $writer = new NullWriter();
        // Should not throw
        $writer->start();
        $this->assertTrue(true);
    }

    public function testWriteDoesNothing(): void
    {
        $writer = new NullWriter();
        $row = new Row(1, ['foo' => 'bar']);
        // Should not throw
        $writer->write($row);
        $this->assertTrue(true);
    }

    public function testFinishDoesNothing(): void
    {
        $writer = new NullWriter();
        // Should not throw
        $writer->finish();
        $this->assertTrue(true);
    }
}