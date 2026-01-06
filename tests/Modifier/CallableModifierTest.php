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

namespace Tobento\Service\ReadWrite\Test\Modifier;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Modifier\CallableModifier;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Writer\NullWriter;

class CallableModifierTest extends TestCase
{
    public function testCallableIsExecutedAndReturnsModifiedRow(): void
    {
        $reader = new IterableReader([
            new Row(key: 1, attributes: ['title' => 'Hello']),
        ]);

        $writer = new NullWriter();

        $originalRow = new Row(key: 1, attributes: ['title' => 'Hello']);
        $modifiedRow = new Row(key: 1, attributes: ['title' => 'Modified']);

        $modifier = new CallableModifier(
            function (RowInterface $row, $r, $w) use ($originalRow, $modifiedRow, $reader, $writer) {
                // Ensure correct objects are passed
                $this->assertSame($originalRow, $row);
                $this->assertSame($reader, $r);
                $this->assertSame($writer, $w);

                return $modifiedRow;
            }
        );

        $result = $modifier->modify($originalRow, $reader, $writer);

        $this->assertSame($modifiedRow, $result);
    }

    public function testThrowsExceptionForInvalidCallable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CallableModifier('not-a-callable');
    }

    public function testCallableCanTransformRow(): void
    {
        $reader = new IterableReader([]);
        $writer = new NullWriter();

        $row = new Row(key: 1, attributes: ['value' => 10]);

        $modifier = new CallableModifier(function (RowInterface $row) {
            return new Row(
                key: $row->key(),
                attributes: ['value' => $row->get('value') * 2]
            );
        });

        $result = $modifier->modify($row, $reader, $writer);

        $this->assertSame(20, $result->get('value'));
    }
}