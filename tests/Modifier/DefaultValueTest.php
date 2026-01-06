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
use Tobento\Service\ReadWrite\Modifier\DefaultValue;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;

class DefaultValueTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testSetsDefaultForMissingField(): void
    {
        $modifier = new DefaultValue([
            'status' => 'active',
        ]);

        $row = new Row(1, ['name' => 'John']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['name' => 'John', 'status' => 'active'],
            $modified->all()
        );
    }

    public function testSetsDefaultForNullField(): void
    {
        $modifier = new DefaultValue([
            'email' => 'unknown@example.com',
        ]);

        $row = new Row(1, ['email' => null]);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['email' => 'unknown@example.com'],
            $modified->all()
        );
    }

    public function testSetsDefaultForEmptyString(): void
    {
        $modifier = new DefaultValue([
            'country' => 'CH',
        ]);

        $row = new Row(1, ['country' => '']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['country' => 'CH'],
            $modified->all()
        );
    }

    public function testDoesNotOverrideExistingValue(): void
    {
        $modifier = new DefaultValue([
            'role' => 'guest',
        ]);

        $row = new Row(1, ['role' => 'admin']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            ['role' => 'admin'],
            $modified->all()
        );
    }

    public function testSetsNestedDefaultValue(): void
    {
        $modifier = new DefaultValue([
            'meta.info.version' => '1.0',
        ]);

        $row = new Row(1, ['name' => 'Test']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(
            [
                'name' => 'Test',
                'meta' => [
                    'info' => [
                        'version' => '1.0',
                    ],
                ],
            ],
            $modified->all()
        );
    }

    public function testThrowsExceptionWhenNoDefaultsProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new DefaultValue([]);
    }
}