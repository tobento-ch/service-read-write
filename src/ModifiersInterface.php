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
 
namespace Tobento\Service\ReadWrite;

use IteratorAggregate;
use Tobento\Service\ReadWrite\Exception\ModifyException;

/**
 * @extends IteratorAggregate<int, ModifierInterface>
 */
interface ModifiersInterface extends IteratorAggregate
{
    /**
     * Returns the modified row.
     *
     * @param RowInterface $row
     * @param ReaderInterface $reader
     * @param WriterInterface $writer
     * @return RowInterface
     * @throws ModifyException
     */
    public function modify(RowInterface $row, ReaderInterface $reader, WriterInterface $writer): RowInterface;
    
    /**
     * Add a modifier.
     *
     * @param ModifierInterface $modifier
     * @return static $this
     */
    public function add(ModifierInterface $modifier): static;
    
    /**
     * Add a modifier to the beginning.
     *
     * @param ModifierInterface $modifier
     * @return static $this
     */
    public function prepend(ModifierInterface $modifier): static;
    
    /**
     * Returns all modifiers.
     *
     * @return array<int, ModifierInterface>
     */
    public function all(): array;
}