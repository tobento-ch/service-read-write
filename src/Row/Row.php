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
 
namespace Tobento\Service\ReadWrite\Row;

use Generator;
use Tobento\Service\Collection\Arr;
use Tobento\Service\Collection\Collection;
use Tobento\Service\Iterable\Iter;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\Support\Arrayable;
use Tobento\Service\Support\Jsonable;

class Row implements RowInterface, Arrayable, Jsonable
{
    /**
     * @var array<string, mixed> The attributes.
     */
    protected array $attributes = [];
    
    /**
     * Create a new instance.
     *
     * @param int|string $key
     * @param iterable<string, mixed> $attributes
     */
    public function __construct(
        protected int|string $key,
        iterable $attributes,
    ){
        $this->attributes = Iter::toArray(iterable: $attributes);
    }
    
    /**
     * Returns the key.
     *
     * @return int|string
     */
    public function key(): int|string
    {
        return $this->key;
    }
    
    /**
     * Returns whether an attribute exists or not.
     *
     * @return bool
     */
    public function has(string|int $key): bool
    {
        return Arr::has($this->attributes, $key);
    }
    
    /**
     * Get an attribute value by key.
     *
     * @param string|int $key The key.
     * @param mixed $default A default value.
     * @return mixed The the default value if not exist.
     */
    public function get(string|int $key, mixed $default = null): mixed
    {
        return Arr::get($this->attributes, $key, $default);
    }    
    
    /**
     * Returns all attributes.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Returns a new Collection with the attributes.
     *
     * @return Collection
     */
    public function collection(): Collection
    {
        return new Collection($this->attributes);
    }
    
    /**
     * Returns an iterator for the attributes.
     *
     * @return Generator
     */
    public function getIterator(): Generator
    {
        foreach($this->attributes as $key => $item) {
            yield $key => $item;
        }
    }
    
    /**
     * Object to array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->collection()->toArray();
    }
    
    /**
     * Object to json.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return $this->collection()->toJson();
    }    

    /**
     * Returns the number of attributes.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->attributes);
    }
    
    /**
     * Determine if an attribute exists at an offset.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Get an attribute at a given offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset];
    }

    /**
     * Set the attribute at a given offset.
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_string($offset)) {
            $this->attributes[$offset] = $value;
            return;
        }
        
        throw new \InvalidArgumentException('Offset must be a string');
    }

    /**
     * Unset the attribute at a given offset.
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->attributes[$offset]);
    }
}