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
use Tobento\Service\ReadWrite\Writer\StorageWriter;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Exception\WriteException;
use Tobento\Service\Storage\InMemoryStorage;
use Tobento\Service\Storage\StorageInterface;
use Tobento\Service\Storage\Tables\Tables;

class StorageWriterTest extends TestCase
{
    protected function createStorage(): InMemoryStorage
    {
        return new InMemoryStorage(
            [],
            new Tables()->add('users', ['id', 'title', 'status', 'created_at'], 'id'),
        )->table('users');
    }
    
    public function testGetterMethods(): void
    {
        $storage = $this->createStorage();
        
        $customWriter = function(RowInterface $row, StorageInterface $s) {};
        
        $writer = new StorageWriter(
            storage: $storage,
            writer: $customWriter,
        );
        
        $this->assertSame($storage, $writer->storage());
        $this->assertSame($customWriter, $writer->writer());
        $this->assertSame('id', $writer->idName());
    }
    
    public function testColumnsDefaultsToEmptyArray(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter($storage);
        $this->assertSame([], $writer->columns());
    }

    public function testColumnsPreviewDefaultsToEmptyArray(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter($storage);
        $this->assertSame([], $writer->columnsPreview());
    }

    public function testColumnsReturnsConfiguredValues(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter(
            storage: $storage,
            columns: ['title', 'status', 'created_at']
        );
        $this->assertSame(['title', 'status', 'created_at'], $writer->columns());
    }

    public function testColumnsPreviewReturnsConfiguredValues(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter(
            storage: $storage,
            columnsPreview: ['title' => 'Lorem', 'status' => 'Draft | Pending']
        );
        $this->assertSame(
            ['title' => 'Lorem', 'status' => 'Draft | Pending'],
            $writer->columnsPreview()
        );
    }

    public function testCustomWriterCallbackIsInvoked(): void
    {
        $storage = $this->createStorage();

        $called = false;
        $customWriter = function(RowInterface $row, StorageInterface $s) use (&$called) {
            $called = true;
            $s->insert($row->all()); // force insert
        };

        $writer = new StorageWriter(storage: $storage, writer: $customWriter);
        $writer->write(new Row(key: 1, attributes: ['id' => 99, 'title' => 'Force Create']));

        $this->assertTrue($called, 'Custom writer should be invoked');
        $this->assertNotEmpty($storage->get());
    }

    public function testWriteCallsInsertWhenNoIdPresent(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter($storage);

        $row = new Row(key: 1, attributes: ['title' => 'Hello']);
        $writer->write($row);

        $this->assertSame('Hello', $storage->get()[0]['title']);
    }

    public function testWriteCallsUpdateWhenIdPresent(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter($storage);

        // Insert initial row
        $storage->insert(['id' => 5, 'title' => 'Old']);

        // Update row via writer
        $row = new Row(key: 1, attributes: ['id' => 5, 'title' => 'Updated']);
        $writer->write($row);

        $this->assertSame('Updated', $storage->find(5)['title']);
    }

    public function testStartAndFinishDoNotThrow(): void
    {
        $storage = $this->createStorage();
        $writer = new StorageWriter($storage);

        $writer->start();
        $writer->finish();

        $this->assertTrue(true);
    }
}