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
use Tobento\Service\ReadWrite\Reader\RepositoryReader;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Row\SkipRow;
use Tobento\Service\Repository\ReadRepositoryInterface;
use Tobento\Service\Repository\RepositoryReadException;

class FakeReadRepository implements ReadRepositoryInterface
{
    public function __construct(
        protected array $items
    ) {}

    public function findById(int|string $id): null|object
    {
        return null;
    }
    
    public function findByIds(int|string ...$ids): iterable
    {
        return [];
    }
    
    public function findOne(array $where = [], array $orderBy = []): ?object
    {
        $items = $this->applyWhere($this->items, $where);
        $items = $this->applyOrder($items, $orderBy);

        $first = reset($items);
        return $first ? (object)$first : null;
    }

    public function findAll(array $where = [], array $orderBy = [], null|int|array $limit = null): iterable
    {
        $items = $this->applyWhere($this->items, $where);
        $items = $this->applyOrder($items, $orderBy);

        if (is_int($limit)) {
            $items = array_slice($items, 0, $limit);
        } elseif (is_array($limit)) {
            [$l, $o] = $limit;
            $items = array_slice($items, $o, $l ?? null);
        }

        foreach ($items as $item) {
            yield is_object($item) ? $item : (object)$item;
        }
    }

    public function count(array $where = []): int
    {
        return count($this->applyWhere($this->items, $where));
    }
    
    public function findColumn(
        string $column,
        null|string $key = null,
        array $where = [],
        array $orderBy = [],
        null|int|array $limit = null
    ): array {
        return [];
    }

    private function applyWhere(array $items, array $where): array
    {
        foreach ($where as $key => $value) {
            $items = array_filter($items, fn($i) => ($i[$key] ?? null) === $value);
        }
        return array_values($items);
    }

    private function applyOrder(array $items, array $orderBy): array
    {
        foreach ($orderBy as $col => $dir) {
            usort($items, function ($a, $b) use ($col, $dir) {
                return $dir === 'DESC'
                    ? $b[$col] <=> $a[$col]
                    : $a[$col] <=> $b[$col];
            });
        }
        return $items;
    }
}

class RepositoryReaderTest extends TestCase
{
    public function testGetterMethods(): void
    {
        $repo = new FakeReadRepository([]);
        $reader = new RepositoryReader(repository: $repo, where: ['active' => true], orderBy: ['id' => 'ASC']);

        $this->assertSame($repo, $reader->repository());
        $this->assertSame(['active' => true], $reader->where());
        $this->assertSame(['id' => 'ASC'], $reader->orderBy());
        $this->assertSame(null, $reader->objectToArray());
        $this->assertSame(3, $reader->previewRows());
    }

    public function testColumns(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $reader = new RepositoryReader($repo);

        $this->assertSame(['id', 'name'], $reader->columns());
    }

    public function testColumnsPreviewAggregatesValues(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Alice'],
        ]);

        $reader = new RepositoryReader(repository: $repo, previewRows: 3);

        $this->assertSame(
            [
                'id'   => '1 | 2 | 3',
                'name' => 'Alice | Bob',
            ],
            $reader->columnsPreview()
        );
    }
    
    public function testColumnsPreviewAggregatesJsonValues(): void
    {
        $repo = new FakeReadRepository([
            [
                'id'   => 1,
                'name' => ['first' => 'Alice', 'role' => 'admin'],
            ],
            [
                'id'   => 2,
                'name' => ['first' => 'Bob', 'role' => 'user'],
            ],
            [
                'id'   => 3,
                'name' => ['first' => 'Alice', 'role' => 'admin'], // duplicate JSON
            ],
        ]);

        $reader = new RepositoryReader(repository: $repo, previewRows: 3);

        $preview = $reader->columnsPreview();

        $this->assertSame('1 | 2 | 3', $preview['id']);

        $this->assertSame(
            json_encode(['first' => 'Alice', 'role' => 'admin']) . ' | ' .
            json_encode(['first' => 'Bob',   'role' => 'user']),
            $preview['name']
        );
    }

    public function testReadsRows(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1],
            ['id' => 2],
        ]);

        $reader = new RepositoryReader($repo);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(2, $rows);
        $this->assertInstanceOf(Row::class, $rows[0]);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testOffsetReading(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ]);

        $reader = new RepositoryReader($repo);

        $rows = iterator_to_array($reader->read(offset: 1));

        $this->assertCount(2, $rows);
        $this->assertSame(['id' => 2], $rows[0]->all());
        $this->assertSame(['id' => 3], $rows[1]->all());
    }

    public function testLimitReading(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ]);

        $reader = new RepositoryReader($repo);

        $rows = iterator_to_array($reader->read(limit: 1));

        $this->assertCount(1, $rows);
        $this->assertSame(['id' => 1], $rows[0]->all());
    }

    public function testCurrentOffsetUpdates(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1],
            ['id' => 2],
        ]);

        $reader = new RepositoryReader($repo);

        iterator_to_array($reader->read());

        $this->assertSame(1, $reader->currentOffset());
    }

    public function testIsFinished(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1],
        ]);

        $reader = new RepositoryReader($repo);

        $this->assertFalse($reader->isFinished());

        iterator_to_array($reader->read());

        $this->assertTrue($reader->isFinished());
    }

    public function testTotalRows(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1],
            ['id' => 2],
        ]);

        $reader = new RepositoryReader($repo);

        $this->assertSame(2, $reader->totalRows());
    }

    public function testOrderByIsApplied(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 2],
            ['id' => 1],
        ]);

        $reader = new RepositoryReader(repository: $repo, orderBy: ['id' => 'ASC']);

        $rows = iterator_to_array($reader->read());

        $this->assertSame(['id' => 1], $rows[0]->all());
        $this->assertSame(['id' => 2], $rows[1]->all());
    }

    public function testWhereIsApplied(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1, 'active' => true],
            ['id' => 2, 'active' => false],
        ]);

        $reader = new RepositoryReader(repository: $repo, where: ['active' => true]);

        $rows = iterator_to_array($reader->read());

        $this->assertCount(1, $rows);
        $this->assertSame(['id' => 1, 'active' => true], $rows[0]->all());
    }
    
    public function testObjectToArrayCallableIsUsed(): void
    {
        $repo = new FakeReadRepository([
            ['id' => 1, 'name' => 'Alice'],
        ]);

        $callable = function (object $obj) {
            return ['custom' => $obj->id . '-' . $obj->name];
        };

        $reader = new RepositoryReader(repository: $repo, objectToArray: $callable);

        $rows = iterator_to_array($reader->read());

        $this->assertSame(['custom' => '1-Alice'], $rows[0]->all());
    }
    
    public function testObjectToArrayUsesToArrayMethod(): void
    {
        $repo = new FakeReadRepository([]);

        $entity = new class {
            public function toArray(): array
            {
                return ['id' => 99, 'name' => 'Test'];
            }
        };

        // Fake repository returning our object
        $repo = new class([$entity]) extends FakeReadRepository {
            public function findAll(array $where = [], array $orderBy = [], null|int|array $limit = null): iterable
            {
                yield $this->items[0];
            }
        };

        $reader = new RepositoryReader($repo);

        $rows = iterator_to_array($reader->read());

        $this->assertSame(['id' => 99, 'name' => 'Test'], $rows[0]->all());
    }
    
    public function testObjectToArrayFallsBackToGetObjectVars(): void
    {
        $entity = new class {
            public int $id = 5;
            public string $name = 'Fallback';
        };

        $repo = new class([$entity]) extends FakeReadRepository {
            public function findAll(array $where = [], array $orderBy = [], null|int|array $limit = null): iterable
            {
                yield $this->items[0];
            }
        };

        $reader = new RepositoryReader($repo);

        $rows = iterator_to_array($reader->read());

        $this->assertSame(['id' => 5, 'name' => 'Fallback'], $rows[0]->all());
    }
    
    public function testInvalidObjectToArrayCallableThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RepositoryReader(
            repository: new FakeReadRepository([]),
            objectToArray: 'not-a-callable'
        );
    }
}