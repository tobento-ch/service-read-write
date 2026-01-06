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

use ArrayAccess;
use Countable;
use IteratorAggregate;
use Tobento\Service\Collection\Collection;
use Tobento\Service\Support\Arrayable;
use Tobento\Service\Support\Jsonable;

/**
 * @extends ArrayAccess<string, mixed>
 * @extends IteratorAggregate<array-key, mixed>
 */
interface RowInterface extends ArrayAccess, Countable, IteratorAggregate, Arrayable, Jsonable
{
    /**
     * Returns the key.
     *
     * @return int|string
     */
    public function key(): int|string;
    
    /**
     * Returns whether an attribute exists or not.
     *
     * @return bool
     */
    public function has(string|int $key): bool;
    
    /**
     * Get an attribute value by key.
     *
     * @param string|int $key The key.
     * @param mixed $default A default value.
     * @return mixed The the default value if not exist.
     */
    public function get(string|int $key, mixed $default = null): mixed;
    
    /**
     * Returns all attributes.
     *
     * @return array
     */
    public function all(): array;
    
    /**
     * Returns a new Collection with the attributes.
     *
     * @return Collection
     */
    public function collection(): Collection;
}