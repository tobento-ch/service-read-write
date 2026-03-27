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

use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;
use Tobento\Service\View\ViewInterface;

/**
 * Writes rows into an HTML document using a template-based rendering system.
 */
final class HtmlResource implements WriterInterface
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
     * @param ViewInterface $view
     * @param string $templateName
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        private ResourceInterface $resource,
        private ViewInterface $view,
        private string $templateName,
        private array $templateData = [],
    ) {}

    /**
     * Returns the resource.
     *
     * @return ResourceInterface
     */
    public function resource(): ResourceInterface
    {
        return $this->resource;
    }

    /**
     * Returns the view renderer.
     *
     * @return ViewInterface
     */
    public function view(): ViewInterface
    {
        return $this->view;
    }

    /**
     * Returns the template name.
     *
     * @return string
     */
    public function templateName(): string
    {
        return $this->templateName;
    }
    
    /**
     * Returns the template data.
     *
     * @return array
     */
    public function templateData(): array
    {
        return $this->templateData;
    }

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
            throw new WriterException('HTML writer already started');
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
            throw new WriterException('HTML writer not started');
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
            throw new WriterException('HTML writer not started');
        }

        if (!$this->resource->isOpen()) {
            throw new WriterException('Resource not open');
        }

        try {
            $html = $this->view->render(
                view: $this->templateName,
                data: array_merge(
                    $this->templateData,
                    ['rows' => $this->rows],
                )
            );

            $this->resource->write($html);
            $this->resource->rewind();
        } catch (\Throwable $e) {
            throw new WriterException(sprintf('Failed to generate HTML: %s', $e->getMessage()), 0, $e);
        } finally {
            $this->resource->close();
            $this->started = false;
        }
    }
}