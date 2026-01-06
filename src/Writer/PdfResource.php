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
 
namespace Tobento\Service\ReadWrite\Writer;

use Tobento\Service\Pdf\Pdf;
use Tobento\Service\Pdf\PdfGeneratorInterface;
use Tobento\Service\Pdf\PdfInterface;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

/**
 * Writes rows into a PDF document using a PDF export.
 */
final class PdfResource implements WriterInterface
{
    /**
     * @var bool
     */
    private bool $started = false;
    
    /**
     * @var RowInterface[]
     */
    private array $rows = [];

    /**
     * Create a new instance.
     *
     * @param ResourceInterface $resource
     * @param PdfGeneratorInterface $pdfGenerator
     * @param string $templateName
     * @param array<string, mixed> $templateData
     * @param null|PdfInterface $pdf
     */
    public function __construct(
        private ResourceInterface $resource,
        private PdfGeneratorInterface $pdfGenerator,
        private string $templateName,
        private array $templateData = [],
        private null|PdfInterface $pdf = null,
    ) {}

    /**
     * Returns the writer type.
     *
     * @return Type
     */
    public function type(): Type
    {
        return Type::Export;
    }

    /**
     * Returns the column names.
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return [];
    }

    /**
     * Returns a preview of column values.
     *
     * @return array<string, string>
     */
    public function columnsPreview(): array
    {
        return [];
    }

    /**
     * Start writing.
     *
     * @return void
     * @throws WriterException
     */
    public function start(): void
    {
        if ($this->started) {
            throw new WriterException('PDF writer already started');
        }

        if ($this->resource->isOpen()) {
            throw new WriterException('Resource already open');
        }

        $this->resource->open();
        $this->started = true;
    }

    /**
     * Write a row.
     *
     * @param RowInterface $row
     * @return void
     * @throws WriteException
     * @throws WriterException
     */
    public function write(RowInterface $row): void
    {
        if (!$this->started) {
            throw new WriterException('PDF writer not started');
        }

        if (!$this->resource->isOpen()) {
            throw new WriterException('Resource not open');
        }

        $this->rows[] = $row;
    }

    /**
     * Finish writing and generate the PDF.
     *
     * @return void
     * @throws WriterException
     */
    public function finish(): void
    {
        if (!$this->started) {
            throw new WriterException('PDF writer not started');
        }

        if (!$this->resource->isOpen()) {
            throw new WriterException('Resource not open');
        }

        try {
            $pdf = $this->pdf ?? new Pdf();

            $pdf->template(
                name: $this->templateName,
                data: array_merge(
                    $this->templateData,
                    ['rows' => $this->rows],
                ),
            );

            $binary = $this->pdfGenerator->generate(pdf: $pdf);

            $this->resource->write($binary);
            $this->resource->rewind();
        } catch (\Throwable $e) {
            throw new WriterException(sprintf('Failed to generate PDF: %s', $e->getMessage()), 0, $e);
        } finally {
            $this->resource->close();
            $this->started = false;
        }
    }
}