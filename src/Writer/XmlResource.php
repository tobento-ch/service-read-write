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

use InvalidArgumentException;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class XmlResource implements WriterInterface, ModeAwareInterface
{
    /**
     * @var Mode
     */
    private Mode $mode = Mode::Overwrite;
    
    /**
     * @var bool
     */
    private bool $started = false;

    /**
     * Create a new instance.
     *
     * @param ResourceInterface $resource
     *     The underlying resource used for writing output (file, stream, etc.).
     *
     * @param string $rootElement
     *     The name of the root XML element wrapping the entire document.
     *     Example: "rss", "urlset", "products".
     *
     * @param string $rowElement
     *     The XML element name used for each written row.
     *     Example: "item", "url", "product".
     *
     * @param string|null $rowWrapper
     *     Optional wrapper element placed between the root element and rows.
     *     Useful for formats like RSS where rows must be inside <channel>.
     *     Example: "channel". Use null for no wrapper.
     *
     * @param array<string, scalar|null> $rootAttributes
     *     Attributes applied to the root element.
     *     Example: ['xmlns:g' => 'http://base.google.com/ns/1.0'].
     *
     * @param string $xmlVersion
     *     XML version used in the declaration. Defaults to "1.0".
     *
     * @param string $encoding
     *     Character encoding used in the XML declaration. Defaults to "UTF-8".
     */
    public function __construct(
        private ResourceInterface $resource,
        private string $rootElement,
        private string $rowElement,
        private null|string $rowWrapper = null,
        private array $rootAttributes = [],
        private string $xmlVersion = '1.0',
        private string $encoding = 'UTF-8',
    ) {
        $this->assertValidXmlVersion($xmlVersion);
        $this->assertValidEncoding($encoding);
        
        $this->assertValidElementName($rootElement);
        $this->assertValidElementName($rowElement);

        if ($rowWrapper !== null) {
            $this->assertValidElementName($rowWrapper);
        }
    }
    
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
     * Returns the rootElement.
     *
     * @return string
     */
    public function rootElement(): string
    {
        return $this->rootElement;
    }
    
    /**
     * Returns the rowElement.
     *
     * @return string
     */
    public function rowElement(): string
    {
        return $this->rowElement;
    }
    
    /**
     * Returns the rowWrapper.
     *
     * @return null|string
     */
    public function rowWrapper(): null|string
    {
        return $this->rowWrapper;
    }
    
    /**
     * Returns the rootAttributes.
     *
     * @return array
     */
    public function rootAttributes(): array
    {
        return $this->rootAttributes;
    }
    
    /**
     * Returns the xmlVersion.
     *
     * @return string
     */
    public function xmlVersion(): string
    {
        return $this->xmlVersion;
    }
    
    /**
     * Returns the encoding.
     *
     * @return string
     */
    public function encoding(): string
    {
        return $this->encoding;
    }
    
    /**
     * Sets the mode.
     *
     * @param Mode $mode
     * @return void
     */
    public function mode(Mode $mode): void
    {
        $this->mode = $mode;
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
     * Returns the column names (schema definition).
     *
     * Example: ['title', 'status', 'created_at']
     *
     * @return array<int, string>
     */
    public function columns(): array
    {
        return [];
    }

    /**
     * Returns a preview of column values.
     * Keys are column names, values are representative or aggregated values.
     *
     * Example: ['title' => 'Lorem', 'status' => 'Draft | Pending']
     *
     * @return array<string, string>
     */
    public function columnsPreview(): array
    {
        return [];
    }
    
    /**
     * Start writing (open file, init transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function start(): void
    {
        if ($this->resource->isOpen()) {
            throw new WriterException('Resource already open');
        }

        $this->resource->open();

        if ($this->mode === Mode::Overwrite) {
            $this->writeXmlDeclaration();
            $this->writeRootOpen();

            if ($this->rowWrapper !== null) {
                $this->resource->write(sprintf('<%s>', $this->rowWrapper));
            }
        }

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
        if (! $this->started) {
            throw new WriterException('Writer not started');
        }

        $xml = $this->rowToXml($row->all());
        $this->resource->write($xml);
    }
    
    /**
     * Finish writing (close file, commit transaction, etc).
     *
     * @return void
     * @throws WriterException
     */
    public function finish(): void
    {
        if (! $this->resource->isOpen()) {
            throw new WriterException('Resource not open');
        }

        if ($this->mode === Mode::Finalize) {
            if ($this->rowWrapper !== null) {
                $this->resource->write(sprintf('</%s>', $this->rowWrapper));
            }

            $this->writeRootClose();
        }

        $this->resource->rewind();
        $this->resource->close();
        $this->started = false;
    }
    
    /**
     * Writes the XML declaration header to the resource.
     */
    private function writeXmlDeclaration(): void
    {
        $this->resource->write(
            sprintf('<?xml version="%s" encoding="%s"?>', $this->xmlVersion, $this->encoding)
        );
    }

    /**
     * Writes the opening root element, including any configured attributes.
     */
    private function writeRootOpen(): void
    {
        $attrs = $this->buildAttributes($this->rootAttributes);

        $this->resource->write(
            sprintf('<%s%s>', $this->rootElement, $attrs)
        );
    }

    /**
     * Writes the closing tag for the root element.
     */
    private function writeRootClose(): void
    {
        $this->resource->write(sprintf('</%s>', $this->rootElement));
    }

    /**
     * Builds an attribute string for an XML element.
     *
     * @param array<string, scalar|null> $attributes
     * @return string XML‑safe attribute string.
     */
    private function buildAttributes(array $attributes): string
    {
        $buffer = '';

        foreach ($attributes as $name => $value) {
            if ($value === null) {
                continue;
            }

            $buffer .= sprintf(
                ' %s="%s"',
                $name,
                htmlspecialchars((string)$value, ENT_XML1)
            );
        }

        return $buffer;
    }

    /**
     * Converts a row array into an XML element string.
     *
     * @param array<string, mixed> $data
     * @return string XML representation of the row.
     */
    private function rowToXml(array $data): string
    {
        $attributes = [];
        $elements = [];

        foreach ($data as $key => $value) {
            if (str_starts_with($key, '@')) {
                $attributes[substr($key, 1)] = $value;
            } else {
                $elements[] = [$key, $value];
            }
        }

        $xml = '<' . $this->rowElement . $this->buildAttributes($attributes) . '>';

        foreach ($elements as [$key, $value]) {
            $xml .= $this->valueToXml($key, $value);
        }

        $xml .= sprintf('</%s>', $this->rowElement);

        return $xml;
    }

    /**
     * Converts a value into an XML element, supporting nested arrays.
     *
     * @param string $key   Element name.
     * @param mixed  $value Element value or nested structure.
     * @return string XML representation of the value.
     */
    private function valueToXml(string $key, mixed $value): string
    {
        if (is_array($value)) {
            $xml = '';

            foreach ($value as $subKey => $subValue) {
                // numeric keys → repeat the same element name
                $elementName = is_int($subKey) ? $key : $subKey;

                $xml .= $this->valueToXml($elementName, $subValue);
            }

            return sprintf('<%s>%s</%s>', $key, $xml, $key);
        }

        return sprintf(
            '<%1$s>%2$s</%1$s>',
            $key,
            htmlspecialchars((string)$value, ENT_XML1)
        );
    }
    
    /**
     * Ensures the XML version is syntactically valid.
     *
     * @throws InvalidArgumentException
     */
    private function assertValidXmlVersion(string $version): void
    {
        // Accepts: 1.0, 1.1, 2.0, etc.
        if (!preg_match('/^[0-9]+(\.[0-9]+)?$/', $version)) {
            throw new InvalidArgumentException(
                sprintf('Invalid XML version "%s". Expected something like "1.0" or "1.1".', $version)
            );
        }
    }

    /**
     * Ensures the encoding value is safe for XML declaration.
     *
     * @throws InvalidArgumentException
     */
    private function assertValidEncoding(string $encoding): void
    {
        // Accepts: UTF-8, ISO-8859-1, ascii, utf8, etc.
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $encoding)) {
            throw new InvalidArgumentException(
                sprintf('Invalid XML encoding "%s".', $encoding)
            );
        }
    }
    
    /**
     * Validates that the given string is a syntactically valid XML element name.
     *
     * @throws InvalidArgumentException
     */
    private function assertValidElementName(string $name): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9._:-]*$/', $name)) {
            throw new InvalidArgumentException("Invalid XML element name: {$name}");
        }
    }
}