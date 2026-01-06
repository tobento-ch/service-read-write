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
use Tobento\Service\ReadWrite\Modifier\Sanitize;
use Tobento\Service\ReadWrite\Reader\IterableReader;
use Tobento\Service\ReadWrite\Writer\NullWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\Sanitizer\Sanitizer;

class SanitizeTest extends TestCase
{
    private function reader(): IterableReader
    {
        return new IterableReader([]);
    }

    private function writer(): NullWriter
    {
        return new NullWriter();
    }

    public function testSanitizesData(): void
    {
        $sanitizer = new Sanitizer();

        $modifier = new Sanitize(
            rules: ['name' => 'trim'],
            sanitizer: $sanitizer
        );

        $row = new Row(1, ['name' => ' John ']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['name' => 'John'], $modified->all());
    }

    public function testStrictSanitationSanitizesMissingFields(): void
    {
        $sanitizer = new Sanitizer();

        $modifier = new Sanitize(
            rules: ['title' => 'cast:string:abc'],
            sanitizer: $sanitizer,
            strictSanitation: true
        );

        $row = new Row(1, ['name' => 'John']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        // strictSanitation = true → missing fields sanitized too
        $this->assertSame(
            ['name' => 'John', 'title' => 'abc'],
            $modified->all()
        );
    }

    public function testReturnSanitizedOnly(): void
    {
        $sanitizer = new Sanitizer();

        $modifier = new Sanitize(
            rules: ['name' => 'uppercase'],
            sanitizer: $sanitizer,
            strictSanitation: false,
            returnSanitizedOnly: true
        );

        $row = new Row(1, ['name' => 'john', 'age' => '42']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame(['name' => 'JOHN'], $modified->all());
    }

    public function testRowKeyIsPreserved(): void
    {
        $sanitizer = new Sanitizer();

        $modifier = new Sanitize(
            rules: ['x' => 'trim'],
            sanitizer: $sanitizer
        );

        $row = new Row('abc', ['x' => ' test ']);

        $modified = $modifier->modify($row, $this->reader(), $this->writer());

        $this->assertSame('abc', $modified->key());
    }

    public function testThrowsExceptionWhenNoRulesProvided(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $sanitizer = new Sanitizer();

        new Sanitize(
            rules: [],
            sanitizer: $sanitizer
        );
    }
}