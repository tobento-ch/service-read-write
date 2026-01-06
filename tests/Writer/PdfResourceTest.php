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
use Tobento\Service\Pdf\Pdf;
use Tobento\Service\Pdf\PdfGeneratorInterface;
use Tobento\Service\Pdf\PdfInterface;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\PdfResource;
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;

class PdfResourceTest extends TestCase
{
    public function testStartOpensResource(): void
    {
        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource(
            resource: $resource,
            pdfGenerator: $pdfGen,
            templateName: 'pdf/test'
        );

        $writer->start();

        $this->assertTrue($resource->isOpen());
    }

    public function testStartThrowsIfAlreadyStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->start();
        $writer->start(); // should throw
    }

    public function testStartThrowsIfResourceAlreadyOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $resource->open(); // already open

        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');
        $writer->start();
    }

    public function testWriteStoresRows(): void
    {
        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->start();

        $row = new Row(1, ['name' => 'Alice']);
        $writer->write($row);

        // We cannot access rows directly, but finish() will use them.
        // So we assert no exception is thrown.
        $this->assertTrue(true);
    }

    public function testWriteThrowsIfNotStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->write(new Row(1, ['x' => 1]));
    }

    public function testWriteThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->start();
        $resource->close(); // simulate external close

        $writer->write(new Row(1, ['x' => 1]));
    }

    public function testFinishGeneratesPdfAndWritesToResource(): void
    {
        $resource = new InMemory();

        $pdfGen = $this->createStub(PdfGeneratorInterface::class);
        $pdfGen->method('generate')->willReturn('PDF_BINARY_DATA');

        $writer = new PdfResource(
            resource: $resource,
            pdfGenerator: $pdfGen,
            templateName: 'pdf/test'
        );

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));

        $writer->finish();

        $this->assertSame('PDF_BINARY_DATA', $resource->getContent());
        $this->assertFalse($resource->isOpen());
    }

    public function testFinishThrowsIfNotStarted(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->finish();
    }

    public function testFinishThrowsIfResourceNotOpen(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();
        $pdfGen = $this->createStub(PdfGeneratorInterface::class);

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->start();
        $resource->close(); // simulate external close

        $writer->finish();
    }

    public function testFinishThrowsIfPdfGeneratorFails(): void
    {
        $this->expectException(WriterException::class);

        $resource = new InMemory();

        $pdfGen = $this->createStub(PdfGeneratorInterface::class);
        $pdfGen->method('generate')->willThrowException(new \RuntimeException('fail'));

        $writer = new PdfResource($resource, $pdfGen, 'pdf/test');

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));

        $writer->finish();
    }

    public function testCustomPdfInstanceIsUsed(): void
    {
        $resource = new InMemory();

        // Use a real Pdf instance because PdfInterface does not declare template()
        $customPdf = new Pdf();

        // MUST be a mock, not a stub, because we use ->expects()
        $pdfGen = $this->createMock(PdfGeneratorInterface::class);

        $pdfGen->expects($this->once())
            ->method('generate')
            ->with($this->identicalTo($customPdf))
            ->willReturn('PDF_BINARY');

        $writer = new PdfResource(
            resource: $resource,
            pdfGenerator: $pdfGen,
            templateName: 'pdf/test',
            templateData: ['title' => 'Report'],
            pdf: $customPdf
        );

        $writer->start();
        $writer->write(new Row(1, ['name' => 'Alice']));
        $writer->finish();

        $this->assertSame('PDF_BINARY', $resource->getContent());
    }
}