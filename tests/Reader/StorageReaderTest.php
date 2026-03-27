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

namespace Tobento\Service\ReadWrite\Test\Reader;

use PHPUnit\Framework\TestCase;
use Tobento\Service\ReadWrite\Reader\StorageReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\Storage\InMemoryStorage;
use Tobento\Service\Storage\StorageInterface;
use Tobento\Service\Storage\Tables\Tables;

class StorageReaderTest extends TestCase
{
    protected function createStorage(array $items = []): StorageInterface
    {
        $tables = new Tables();
        $tables->add('products', ['id', 'sku', 'price'], 'id');

        return new InMemoryStorage([
            'products' => $items,
        ], $tables);
    }

    public function testGetterMethods(): void
    {
        $storage = $this->createStorage();

        $query = function (StorageInterface $t): void {
            $t->where('price', '>', 10);
        };

        $reader = new StorageReader(
            storage: $storage,
            table: 'products',
            query: $query,
            previewRows: 5,
        );

        $this->assertSame($storage, $reader->storage());
        $this->assertSame('products', $reader->table());
        $this->assertSame($query, $reader->query());
        $this->assertSame(5, $reader->previewRows());
    }
    
    public function testColumns()
    {
        $storage = $this->createStorage([
            ['id' => 1, 'sku' => 'A', 'price' => 10],
        ]);

        $reader = new StorageReader(
            storage: $storage,
            table: 'products'
        );

        $this->assertSame(['id', 'sku', 'price'], $reader->columns());
    }

    public function testColumnsPreview()
    {
        $storage = $this->createStorage([
            ['id' => 1, 'sku' => 'A', 'price' => 10],
            ['id' => 2, 'sku' => 'B', 'price' => 20],
        ]);

        $reader = new StorageReader(
            storage: $storage,
            table: 'products',
            query: null,
            previewRows: 2
        );

        $preview = $reader->columnsPreview();

        $this->assertSame('A | B', $preview['sku']);
        $this->assertSame('10 | 20', $preview['price']);
    }
    
    public function testColumnsPreviewWithJson()
    {
        $storage = $this->createStorage([
            [
                'id' => 1,
                'sku' => ['code' => 'A', 'color' => 'red'],
                'price' => 10,
            ],
            [
                'id' => 2,
                'sku' => ['code' => 'B', 'color' => 'blue'],
                'price' => 20,
            ],
        ]);

        $reader = new StorageReader(
            storage: $storage,
            table: 'products',
            query: null,
            previewRows: 2
        );

        $preview = $reader->columnsPreview();

        // Scalars still work
        $this->assertSame('10 | 20', $preview['price']);

        // JSON arrays become JSON strings
        $this->assertSame(
            json_encode(['code' => 'A', 'color' => 'red']) . ' | ' .
            json_encode(['code' => 'B', 'color' => 'blue']),
            $preview['sku']
        );
    }

    public function testTotalRows()
    {
        $storage = $this->createStorage([
            ['id' => 1, 'sku' => 'A', 'price' => 10],
            ['id' => 2, 'sku' => 'B', 'price' => 20],
        ]);

        $reader = new StorageReader($storage, 'products');

        $this->assertSame(2, $reader->totalRows());
    }

    public function testReadReturnsRows()
    {
        $storage = $this->createStorage([
            ['id' => 1, 'sku' => 'A', 'price' => 10],
            ['id' => 2, 'sku' => 'B', 'price' => 20],
        ]);

        $reader = new StorageReader($storage, 'products');

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1, 'sku' => 'A', 'price' => 10], $rows[0]->all());
    }

    public function testReadAppliesQuery()
    {
        $storage = $this->createStorage([
            ['id' => 1, 'sku' => 'A', 'price' => 10],
            ['id' => 2, 'sku' => 'B', 'price' => 20],
        ]);

        $reader = new StorageReader(
            storage: $storage,
            table: 'products',
            query: function ($t) {
                $t->where('price', '>', 10);
            }
        );

        $rows = iterator_to_array($reader->read());

        $this->assertCount(1, $rows);
        $this->assertSame(20, $rows[0]->all()['price']);
    }

    public function testIsFinished()
    {
        $storage = $this->createStorage([
            ['id' => 1, 'sku' => 'A', 'price' => 10],
        ]);

        $reader = new StorageReader($storage, 'products');

        iterator_to_array($reader->read());

        $this->assertTrue($reader->isFinished());
    }
}