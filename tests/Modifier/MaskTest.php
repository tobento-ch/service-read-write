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
use Tobento\Service\ReadWrite\Modifier\Mask;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Exception\ModifyException;

class MaskTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testMasksStringDefault(): void
    {
        $modifier = new Mask('name');

        $row = new Row(1, ['name' => 'Jonathan']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['name' => 'J******n'],
            $modified->all()
        );
    }

    public function testMasksShortStringDefault(): void
    {
        $modifier = new Mask('code');

        $row = new Row(1, ['code' => 'AB']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // length <= 2 → fully masked
        $this->assertSame(
            ['code' => '**'],
            $modified->all()
        );
    }

    public function testMasksEmailDefault(): void
    {
        $modifier = new Mask('email');

        $row = new Row(1, ['email' => 'john@example.com']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['email' => 'j**n@example.com'],
            $modified->all()
        );
    }

    public function testMasksNumberDefault(): void
    {
        $modifier = new Mask('id');

        $row = new Row(1, ['id' => 123456]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // 1****6
        $this->assertSame(
            ['id' => '1****6'],
            $modified->all()
        );
    }

    public function testMasksNestedField(): void
    {
        $modifier = new Mask('meta.token');

        $row = new Row(1, [
            'meta' => [
                'token' => 'abcdef',
            ],
        ]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // a****f
        $this->assertSame(
            [
                'meta' => [
                    'token' => 'a****f',
                ],
            ],
            $modified->all()
        );
    }

    public function testCustomMasker(): void
    {
        $modifier = new Mask(
            fields: 'secret',
            masker: fn($value) => 'XXX-' . $value
        );

        $row = new Row(1, ['secret' => 'abc']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['secret' => 'XXX-abc'],
            $modified->all()
        );
    }

    public function testSkipsNullValues(): void
    {
        $modifier = new Mask('field');

        $row = new Row(1, ['field' => null]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // unchanged
        $this->assertSame(
            ['field' => null],
            $modified->all()
        );
    }

    public function testThrowsModifyExceptionOnMaskFailure(): void
    {
        $this->expectException(ModifyException::class);

        $badMasker = function () {
            throw new \RuntimeException('boom');
        };

        $modifier = new Mask('field', $badMasker);

        $row = new Row(1, ['field' => 'value']);

        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testThrowsExceptionWhenNoFieldsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Mask([]);
    }

    public function testThrowsExceptionWhenMaskerNotCallable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Mask('field', masker: 'not-a-callable');
    }
}