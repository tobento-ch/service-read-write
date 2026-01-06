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

use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\ReadWrite\ModifierInterface;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

final class ColumnMap implements ModifierInterface
{
    /**
     * Create a new instance.
     *
     * @param array<string, string> $map ['from' => 'to']
     */
    public function __construct(
        private array $map,
    ) {
        if (empty($this->map)) {
            throw new \InvalidArgumentException('Map must not be empty');
        }
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
        $attributes = [];
        
        foreach($this->map as $from => $to) {
            if ($row->has($from)) {
                $attributes[$to] = $row->get($from);
            }
        }
        
        return new Row(key: $row->key(), attributes: $attributes);
    }
}