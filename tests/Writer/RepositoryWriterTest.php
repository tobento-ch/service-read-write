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

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Writer\RepositoryWriter;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\Repository\WriteRepositoryInterface;
use Tobento\Service\Repository\RepositoryWriteException;

class RepositoryWriterTest extends TestCase
{
    public function testGetterMethods(): void
    {
        $repository = $this->createStub(WriteRepositoryInterface::class);
        $customWriter = function(RowInterface $row, WriteRepositoryInterface $repo) {};
        
        $writer = new RepositoryWriter(
            repository: $repository,
            writer: $customWriter,
        );
        
        $this->assertSame($repository, $writer->repository());
        $this->assertSame($customWriter, $writer->writer());
        $this->assertSame('id', $writer->idName());
    }
    
    public function testCreatesRowWhenNoIdPresent(): void
    {
        $repository = $this->createMock(WriteRepositoryInterface::class);

        $row = new Row(key: 1, attributes: ['title' => 'Hello']);

        $repository
            ->expects($this->once())
            ->method('create')
            ->with(['title' => 'Hello']);

        $writer = new RepositoryWriter($repository);
        $writer->write($row);
    }

    public function testUpdatesRowWhenIdPresent(): void
    {
        $repository = $this->createMock(WriteRepositoryInterface::class);

        $row = new Row(key: 1, attributes: ['id' => 5, 'title' => 'Updated']);

        $repository
            ->expects($this->once())
            ->method('updateById')
            ->with(5, ['id' => 5, 'title' => 'Updated']);

        $writer = new RepositoryWriter($repository);
        $writer->write($row);
    }

    public function testUsesCustomWriterCallback(): void
    {
        $repository = $this->createStub(WriteRepositoryInterface::class);

        $row = new Row(key: 1, attributes: ['id' => 10, 'title' => 'Custom']);

        $called = false;
        $customWriter = function(RowInterface $row, WriteRepositoryInterface $repo) use (&$called) {
            $called = true;
            $this->assertSame('Custom', $row->all()['title']);
        };

        $writer = new RepositoryWriter($repository, $customWriter);
        $writer->write($row);

        $this->assertTrue($called, 'Custom writer should be invoked');
    }

    public function testThrowsWriteExceptionOnRepositoryError(): void
    {
        $repository = $this->createStub(WriteRepositoryInterface::class);

        $row = new Row(key: 1, attributes: ['title' => 'Fail']);

        $repository
            ->method('create')
            ->willThrowException(new RepositoryWriteException('Repo error'));

        $writer = new RepositoryWriter($repository);

        $this->expectException(WriteException::class);
        $writer->write($row);
    }

    public function testThrowsWriterExceptionOnOtherError(): void
    {
        $repository = $this->createStub(WriteRepositoryInterface::class);

        $row = new Row(key: 1, attributes: ['title' => 'Oops']);

        $writer = new RepositoryWriter($repository, function() {
            throw new \RuntimeException('Unexpected');
        });

        $this->expectException(WriterException::class);
        $writer->write($row);
    }

    public function testStartAndFinishDoNotThrow(): void
    {
        $repository = $this->createStub(WriteRepositoryInterface::class);

        $writer = new RepositoryWriter($repository);

        // These are no-ops, but should not throw
        $writer->start();
        $writer->finish();

        $this->assertTrue(true);
    }
    
    public function testColumnsAndPreview(): void
    {
        $repository = $this->createStub(WriteRepositoryInterface::class);

        $writer = new RepositoryWriter(
            repository: $repository,
            columns: ['title', 'status', 'created_at'],
            columnsPreview: [
                'title'  => 'Lorem',
                'status' => 'Draft | Pending',
            ]
        );

        $this->assertSame(['title', 'status', 'created_at'], $writer->columns());
        $this->assertSame(
            ['title' => 'Lorem', 'status' => 'Draft | Pending'],
            $writer->columnsPreview()
        );
    }
}