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
use Tobento\Service\View\ViewInterface;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\HtmlResource;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;

class HtmlResourceTest extends TestCase
{
    public function testGetterMethods(): void
    {
        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource(
            resource: $resource,
            view: $view,
            templateName: 'html/test',
            templateData: ['foo' => 'bar'],
        );

        $this->assertSame($resource, $writer->resource());
        $this->assertSame($view, $writer->view());
        $this->assertSame('html/test', $writer->templateName());
        $this->assertSame(['foo' => 'bar'], $writer->templateData());
    }

    public function testStartOpensResource(): void
    {
        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->start();

        $this->assertTrue($resource->isOpen());
    }

    public function testStartThrowsIfAlreadyStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->start();
        $writer->start(); // should throw
    }

    public function testStartThrowsIfResourceAlreadyOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $resource->open(); // already open

        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');
        $writer->start();
    }

    public function testWriteStoresRows(): void
    {
        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->start();

        $row = new Row(1, ['name' => 'Alice']);
        $writer->write($row);

        // No direct access to rows, but no exception = success
        $this->assertTrue(true);
    }

    public function testWriteThrowsIfNotStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->write(new Row(1, ['x' => 1]));
    }

    public function testWriteThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->start();
        $resource->close(); // simulate external close

        $writer->write(new Row(1, ['x' => 1]));
    }

    public function testFinishRendersHtmlAndWritesToResource(): void
    {
        $resource = new InMemory();

        $view = $this->createStub(ViewInterface::class);
        $view->method('render')->willReturn('<html>OK</html>');

        $writer = new HtmlResource(
            resource: $resource,
            view: $view,
            templateName: 'html/test'
        );

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));

        $writer->finish();

        $this->assertSame('<html>OK</html>', $resource->getContent());
        $this->assertFalse($resource->isOpen());
    }

    public function testFinishThrowsIfNotStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->finish();
    }

    public function testFinishThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $view = $this->createStub(ViewInterface::class);

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->start();
        $resource->close(); // simulate external close

        $writer->finish();
    }

    public function testFinishThrowsIfRendererFails(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();

        $view = $this->createStub(ViewInterface::class);
        $view->method('render')->willThrowException(new \RuntimeException('fail'));

        $writer = new HtmlResource($resource, $view, 'html/test');

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));

        $writer->finish();
    }

    public function testCustomTemplateDataIsPassedToRenderer(): void
    {
        $resource = new InMemory();

        $view = $this->createMock(ViewInterface::class);

        $view->expects($this->once())
            ->method('render')
            ->with(
                $this->equalTo('html/test'),
                $this->callback(function ($data) {
                    return isset($data['title'])
                        && $data['title'] === 'Report'
                        && isset($data['rows'])
                        && count($data['rows']) === 1;
                })
            )
            ->willReturn('<html>OK</html>');

        $writer = new HtmlResource(
            resource: $resource,
            view: $view,
            templateName: 'html/test',
            templateData: ['title' => 'Report']
        );

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));
        $writer->finish();

        $this->assertSame('<html>OK</html>', $resource->getContent());
    }
}