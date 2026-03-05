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
use Tobento\Service\ReadWrite\Modifier\Validation;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\ReadWrite\Exception\ModifyErrorsException;
use Tobento\Service\ReadWrite\Exception\ModifyException;
use Tobento\Service\Validation\Validator;

class ValidationTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testValidationPasses(): void
    {
        $validator = new Validator();

        $modifier = new Validation(
            rules: ['name' => 'required'],
            validator: $validator
        );

        $row = new Row(1, ['name' => 'John']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // Should return the same row unchanged
        $this->assertSame($row, $modified);
    }

    public function testValidationFailsAndThrowsException(): void
    {
        $validator = new Validator();

        $modifier = new Validation(
            rules: ['name' => 'required'],
            validator: $validator,
            onFail: 'fail'
        );

        $row = new Row(1, ['name' => '']);

        $this->expectException(ModifyErrorsException::class);
        $this->expectExceptionMessage('Validation failed: [error] The name is required. (name)');

        $modifier->modify($row, $this->reader(), $this->writer());
    }

    public function testValidationFailsAndReturnsSkipRow(): void
    {
        $validator = new Validator();

        $modifier = new Validation(
            rules: ['email' => 'required|email'],
            validator: $validator,
            onFail: 'skip'
        );

        $row = new Row(1, ['email' => 'not-an-email']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertStringContainsString(
            'Validation failed: [error] The email must be a valid email address. (email)',
            $modified->reason(),
        );
    }

    public function testMultipleValidationErrorsAreGrouped(): void
    {
        $validator = new Validator();

        $modifier = new Validation(
            rules: [
                'password' => 'required|minLen:8',
            ],
            validator: $validator,
            onFail: 'skip'
        );

        $row = new Row(1, ['password' => 'abc']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);

        $reason = $modified->reason();

        // Only minLen fails because password is not empty
        $this->assertStringContainsString('8 chars', $reason);
        $this->assertStringNotContainsString('required', $reason);
    }

    public function testRowKeyAndAttributesArePreservedInSkipRow(): void
    {
        $validator = new Validator();

        $modifier = new Validation(
            rules: ['name' => 'required'],
            validator: $validator,
            onFail: 'skip'
        );

        $row = new Row('abc', ['name' => '']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertInstanceOf(SkipRow::class, $modified);
        $this->assertSame('abc', $modified->key());
        $this->assertSame(['name' => ''], $modified->all());
    }
}