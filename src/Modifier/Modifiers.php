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
 
namespace Tobento\Service\ReadWrite\Modifier;

use Generator;
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\ModifierInterface;
use Tobento\Service\ReadWrite\ModifiersInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\SkippableInterface;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

class Modifiers implements ModifiersInterface
{
    /**
     * @var array<int, ModifierInterface>
     */
    protected array $modifiers = [];
    
    /**
     * Create a new instance.
     *
     * @param ModifierInterface $modifier
     */
    public function __construct(
        ModifierInterface ...$modifier,
    ) {
        $this->modifiers = $modifier;
    }

    /**
     * Returns the modified row.
     *
     * @param RowInterface $row
     * @param ReaderInterface $reader
     * @param WriterInterface $writer
     * @return RowInterface
     * @throws ModifyException
     */
    public function modify(RowInterface $row, ReaderInterface $reader, WriterInterface $writer): RowInterface
    {
        foreach($this->all() as $modifier) {
            $row = $modifier->modify(row: $row, reader: $reader, writer: $writer);
            
            // Stop the modifier chain immediately
            if ($row instanceof SkippableInterface) {
                return $row;
            }
        }
        
        return $row;
    }
    
    /**
     * Add a modifier.
     *
     * @param ModifierInterface $modifier
     * @return static $this
     */
    public function add(ModifierInterface $modifier): static
    {
        $this->modifiers[] = $modifier;
        return $this;
    }
    
    /**
     * Add a modifier to the beginning.
     *
     * @param ModifierInterface $modifier
     * @return static $this
     */
    public function prepend(ModifierInterface $modifier): static
    {
        array_unshift($this->modifiers, $modifier);
        return $this;
    }
    
    /**
     * Returns all modifiers.
     *
     * @return array<int, ModifierInterface>
     */
    public function all(): array
    {
        return $this->modifiers;
    }
    
    /**
     * Returns an iterator for the modifiers.
     *
     * @return Generator
     */
    public function getIterator(): Generator
    {
        foreach($this->all() as $modifier) {
            yield $modifier;
        }
    }
}