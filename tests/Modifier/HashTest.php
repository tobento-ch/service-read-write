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
use Tobento\Service\ReadWrite\Modifier\Hash;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class HashTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    private function fakeHasher(): callable
    {
        return fn($value) => 'hashed:' . $value;
    }

    public function testHashesSingleField(): void
    {
        $modifier = new Hash('password', $this->fakeHasher());

        $row = new Row(1, ['password' => 'secret']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['password' => 'hashed:secret'],
            $modified->all()
        );
    }

    public function testHashesMultipleFields(): void
    {
        $modifier = new Hash(['a', 'b'], $this->fakeHasher());

        $row = new Row(1, ['a' => 'x', 'b' => 'y']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 'hashed:x', 'b' => 'hashed:y'],
            $modified->all()
        );
    }

    public function testSkipsNullValues(): void
    {
        $modifier = new Hash(['a', 'b'], $this->fakeHasher());

        $row = new Row(1, ['a' => null, 'b' => 'value']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => null, 'b' => 'hashed:value'],
            $modified->all()
        );
    }

    public function testHashesNestedField(): void
    {
        $modifier = new Hash('meta.token', $this->fakeHasher());

        $row = new Row(1, [
            'meta' => [
                'token' => 'abc123',
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'token' => 'hashed:abc123',
                ],
            ],
            $modified->all()
        );
    }

    public function testThrowsModifyExceptionOnHashFailure(): void
    {
        $this->expectException(ModifyException::class);

        $badHasher = function () {
            throw new \RuntimeException('boom');
        };

        $modifier = new Hash('field', $badHasher);

        $row = new Row(1, ['field' => 'test']);

        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testThrowsExceptionWhenNoFieldsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Hash([], $this->fakeHasher());
    }

    public function testThrowsExceptionWhenHasherNotCallable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Hash('field', 'not-a-callable');
    }
}