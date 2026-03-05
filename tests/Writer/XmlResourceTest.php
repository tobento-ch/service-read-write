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
use Tobento\Service\ReadWrite\Writer\XmlResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;
use InvalidArgumentException;

class XmlResourceTest extends TestCase
{
    public function testGetterMethods(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource(
            resource: $resource,
            rootElement: 'products',
            rowElement: 'product',
            rowWrapper: 'channel',
            rootAttributes: ['foo' => 'foo'],
        );
        
        $this->assertSame($resource, $writer->resource());
        $this->assertSame('products', $writer->rootElement());
        $this->assertSame('product', $writer->rowElement());
        $this->assertSame('channel', $writer->rowWrapper());
        $this->assertSame(['foo' => 'foo'], $writer->rootAttributes());
        $this->assertSame('1.0', $writer->xmlVersion());
        $this->assertSame('UTF-8', $writer->encoding());
    }
    
    public function testStartWritesXmlDeclarationAndRootInOverwriteMode(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'products', 'product');

        $writer->start();

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>', $resource->getContent());
        $this->assertStringContainsString('<products>', $resource->getContent());
    }

    public function testStartThrowsIfResourceAlreadyOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $resource->open(); // already open

        $writer = new XmlResource($resource, 'root', 'item');
        $writer->start();
    }

    public function testWriteSingleRow(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'products', 'product');

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $expected =
            '<?xml version="1.0" encoding="UTF-8"?>' .
            '<products>' .
            '<product><name>Alice</name></product>' .
            '</products>';

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteMultipleRows(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'items', 'item');

        $writer->start();
        $writer->write(new Row(1, ['a' => 1]));
        $writer->write(new Row(2, ['a' => 2]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $expected =
            '<?xml version="1.0" encoding="UTF-8"?>' .
            '<items>' .
            '<item><a>1</a></item>' .
            '<item><a>2</a></item>' .
            '</items>';

        $this->assertSame($expected, $resource->getContent());
    }

    public function testWriteThrowsIfWriterNotStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new XmlResource($resource, 'root', 'item');

        $writer->write(new Row(1, ['x' => 1])); // not started
    }

    public function testFinishClosesResourceAndRewinds(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'root', 'item');

        $writer->start();
        $writer->write(new Row(1, ['x' => 1]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $this->assertFalse($resource->isOpen());
        $this->assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><root><item><x>1</x></item></root>',
            $resource->getContent()
        );
    }

    public function testWrapperElementIsWritten(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'rss', 'item', 'channel');

        $writer->start();
        $writer->write(new Row(1, ['title' => 'Hello']));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $xml = $resource->getContent();

        $this->assertStringContainsString('<channel>', $xml);
        $this->assertStringContainsString('</channel>', $xml);
        $this->assertStringContainsString('<item><title>Hello</title></item>', $xml);
    }

    public function testAttributesAreWritten(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'root', 'entry');

        $writer->start();
        $writer->write(new Row(1, [
            '@id' => '123',
            'name' => 'Foo',
        ]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $xml = $resource->getContent();

        $this->assertStringContainsString('<entry id="123">', $xml);
        $this->assertStringContainsString('<name>Foo</name>', $xml);
    }

    public function testNestedArraysAreWritten(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'root', 'item');

        $writer->start();
        $writer->write(new Row(1, [
            'tags' => [
                'tag' => ['a', 'b'],
            ],
        ]));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $xml = $resource->getContent();

        $this->assertStringContainsString('<tags>', $xml);
        $this->assertStringContainsString('<tag>a</tag>', $xml);
        $this->assertStringContainsString('<tag>b</tag>', $xml);
        $this->assertStringContainsString('</tags>', $xml);
    }

    public function testEscapingIsApplied(): void
    {
        $resource = new InMemory();
        $writer = new XmlResource($resource, 'root', 'item');

        $writer->start();
        $writer->write(new Row(1, ['name' => 'A & B']));
        $writer->mode(Mode::Finalize);
        $writer->finish();

        $this->assertStringContainsString('<name>A &amp; B</name>', $resource->getContent());
    }

    public function testInvalidXmlVersionThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new XmlResource(new InMemory(), 'root', 'item', xmlVersion: 'bad');
    }

    public function testInvalidEncodingThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new XmlResource(new InMemory(), 'root', 'item', encoding: 'UTF 8');
    }

    public function testInvalidElementNameThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new XmlResource(new InMemory(), '123bad', 'item');
    }

    public function testFinishThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $writer = new XmlResource($resource, 'root', 'item');

        $writer->finish(); // not open
    }
}