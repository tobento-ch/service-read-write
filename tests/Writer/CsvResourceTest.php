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
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\CsvResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;

class CsvResourceTest extends TestCase
{
    public function testStartWritesBomInOverwriteMode(): void
    {
        $resource = new InMemory();
        $writer = new CsvResource($resource);

        $writer->start();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $resource->getContent());
    }

    public function testStartThrowsIfResourceAlreadyOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $resource->open(); // already open

        $writer = new CsvResource($resource);
        $writer->start();
    }

    public function testWriteWritesHeaderAndRow(): void
    {
        $resource = new InMemory();
        $writer = new CsvResource($resource);

        $writer->start();

        $row = new Row(1, ['name' => 'Alice', 'age' => 30]);
        $writer->write($row);

        $expected =
            "\xEF\xBB\xBF" .
            "name,age\n" .
            "Alice,30\n";

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteMultipleRowsWritesHeaderOnce(): void
    {
        $resource = new InMemory();
        $writer = new CsvResource($resource);

        $writer->start();

        $writer->write(new Row(1, ['a' => 1, 'b' => 2]));
        $writer->write(new Row(2, ['a' => 3, 'b' => 4]));

        $expected =
            "\xEF\xBB\xBF" .
            "a,b\n" .
            "1,2\n" .
            "3,4\n";

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new CsvResource($resource);

        $writer->write(new Row(1, ['x' => 1]));
    }

    public function testFinishClosesResource(): void
    {
        $resource = new InMemory();
        $writer = new CsvResource($resource);

        $writer->start();
        $this->assertTrue($resource->isOpen());

        $writer->finish();
        $this->assertFalse($resource->isOpen());
    }

    public function testStartDoesNotWriteBomInAppendMode(): void
    {
        $resource = new InMemory();
        $writer = new CsvResource($resource);
        $writer->mode(Mode::Append);

        $writer->start();

        $this->assertSame('', $resource->getContent());
    }
    
    public function testWriteWithoutBom(): void
    {
        $resource = new InMemory();

        // Disable BOM
        $writer = new CsvResource(
            resource: $resource,
            delimiter: ',',
            enclosure: '"',
            escape: '\\',
            writeBom: false
        );

        $writer->start();

        $writer->write(new Row(1, ['name' => 'Alice', 'age' => 30]));

        $expected =
            "name,age\n" .
            "Alice,30\n";

        $this->assertSame($expected, $resource->getContent());
    }

    public function testCustomDelimiterEnclosureEscape(): void
    {
        $resource = new InMemory();
        $writer = new CsvResource($resource, delimiter: ';', enclosure: "'", escape: '\\');

        $writer->start();
        $writer->write(new Row(1, ['title' => "A;B", 'note' => "O'Reilly"]));

        $expected =
            "\xEF\xBB\xBF" .
            "title;note\n" .
            "'A;B';'O''Reilly'\n";

        $this->assertSame($expected, $resource->getContent());
    }
}