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
use Tobento\Service\Encryption\EncrypterInterface;
use Tobento\Service\ReadWrite\Modifier\Encrypt;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class EncryptTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    private function fakeEncrypter(): EncrypterInterface
    {
        return new class implements EncrypterInterface {
            public function name(): string
            {
                return 'default';
            }
            
            public function encrypt(mixed $data): string
            {
                return 'enc:' . (string)$data;
            }

            public function decrypt(string $encrypted): mixed
            {
                return substr($value, 4);
            }
        };
    }

    public function testEncryptsSingleField(): void
    {
        $modifier = new Encrypt('secret', $this->fakeEncrypter());

        $row = new Row(1, ['secret' => 'abc']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['secret' => 'enc:abc'],
            $modified->all()
        );
    }

    public function testEncryptsMultipleFields(): void
    {
        $modifier = new Encrypt(['a', 'b'], $this->fakeEncrypter());

        $row = new Row(1, ['a' => 'x', 'b' => 'y']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => 'enc:x', 'b' => 'enc:y'],
            $modified->all()
        );
    }

    public function testSkipsNullValues(): void
    {
        $modifier = new Encrypt(['a', 'b'], $this->fakeEncrypter());

        $row = new Row(1, ['a' => null, 'b' => 'value']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['a' => null, 'b' => 'enc:value'],
            $modified->all()
        );
    }

    public function testEncryptsNestedField(): void
    {
        $modifier = new Encrypt('meta.token', $this->fakeEncrypter());

        $row = new Row(1, [
            'meta' => [
                'token' => 'abc123',
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'meta' => [
                    'token' => 'enc:abc123',
                ],
            ],
            $modified->all()
        );
    }

    public function testThrowsModifyExceptionOnEncryptionFailure(): void
    {
        $this->expectException(ModifyException::class);

        $badEncrypter = new class implements EncrypterInterface {
            public function name(): string
            {
                return 'default';
            }
            public function encrypt(mixed $data): string
            {
                throw new \RuntimeException('boom');
            }
            public function decrypt(string $encrypted): mixed
            {
                return $value;
            }
        };

        $modifier = new Encrypt('field', $badEncrypter);

        $row = new Row(1, ['field' => 'test']);

        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testThrowsExceptionWhenNoFieldsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Encrypt([], $this->fakeEncrypter());
    }
}