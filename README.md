# Read and Write Service

The **Read and Write Service** provides a unified way to **consume** and **produce** structured data across different formats and sources.  
It abstracts common operations such as reading rows from streams or iterables, inspecting column metadata, and writing data back to files or streams in formats like CSV, JSON, or NDJSON.

## Key Capabilities

- **Readers**: Stream data from CSV, JSON arrays, NDJSON, or in-memory iterables.
- **Writers**: Export rows into CSV, JSON, NDJSON, or other supported formats.
- **Consistency**: All readers and writers implement common interfaces for predictable usage.
- **Flexibility**: Works with any PSR-7 `StreamInterface` implementation (Nyholm, Guzzle, Laminas, Slim, etc.).
- **Extensibility**: Supports modifiers to transform, filter, or enrich row attributes.

## Why Use It?

- Simplifies handling of heterogeneous data sources.
- Enables efficient streaming for large datasets without loading everything into memory.
- Provides a consistent developer experience across different formats.

## Table of Contents

- [Getting started](#getting-started)
    - [Requirements](#requirements)
- [Documentation](#documentation)
    - [Workflow](#workflow)
    - [Readers Comparison](#readers-comparison)
    - [Readers](#readers)
        - [CSV Stream Reader](#csv-stream-reader)
        - [Iterable Reader](#iterable-reader)
        - [JSON Stream Reader](#json-stream-reader)
        - [NDJSON Stream Reader](#ndjson-stream-reader)
        - [Repository Reader](#repository-reader)
        - [Storage Reader](#storage-reader)
    - [Writers Comparison](#writers-comparison)
    - [Writers](#writers)
        - [CSV Resource Writer](#csv-resource-writer)
        - [JSON Resource Writer](#json-resource-writer)
        - [NDJSON Resource Writer](#ndjson-resource-writer)
        - [Null Writer](#null-writer)
        - [PDF Resource Writer](#pdf-resource-writer)
        - [Repository Writer](#repository-writer)
        - [Storage Writer](#storage-writer)
        - [XML Resource Writer](#xml-resource-writer)
    - [Writer Resources](#writer-resources)
        - [File Storage Resource](#file-storage-resource)
        - [In Memory Resource](#in-memory-resource)
        - [Local File Resource](#local-file-resource)
    - [Modifiers](#modifiers)
        - [Apply Modifiers If Modifier](#apply-modifiers-if-modifier)
        - [Callable Modifier](#callable-modifier)
        - [Column Map Modifier](#column-map-modifier)
        - [Combine Fields Modifier](#combine-fields-modifier)
        - [Compute Modifier](#compute-modifier)
        - [Default Value Modifier](#default-value-modifier)
        - [Encrypt Modifier](#encrypt-modifier)
        - [Filter Fields Modifier](#filter-fields-modifier)
        - [Format Modifier](#format-modifier)
        - [Hash Modifier](#hash-modifier)
        - [Lookup Modifier](#lookup-modifier)
        - [Mask Modifier](#mask-modifier)
        - [Redact Modifier](#redact-modifier)
        - [Remove Fields Modifier](#remove-fields-modifier)
        - [Replace Modifier](#replace-modifier)
        - [Sanitize Modifier](#sanitize-modifier)
        - [Skip If Modifier](#skip-if-modifier)
        - [Split Modifier](#split-modifier)
        - [Trim Modifier](#trim-modifier)
        - [Unique Modifier](#unique-modifier)
        - [Validation Modifier](#validation-modifier)
    - [Processors](#processors)
        - [Default Processor](#default-processor)
        - [Time Budget Processor](#time-budget-processor)
    - [Results](#results)
        - [Result Handler](#result-handler)
        - [Result Object](#result-object)
    - [Events](#events)
    - [Learn More](#learn-more)
        - [Using Processors with Registries and Queues](#using-processors-with-registries-and-queues)
- [Credits](#credits)
___

# Getting started

Add the latest version of the read/write project running this command.

```
composer require tobento/service-read-write
```

## Requirements

- PHP 8.4 or greater

# Documentation

## Workflow

```php
use Tobento\Service\ReadWrite\Modifier;
use Tobento\Service\ReadWrite\Processor;
use Tobento\Service\ReadWrite\Reader;
use Tobento\Service\ReadWrite\Writer;

$reader = new Reader\CsvStream(
    // PSR-7 StreamInterface
    stream: new Psr17Factory()->createStreamFromFile('/data/input.csv'),
);

$writer = new Writer\CsvResource(
    resource: new Writer\Resource\LocalFile('/data/output.csv'),
);

$modifiers = new Modifier\Modifiers(
    new Modifier\ColumnMap(['title' => 'headline']),
);

$processor = new Processor\TimeBudgetProcessor(
    timeBudget: 10,
    modifiers: $modifiers
);

$result = $processor->process(reader: $reader, writer: $writer);

print_r($result->timeline());
```

## Readers Comparison

| Reader                   | Streaming | Supports Preview | Detects Columns | Nested Structures | Skips Invalid Rows | Typical Use Case |
|--------------------------|-----------|------------------|------------------|-------------------|---------------------|------------------|
| **CSV Stream Reader**    | Yes       | Yes              | Yes              | No                | Yes                 | Importing tabular CSV files of any size |
| **Iterable Reader**      | Yes       | Yes              | Yes (from first row) | Yes (if iterable contains arrays) | Yes | Reading from arrays, generators, API responses |
| **JSON Stream Reader**   | Yes       | Yes              | Yes              | Yes               | Yes (`SkipRow`)     | Large JSON arrays, API exports, structured data |
| **NDJSON Stream Reader** | Yes       | Yes              | Yes              | Yes (per line)    | Yes (`SkipRow`)     | Log streams, event streams, line-based JSON |
| **Repository Reader**    | No        | Yes              | Yes (from first entity) | Yes (entity to array) | Yes (`SkipRow`) | Domain repositories, entity-based data sources |
| **Storage Reader**       | No        | Yes              | Yes              | Yes (item to array) | Yes (`SkipRow`)     | Storage backends (in-memory, database, abstracted storage) |

**Notes**

- *Streaming* means the reader does not load the entire file into memory.
- *Preview* refers to `columnsPreview()` support.
- *Detects Columns* means the reader can infer column names from the first row.
- *Nested Structures* apply to JSON and NDJSON, not CSV.
- *Skip invalid rows* means the reader returns a `SkipRow` instead of throwing.

## Readers

### CSV Stream Reader

The `CsvStream` reader allows you to consume CSV data from any source that implements the PSR-7 ```StreamInterface```. It supports reading rows sequentially, applying offsets and limits.

**Features**

- Reads CSV data from PSR-7 streams (files, HTTP bodies, memory streams, etc.).
- Supports custom delimiters, enclosures, and escape characters.
- Automatically detects and uses the first row as column headers.
- Provides row objects implementing ```RowInterface``` for consistent access.
- Provides ```isFinished()``` to check if the end of the stream has been reached.
- Provides ```currentOffset()``` to get the byte offset for resuming later.

**Example**

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Tobento\Service\ReadWrite\Reader;

// Create a PSR-7 stream from a local file
$stream = new Psr17Factory()->createStreamFromFile('/data/input.csv');

// Initialize the CSV reader
$reader = new Reader\CsvStream(
    stream: $stream,
    delimiter: ',', // optional, defaults to ','
    enclosure: '"', // optional, defaults to '"'
    escape: '\\', // optional, defaults to '\'
);

// Read the first 5 rows starting at offset 0
foreach ($reader->read(offset: 0, limit: 5) as $row) {
    echo $row->key() . ': ' . json_encode($row->all()) . PHP_EOL;
}

if ($reader->isFinished()) {
    echo 'Reached end of CSV at offset: ' . $reader->currentOffset();
}
```

**Notes**

- You can use any PSR-7 implementation (Nyholm, Laminas, Guzzle, Slim, etc.) to create the stream.
- If the CSV contains a UTF-8 BOM, it will be stripped automatically.
- Invalid rows (wrong number of columns) are returned as ```Tobento\Service\ReadWrite\Row\SkipRow``` objects with a reason.
- Combine with [modifiers](#modifiers) to transform attributes if needed.

### Iterable Reader

The `IterableReader` wraps any PHP iterable (array, generator, `Traversable`) into a `ReaderInterface`.  
It is useful for testing, working with in-memory datasets, or adapting existing collections to the reader API.

**Features**
- Accepts arrays, generators, and custom iterables.
- No stream or PSR-7 dependency required.
- Produces raw attributes exactly as provided by the iterable rows.
- Provides `isFinished()` to check if all items have been consumed.
- Provides `currentOffset()` to track how many items have been read.

**Example**

```php
use Tobento\Service\ReadWrite\Reader\IterableReader;

$data = [
    ['title' => 'Hello'],
    ['title' => 'World'],
];

$reader = new IterableReader($data);

foreach ($reader->read() as $row) {
    echo $row->key() . ': ' . $row->get('title') . PHP_EOL;
}

echo 'Finished? ' . ($reader->isFinished() ? 'yes' : 'no') . PHP_EOL;
echo 'Current offset: ' . $reader->currentOffset() . PHP_EOL;
```

**Notes**

- Ideal for unit tests or scenarios where data is already available in memory.
- Rows must implement RowInterface (e.g. Row objects).
- Combine with [modifiers](#modifiers) to transform attributes if needed.

### JSON Stream Reader

The `JsonStream` reader consumes JSON arrays from any source that implements the PSR-7 `StreamInterface`.  
Each element in the array is parsed into a `RowInterface`, allowing sequential access with offset and limit support.

**Requirements**

To use the `JsonStream` reader you must install the [JsonMachine](https://github.com/halaxa/json-machine) library, which provides efficient streaming of large JSON arrays:

```
composer require halaxa/json-machine
```

**Features**

- Parses top-level JSON arrays into rows.
- Efficient for large JSON arrays thanks to JsonMachine's incremental parsing.
- Efficient for moderately sized JSON arrays via streaming.
- Produces raw attributes exactly as they appear in the source.
- Skips any non-array values, returning them as `SkipRow` objects with a reason.
- Provides `isFinished()` to detect end of stream.
- Provides `currentOffset()` for resuming from a specific byte position.

**Example**

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Tobento\Service\ReadWrite\Reader\JsonStream;

$stream = new Psr17Factory()->createStreamFromFile('/data/input.json');
$reader = new JsonStream($stream);

// Read the first 3 rows
foreach ($reader->read(offset: 0, limit: 3) as $row) {
    if ($row instanceof \Tobento\Service\ReadWrite\Row\SkipRow) {
        echo "Skipped row: " . $row->reason() . PHP_EOL;
    } else {
        print_r($row->all());
    }
}

echo 'Offset: ' . $reader->currentOffset() . PHP_EOL;

if ($reader->isFinished()) {
    echo 'Reached end of JSON stream';
}
```

**Notes**

- The JSON must be a valid array at the top level (e.g. ```[ {...}, {...} ]```).
- Any non-array values encountered will be skipped and returned as SkipRow objects.
- For very large or continuous datasets, consider using the [NDJSON Stream Reader](#ndjson-stream-reader) instead, as it processes line-by-line and is more memory-efficient.
- Any PSR‑7 implementation (Nyholm, Laminas, Guzzle, Slim, etc.) can be used to create the stream.
- Combine with [modifiers](#modifiers) to transform attributes if needed.

### NDJSON Stream Reader

The `NdJsonStream` reader consumes NDJSON (newline-delimited JSON) from any source that implements the PSR-7 `StreamInterface`.  
Each line is parsed into a `RowInterface`, allowing sequential access with offset and limit support.

**Features**

- Processes entries line by line, memory-efficient for large datasets.
- Produces raw attributes from each JSON object.
- Skips invalid JSON lines, returning them as `SkipRow` objects with a reason.
- Provides `isFinished()` to check if all lines have been consumed.
- Provides `currentOffset()` to track the byte position for resuming later.
- `totalRows()` always returns `null` since the total count cannot be determined without scanning the entire stream.

**Example**

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Tobento\Service\ReadWrite\Reader\NdJsonStream;

$stream = new Psr17Factory()->createStreamFromFile('/data/input.ndjson');
$reader = new NdJsonStream(stream: $stream);

// Read the first 2 rows starting at offset 0
foreach ($reader->read(offset: 0, limit: 2) as $row) {
    if ($row instanceof \Tobento\Service\ReadWrite\Row\SkipRow) {
        echo 'Skipped row: ' . $row->reason() . PHP_EOL;
    } else {
        echo json_encode($row->all()) . PHP_EOL;
    }
}

echo 'Current offset: ' . $reader->currentOffset() . PHP_EOL;

if ($reader->isFinished()) {
    echo 'Reached end of NDJSON stream';
}
```

**Notes**

- NDJSON does not require a top-level array; it streams one JSON object per line.
- Invalid or malformed JSON lines are skipped and returned as SkipRow objects.
- `JsonStream` uses [JsonMachine](https://github.com/halaxa/json-machine) to stream a top-level JSON array without loading the full file, while `NdJsonStream` reads line-by-line NDJSON. Both are streaming-friendly; choose based on source format (array vs. line-delimited) and whether the data is continuous.
- Prefer NDJSON for log-style or continuously appended data; prefer `JsonStream` for structured arrays.
- Any PSR-7 implementation (Nyholm, Laminas, Guzzle, Slim, etc.) can be used to create the stream.

### Repository Reader

The `RepositoryReader` reads entities from any repository that implements the `RepositoryInterface`.  
It applies optional `where` and `orderBy` constraints, converts each entity into a `RowInterface`, and supports sequential reading with offset and limit.

**Features**

- Works with any repository implementation that follows `RepositoryInterface`.
- Supports `where` and `orderBy` constraints passed directly into the constructor.
- Converts entities using arrays, `toArray()`, or a custom `objectToArray` callable.
- Produces `SkipRow` objects when an entity cannot be converted into a valid row.
- Provides `isFinished()` to check if all rows have been consumed.
- Tracks the current offset for sequential reading.
- `totalRows()` returns the number of entities after applying the constraints.
- `previewRows()` controls how many rows are sampled for `columnsPreview()`.

**Example**

```php
use Tobento\Service\ReadWrite\Reader\RepositoryReader;
use Tobento\Service\Repository\RepositoryInterface;

// Create reader with a query
$reader = new RepositoryReader(
    // RepositoryInterface instance used as the data source
    repository: $repository,
    
    // Filtering conditions applied before reading
    where: [],
    
    // Sorting rules
    orderBy: [],
    
    // Optional callable to convert objects into arrays
    objectToArray: function (object $entity): array {
        // Custom conversion logic for domain objects
        return [
            'id'   => $entity->id(),
            'name' => $entity->name(),
            'role' => $entity->role(),
        ];
    },
    
    // Number of rows sampled for columnsPreview()
    previewRows: 3,
);

// Read the first 2 rows
foreach ($reader->read(offset: 0, limit: 2) as $row) {
    if ($row instanceof \Tobento\Service\ReadWrite\Row\SkipRow) {
        echo 'Skipped row: ' . $row->reason() . PHP_EOL;
    } else {
        print_r($row->all());
    }
}

echo 'Current offset: ' . $reader->currentOffset() . PHP_EOL;

if ($reader->isFinished()) {
    echo 'Reached end of active users';
}
```

### Storage Reader

The `StorageReader` reads rows from any storage backend that implements the `StorageInterface`.  
It applies an optional query callable, converts each storage item into a `RowInterface`, and supports sequential reading with offset and limit.

**Features**

- Works with any storage implementation that follows `StorageInterface`.
- Supports a query callable for filtering, sorting, or limiting.
- Converts items using arrays, `ItemInterface`, or `toArray()`.
- Produces `SkipRow` objects when an item cannot be converted into a valid row.
- Provides `isFinished()` to check if all rows have been consumed.
- Tracks the current offset for sequential reading.
- `totalRows()` returns the number of rows after applying the query.
- `previewRows()` controls how many rows are sampled for `columnsPreview()`.

**Example**

```php
use Tobento\Service\ReadWrite\Reader\StorageReader;
use Tobento\Service\Storage\StorageInterface;

// Create reader with a query
$reader = new StorageReader(
    // StorageInterface instance used as the data source
    storage: $storage,
    
    // Table name to read from
    table: 'products',
    
    // Optional query callable applied before reading
    query: function (StorageInterface $t): void {
        $t->where('price', '>', 10)->order('price', 'asc');
    },
    
    // Number of rows sampled for columnsPreview()
    previewRows: 3,
);

// Read the first 2 rows
foreach ($reader->read(offset: 0, limit: 2) as $row) {
    if ($row instanceof \Tobento\Service\ReadWrite\Row\SkipRow) {
        echo 'Skipped row: ' . $row->reason() . PHP_EOL;
    } else {
        print_r($row->all());
    }
}

echo 'Current offset: ' . $reader->currentOffset() . PHP_EOL;

if ($reader->isFinished()) {
    echo 'Reached end of filtered products';
}
```

## Writers Comparison

| Writer                   | Streaming | Supports Headers | Supports Attributes | Nested Structures | Append Mode        | Typical Use Case |
|--------------------------|-----------|------------------|---------------------|-------------------|---------------------|------------------|
| **CSV Resource Writer**  | Yes       | Yes              | No                  | No                | Yes                 | Exporting tabular data, spreadsheets, reports |
| **JSON Resource Writer** | Yes       | N/A              | N/A                 | Yes               | Yes                 | APIs, structured exports, debugging |
| **NDJSON Resource Writer** | Yes     | N/A              | N/A                 | Yes (per line)    | Yes                 | Log streams, large datasets, incremental processing |
| **XML Resource Writer**  | Yes       | N/A              | Yes (`@attr`)       | Yes               | No (Finalize only)  | Feeds (RSS, Atom), Google Shopping, catalogs, sitemaps |
| **PDF Resource Writer**  | No        | Template-based   | Yes (via template)  | Yes               | No (Finalize only)  | PDF reports, tables, invoices, formatted exports |
| **Null Writer**          | Yes       | No               | No                  | No                | N/A                 | Discarding output, testing pipelines |
| **Repository Writer**    | No        | No               | No                  | No                | N/A                 | Writing rows into repositories or collections |
| **Storage Writer**       | No        | No               | No                  | No                | N/A                 | Writing rows into storage services (e.g., key-value stores) |

**Notes**

- *Streaming* means the writer does not load the entire dataset into memory.
- *Attributes* apply only to XML (`@id`, `@foo`, etc.).
- *Nested structures* apply to JSON, NDJSON, and XML.
- *Append mode* is supported only where the underlying format allows it (CSV, JSON, NDJSON).
- XML uses `Mode::Finalize` instead of append, because XML cannot be safely appended without rewriting closing tags.

## Writers

### CSV Resource Writer

The `CsvResource` writer exports rows into a CSV file or stream using a `ResourceInterface`.  
It supports writing headers, handling BOM, and controlling write modes (overwrite or append).

**Features**

- Implements `WriterInterface` and `ModeAwareInterface`.
- Supports configurable delimiter, enclosure, and escape characters.
- Optionally writes a UTF-8 BOM when starting fresh.
- Automatically writes column headers on the first row.
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.
- See [Writer Resources](#writer-resources) for details on available resource implementations.

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\CsvResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

// Create a file resource
$resource = new LocalFile('/data/output.csv');

// Initialize the CSV writer
$writer = new CsvResource(
    resource: $resource,
    delimiter: ',', // optional
    enclosure: '"', // optional
    escape: '\\',  // optional
    writeBom: true // optional
);

// Set mode (overwrite or append)
$writer->mode(Mode::Overwrite);

// Start writing
$writer->start();

// Write rows
$writer->write(new Row(key: 1, attributes: ['title' => 'Hello', 'status' => 'Draft']));
$writer->write(new Row(key: 2, attributes: ['title' => 'World', 'status' => 'Published']));

// Finish writing
$writer->finish();
```

**Notes**

- The header row is written automatically based on the first row's attributes.
- If the resource is already open, start() will throw a WriterException.
- Use `Mode::Overwrite` to start fresh (writes BOM) or `Mode::Append` to add to an existing file.

### JSON Resource Writer

The `JsonResource` writer exports rows into a JSON file or stream using a `ResourceInterface`.  
It writes rows as objects inside a top-level JSON array, supporting overwrite, append, and finalize modes.

**Features**

- Implements `WriterInterface` and `ModeAwareInterface`.
- Writes rows into a JSON array (`[ {...}, {...} ]`).
- Supports `Mode::Overwrite`, `Mode::Append`, and `Mode::Finalize`.
- Automatically handles commas between rows.
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.
- See [Writer Resources](#writer-resources) for details on available resource implementations.

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\JsonResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

// Create a file resource
$resource = new LocalFile('/data/output.json');

// Initialize the JSON writer
$writer = new JsonResource($resource);

// Set mode (overwrite or append)
$writer->mode(Mode::Overwrite);

// Start writing
$writer->start();

// Write rows
$writer->write(new Row(key: 1, attributes: ['title' => 'Hello', 'status' => 'Draft']));
$writer->write(new Row(key: 2, attributes: ['title' => 'World', 'status' => 'Published']));

// Finish writing
$writer->finish();
```

**Notes**

- Rows are written as JSON objects inside a top-level array.
- If the resource is already open, start() will throw a WriterException.
- Use `Mode::Overwrite` to start fresh (opens a new JSON array).
- Use `Mode::Append` to continue writing into an existing JSON array.
- Use `Mode::Finalize` to close the JSON array when finished.

### NDJSON Resource Writer

The `NdJsonResource` writer exports rows into an NDJSON file or stream using a `ResourceInterface`.  
It writes each row as a standalone JSON object on its own line, making it ideal for log‑style or streaming data.

**Features**

- Implements `WriterInterface`.
- Writes rows as individual JSON objects separated by newlines.
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.
- See [Writer Resources](#writer-resources) for details on available resource implementations.

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\NdJsonResource;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

// Create a file resource
$resource = new LocalFile('/data/output.ndjson');

// Initialize the NDJSON writer
$writer = new NdJsonResource($resource);

// Start writing
$writer->start();

// Write rows (each will be a separate line)
$writer->write(new Row(key: 1, attributes: ['title' => 'Hello', 'status' => 'Draft']));
$writer->write(new Row(key: 2, attributes: ['title' => 'World', 'status' => 'Published']));

// Finish writing
$writer->finish();
```

**Notes**

- Each row is written as a JSON object on its own line (NDJSON format).
- If the resource is already open, start() will throw a WriterException.
- NDJSON is well-suited for large or continuously appended datasets (e.g. logs).

### Null Writer

The `NullWriter` is a writer implementation that accepts rows but does **nothing** with them.
It is useful for:

- dry-run or simulation modes  
- validating readers and modifiers without producing output  
- debugging pipelines  
- performance testing without I/O overhead  
- scenarios where writing is optional or intentionally suppressed  

The writer fully participates in the processing lifecycle (`start`, `write`, `finish`) but performs no side effects.

**Example Use Cases**
- Testing an import job without writing any data  
- Running a processor to validate rows only  
- Using modifiers for transformation or sanitization without persisting results  
- Benchmarking reader performance  

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\NullWriter;

// Initialize the NullWriter
$writer = new NullWriter();

// (Optional) Set mode if your application uses writer modes
$writer->mode(Mode::Overwrite);

// Start writing (no-op)
$writer->start();

// Write rows (no-op)
$writer->write(new Row(
    key: 1,
    attributes: ['title' => 'Hello', 'status' => 'Draft']
));

$writer->write(new Row(
    key: 2,
    attributes: ['title' => 'World', 'status' => 'Published']
));

// Finish writing (no-op)
$writer->finish();
```

### PDF Resource Writer

The `PdfResource` writer exports rows into a PDF document using a template-based rendering system.  
It collects all written rows, merges them with optional template data, and generates a PDF using a `PdfGeneratorInterface`.

**Requirements**

Install the [tobento/service-pdf](https://github.com/tobento-ch/service-pdf) package, which provides the PDF generation interfaces and utilities used by this writer:

```
composer require tobento/service-pdf
```

**Features**

- Implements `WriterInterface`.
- Collects rows and passes them to a PDF template as `$rows`.
- Supports additional template data via the `$templateData` array (e.g. `title`, `description`, metadata).
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.
- Uses any `ResourceInterface` (local file, stream, memory, etc.).
- Works with any `PdfInterface` implementation.
- See [Writer Resources](#writer-resources) for details on available resource implementations.

**Example**

```php
use Tobento\Service\Pdf\Enums\Orientation;
use Tobento\Service\Pdf\Pdf;
use Tobento\Service\Pdf\PdfGenerator;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\PdfResource;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

// Create a file resource
$resource = new LocalFile('/data/report.pdf');

// Create a PDF generator: see pdf service.
$pdfGenerator = new PdfGenerator();

// Initialize the PDF writer with a template and template data
$writer = new PdfResource(
    resource: $resource,
    pdfGenerator: $pdfGenerator,
    templateName: 'pdf/export-table',
    templateData: [
        'title' => 'Product Report',
        'description' => 'Generated on ' . date('Y-m-d'),
    ],
    pdf: new Pdf()->orientation(Orientation::LANDSCAPE),
);

// Start writing
$writer->start();

// Write rows (these will be available as $rows in the template)
$writer->write(new Row(key: 1, attributes: ['name' => 'Apple', 'price' => 2.50]));
$writer->write(new Row(key: 2, attributes: ['name' => 'Banana', 'price' => 1.20]));

// Finish writing and generate the PDF
$writer->finish();
```

**Template Example**

A PDF template is a regular view file that receives the merged `$templateData` and the collected `$rows`.  
You may include CSS assets, partials, and any layout structure you need.

```php
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <title><?= $view->esc($title ?? 'Export') ?></title>

        <?= $view->assets()->render() ?>

        <?php
        // Assets can be included in every subview too.
        $view->asset('assets/css/basis.css');
        $view->asset('assets/css/app.css');
        ?>
    </head>
    <body class="content">
        <?= $view->render('inc/header') ?>

        <?php if (!empty($rows)) { ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach (array_keys($rows[0]->all()) as $col) { ?>
                            <th><?= $view->esc($col) ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) { ?>
                        <tr>
                            <?php foreach ($row->all() as $value) { ?>
                                <td><?= $view->esc((string)$value) ?></td>
                            <?php } ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } else { ?>
            <p>No data available.</p>
        <?php } ?>

        <?= $view->render('inc/footer') ?>
    </body>
</html>
```

### Repository Writer

The `RepositoryWriter` writes rows into a `WriteRepositoryInterface`.  
It supports automatic create/update operations or custom writer callbacks for flexible handling.

**Requirements**

Install the [tobento/service-repository](https://github.com/tobento-ch/service-repository) package, which provides the `WriteRepositoryInterface` used by this writer:

```
composer require tobento/service-repository
```

**Features**

- Implements `WriterInterface`.
- Uses a `WriteRepositoryInterface` for persistence.
- Supports a custom writer callback `(RowInterface, WriteRepositoryInterface): void`.
- Automatically updates rows if an identifier (`id` by default) is present.
- Creates new rows when no identifier is found.
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\RepositoryWriter;
use Tobento\Service\Repository\WriteRepositoryInterface;

// Example repository implementing WriteRepositoryInterface
$repository = new MyRepository();

// Initialize the repository writer
$writer = new RepositoryWriter(
    repository: $repository,
    
    // Optional: specify the identifier column (defaults to 'id')
    idName: 'id',
    
    // Optional: define the columns supported by the repository
    columns: ['title', 'status', 'created_at'],
    
    // Optional: provide representative preview values for each column
    columnsPreview: [
        'title'  => 'Lorem',
        'status' => 'Draft | Pending',
    ],
);

// Start writing
$writer->start();

// Write rows
$writer->write(new Row(key: 1, attributes: ['id' => 1, 'title' => 'Hello', 'status' => 'Draft']));
$writer->write(new Row(key: 2, attributes: ['title' => 'World', 'status' => 'Published']));

// Finish writing
$writer->finish();
```

**Custom Writer Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Writer\RepositoryWriter;
use Tobento\Service\Repository\WriteRepositoryInterface;

// Custom writer callback
$customWriter = function(RowInterface $row, WriteRepositoryInterface $repo): void {
    $attributes = $row->all();
    // Always create new entries, ignore id
    $repo->create($attributes);
};

$writer = new RepositoryWriter(
    repository: $repository,
    writer: $customWriter
);

$writer->start();
$writer->write(new Row(key: 1, attributes: ['id' => 99, 'title' => 'Force Create']));
$writer->finish();
```

**Notes**

- If a row contains the identifier (id by default), updateById is called.
- If no identifier is present, create is called.
- A custom writer callback overrides the default behavior.
- start() and finish() are no‑ops by default (repositories usually don't need explicit start/finish).
- Errors from the repository are wrapped in WriteException or WriterException for consistent handling.

### Storage Writer

The `StorageWriter` writes rows into a `StorageInterface`.  
It supports automatic create/update operations or custom writer callbacks for flexible handling.

**Requirements**

Install the [tobento/service-storage](https://github.com/tobento-ch/service-storage) package, which provides the `StorageInterface` used by this writer:

```
composer require tobento/service-storage
```

**Features**

- Implements `WriterInterface`.
- Uses a `StorageInterface` for persistence.
- Supports a custom writer callback `(RowInterface, StorageInterface): void`.
- Automatically updates rows if an identifier (`id` by default) is present.
- Creates new rows when no identifier is found.
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\StorageWriter;
use Tobento\Service\Storage\StorageInterface;

// Example storage implementing StorageInterface
$storage = new MyStorage();

// Initialize the storage writer
$writer = new StorageWriter(
    storage: $storage,
    
    // Optional: specify the identifier column (defaults to 'id')
    idName: 'id',
    
    // Optional: define the columns supported by the repository
    columns: ['title', 'status', 'created_at'],
    
    // Optional: provide representative preview values for each column
    columnsPreview: [
        'title'  => 'Lorem',
        'status' => 'Draft | Pending',
    ],
);

// Start writing
$writer->start();

// Write rows
$writer->write(new Row(key: 1, attributes: ['id' => 1, 'title' => 'Hello', 'status' => 'Draft']));
$writer->write(new Row(key: 2, attributes: ['title' => 'World', 'status' => 'Published']));

// Finish writing
$writer->finish();
```

**Custom Writer Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Writer\StorageWriter;
use Tobento\Service\Storage\StorageInterface;

// Custom writer callback
$customWriter = function(RowInterface $row, StorageInterface $storage): void {
    $attributes = $row->all();
    // Always create new entries, ignore id
    $storage->insert($attributes);
};

$writer = new StorageWriter(
    storage: $storage,
    writer: $customWriter
);

$writer->start();
$writer->write(new Row(key: 1, attributes: ['id' => 99, 'title' => 'Force Create']));
$writer->finish();
```

### XML Resource Writer

The `XmlResource` writer streams rows into an XML document using a `ResourceInterface`.  
It supports configurable root elements, row elements, optional wrapper elements, attributes, nested structures, and safe XML escaping.

**Features**

- Implements `WriterInterface` and `ModeAwareInterface`.
- Streams XML without loading the full document into memory.
- Writes an XML declaration (`<?xml version="1.0" encoding="UTF-8"?>`).
- Supports configurable:
  - root element  
  - row element  
  - optional wrapper element (e.g., `<channel>` in RSS)  
  - root attributes  
- Supports attributes via the `@attribute` key convention.
- Supports nested arrays to generate nested XML structures.
- Numeric array keys produce repeated XML elements.
- Automatically escapes values using `ENT_XML1`.
- Validates XML version, encoding, and element names.
- Provides `start()`, `write()`, and `finish()` lifecycle methods.
- Throws `WriterException` or `WriteException` on errors.
- See [Writer Resources](#writer-resources) for details on available resource implementations.

**Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\XmlResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

// Create a file resource
$resource = new LocalFile('/data/feed.xml');

// Initialize the XML writer
$writer = new XmlResource(
    resource: $resource,
    rootElement: 'products',
    rowElement: 'product',
    rowWrapper: null, // optional
    rootAttributes: ['xmlns:g' => 'http://base.google.com/ns/1.0'], // optional
    xmlVersion: '1.0', // optional
    encoding: 'UTF-8' // optional
);

// Set mode (overwrite or finalize)
$writer->mode(Mode::Overwrite);

// Start writing
$writer->start();

// Write rows
$writer->write(new Row(key: 1, attributes: [
    '@id' => '123',
    'title' => 'Red Shoes',
    'price' => '49.99',
    'tags' => [
        'tag' => ['fashion', 'shoes'], // repeated elements
    ],
]));

$writer->write(new Row(key: 2, attributes: [
    '@id' => '124',
    'title' => 'Blue Shirt',
    'price' => '29.99',
]));

// Finish writing
$writer->mode(Mode::Finalize);
$writer->finish();
```

**Atom Feed Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\XmlResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

$resource = new LocalFile('/data/atom.xml');

$writer = new XmlResource(
    resource: $resource,
    rootElement: 'feed',
    rowElement: 'entry',
    rowWrapper: null,
    rootAttributes: [
        'xmlns' => 'http://www.w3.org/2005/Atom',
    ]
);

$writer->mode(Mode::Overwrite);
$writer->start();

$writer->write(new Row(1, [
    'title' => 'Hello World',
    'id' => 'urn:uuid:123',
    'updated' => '2025-01-01T12:00:00Z',
    'link' => [
        '@href' => 'https://example.com/hello',
    ],
    'content' => 'This is an Atom entry.',
]));

$writer->mode(Mode::Finalize);
$writer->finish();
```

**Google Shopping Product Feed Example**

```php
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\Writer\XmlResource;
use Tobento\Service\ReadWrite\Writer\Mode;
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;

$resource = new LocalFile('/data/google-shopping.xml');

$writer = new XmlResource(
    resource: $resource,
    rootElement: 'rss',
    rowElement: 'item',
    rowWrapper: 'channel',
    rootAttributes: [
        'version' => '2.0',
        'xmlns:g' => 'http://base.google.com/ns/1.0',
    ]
);

$writer->mode(Mode::Overwrite);
$writer->start();

$writer->write(new Row(1, [
    'g:id' => 'SKU123',
    'g:title' => 'Red Shoes',
    'g:description' => 'Comfortable running shoes.',
    'g:link' => 'https://example.com/red-shoes',
    'g:image_link' => 'https://example.com/red-shoes.jpg',
    'g:price' => '49.99 USD',
    'g:availability' => 'in stock',
]));

$writer->mode(Mode::Finalize);
$writer->finish();
```

**Notes**

- Attributes are written using the `@attribute` key convention: `['@id' => '123']` into `<product id="123">`
- Nested arrays generate nested XML structures.
- Numeric keys inside arrays produce repeated elements: `['tag' => ['a', 'b']]` into `<tag>a</tag><tag>b</tag>`
- If the resource is already open, `start()` will throw a `WriterException`.
- Use `Mode::Overwrite` to create a fresh XML document.
- Use `Mode::Finalize` to close wrapper and root elements when finishing.

## Writer Resources

Writer Resources define **where and how data is physically written** when using a writer (CSV, JSON, NDJSON, etc.).  
They act as the destination layer, abstracting away the details of file handling, memory buffering, or storage backends.

**Why They Are Used**

- **Separation of concerns**: Writers focus on formatting rows (CSV, JSON, NDJSON), while resources handle persistence (local file, memory, storage system).
- **Flexibility**: You can swap out the resource depending on your needs without changing the writer logic.
  - Write directly to a local file (`LocalFile`).
  - Buffer in memory for testing or debugging (`InMemory`).
  - Commit to a storage backend like filesystem or cloud (`FileStorage`).
- **Consistency**: All resources implement `ResourceInterface`, so writers interact with them in a uniform way (`open()`, `write()`, `rewind()`, `close()`).
- **Error handling**: Resources encapsulate low-level errors (file not found, write failure, storage commit issues) and translate them into `WriterException`s.
- **Reusability**: The same writer can be reused with different resources, making the system modular and adaptable.

**Examples of Resources**

- **LocalFile**: Writes directly to a file on the local filesystem.
- **FileStorage**: Buffers data and commits it to a `StorageInterface` backend (e.g. filesystem, cloud).
- **InMemory**: Stores all written data in memory, useful for testing or capturing output without persistence.

By combining a writer (format) with a resource (destination), you can easily control both **how** data is structured and **where** it is stored.

### File Storage Resource

The `FileStorage` resource provides a bridge between writers and a storage backend implementing `StorageInterface`.  
It buffers written data into a temporary stream and commits the contents to the configured storage when closed.

**Requirements**

Install the [tobento/service-file-storage](https://github.com/tobento-ch/service-file-storage) package, which provides the `StorageInterface` used by the `FileStorage` resource:

```
composer require tobento/service-file-storage
```

**Features**

- Implements `ResourceInterface`.
- Buffers data in a temporary `php://temp` stream.
- Commits buffered data to the configured `StorageInterface` on `close()`.
- Provides lifecycle methods: `open()`, `write()`, `rewind()`, and `close()`.
- Throws `WriterException` on errors (open, write, rewind, commit).

**Example**

```php
use Tobento\Service\FileStorage\NullStorage;
use Tobento\Service\FileStorage\StorageInterface;
use Tobento\Service\ReadWrite\Exception\WriterException;
use Tobento\Service\ReadWrite\Writer\Resource\FileStorage;

// Create a storage (NullStorage for demo)
$storage = new NullStorage(name: 'null');

// Initialize the FileStorage resource
$resource = new FileStorage(storage: $storage, filename: 'output.csv');

// Open resource
$resource->open();

// Write data
$resource->write("id,title,status\n");
$resource->write("1,Hello,Draft\n");
$resource->write("2,World,Published\n");

// Rewind if needed
$resource->rewind();

// Close and commit to storage
$resource->close();
```

**Notes**

- Data is first written to a temporary stream (php://temp) before being committed to the storage backend.
- If the resource is already open, open() will throw a WriterException.
- close() commits the buffered contents to the configured StorageInterface and releases the handle.
- Use this resource with writers like CSV, JSON, or NDJSON to persist output into storage backends.

### In Memory Resource

The `InMemory` resource provides a simple, non-persistent buffer for writers.  
It stores all written data in memory, making it useful for testing, debugging, or scenarios where you don't want to write to disk or external storage.

**Features**

- Implements `ResourceInterface`.
- Buffers all written data in a string property.
- Provides lifecycle methods: `open()`, `write()`, `rewind()`, and `close()`.
- Exposes `getContent()` to retrieve the full written content.
- Throws `WriterException` on errors (open, write).

**Example**

```php
use Tobento\Service\ReadWrite\Writer\Resource\InMemory;
use Tobento\Service\ReadWrite\Exception\WriterException;

// Initialize the in-memory resource
$resource = new InMemory();

// Open resource
$resource->open();

// Write data
$resource->write("id,title,status\n");
$resource->write("1,Hello,Draft\n");
$resource->write("2,World,Published\n");

// Rewind if needed
$resource->rewind();

// Close resource
$resource->close();

// Retrieve buffered content
echo $resource->getContent();
```

**Notes**

- Data is stored entirely in memory and is lost once the resource is closed or discarded.
- If the resource is not open, write() will throw a WriterException.
- rewind() is effectively a no-op, since memory buffering does not require pointer management.
- Use this resource with writers like CSV, JSON, or NDJSON when you want to capture output without persisting it to disk or external storage.

### Local File Resource

The `LocalFile` resource provides direct file access for writers.  
It opens a file on the local filesystem, writes data to it, and manages the file handle lifecycle.

**Features**

- Implements `ResourceInterface`.
- Opens a file using `fopen()` with a configurable mode (default: `'w'`).
- Provides lifecycle methods: `open()`, `write()`, `rewind()`, and `close()`.
- Throws `WriterException` on errors (open, write, rewind, close).
- Exposes the underlying file handle via `getHandle()`.

**Example**

```php
use Tobento\Service\ReadWrite\Writer\Resource\LocalFile;
use Tobento\Service\ReadWrite\Exception\WriterException;

// Initialize the LocalFile resource
$resource = new LocalFile(filename: '/data/output.csv', mode: 'w');

// Open resource
$resource->open();

// Write data
$resource->write("id,title,status\n");
$resource->write("1,Hello,Draft\n");
$resource->write("2,World,Published\n");

// Rewind if needed
$resource->rewind();

// Close resource
$resource->close();
```

**Notes**

- The file is opened with the specified mode ('w', 'a', etc.), allowing overwrite or append behavior.
- If the file cannot be opened, open() will throw a WriterException.
- If the resource is not open, write() will throw a WriterException.
- close() ensures the file handle is released properly.
- Use this resource with writers like CSV, JSON, or NDJSON to persist output directly to the local filesystem.

## Modifiers

Modifiers allow you to transform, filter, or reshape rows as they move through the processing pipeline.  
They operate between the reader and writer, giving you full control over how data is normalized, renamed, enriched, or reduced before it is written.

Modifiers are applied by [**processors**](#processors), not by writers directly.  
This keeps the system flexible and composable: you can chain multiple modifiers, reorder them, or reuse them across different read/write workflows.

**Why Modifiers Are Useful**

- **Schema alignment**: Rename or remap columns so input and output formats match.
- **Data transformation**: Convert values, normalize formats, or enrich rows.
- **Filtering**: Remove unwanted columns or skip rows entirely.
- **Consistency**: All modifiers implement `ModifierInterface`, making them easy to plug into any pipeline.
- **Reusability**: Modifiers can be combined to build powerful, declarative data-processing flows.

Use modifiers whenever you need to adapt data between reading and writing without changing your reader or writer implementations.

### Apply Modifiers If Modifier

The `ApplyModifiersIf` modifier applies one or more modifiers only when a condition is met.  
If the condition does not match, the wrapped modifiers are skipped and the row
continues unchanged.

**Features**
- Wraps a single modifier or a chain of modifiers.
- Supports `callable`, `array`, `string`, or `bool` conditions.
- Does nothing when the condition is false.
- Dot-aware field paths supported.

#### Supported Conditions

**1. Boolean Condition**

```php
use Tobento\Service\ReadWrite\Modifier\ApplyModifiersIf;

// Always apply:
new ApplyModifiersIf(true, $modifier);

// Never apply:
new ApplyModifiersIf(false, $modifier);
```

**2. String Condition**

Apply only if the field exists and is not empty:

```php
use Tobento\Service\ReadWrite\Modifier\ApplyModifiersIf;

new ApplyModifiersIf('country', $modifier);
```

**3. Array Condition**

```php
use Tobento\Service\ReadWrite\Modifier\ApplyModifiersIf;

// Apply if field is missing:
new ApplyModifiersIf(
    ['field' => 'postal_code', 'missing' => true],
    $modifier
);

// Apply if field equals a specific value:
new ApplyModifiersIf(
    ['field' => 'country', 'equals' => 'CH'],
    $modifier
);
```

**4. Callable Condition**

Apply only for Swiss addresses:

```php
use Tobento\Service\ReadWrite\Modifier\ApplyModifiersIf;
use Tobento\Service\ReadWrite\RowInterface;

new ApplyModifiersIf(
    fn(RowInterface $row): bool => $row->get('country') === 'CH',
    $modifier
);
```

### Callable Modifier

The `CallableModifier` allows you to apply custom transformation logic to a row using any user-defined callable.  
It provides maximum flexibility when built-in modifiers are not sufficient or when you want to encapsulate small, one-off transformations.

**Features**

- Implements `ModifierInterface`.
- Accepts any valid PHP callable.
- The callable receives the current `RowInterface`, `ReaderInterface`, and `WriterInterface`.
- Returns a modified `RowInterface`.
- Throws `ModifyException` if the callable fails.
- Applied by processors within the read/write pipeline.
- See [Modifiers](#modifiers) for more transformation utilities.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\CallableModifier;
use Tobento\Service\ReadWrite\ReaderInterface;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\WriterInterface;

// Define a custom modifier
$modifier = new CallableModifier(function(RowInterface $row, ReaderInterface $reader, WriterInterface $writer): RowInterface {
    $attributes = $row->all();
    $attributes['title'] = strtoupper($attributes['title'] ?? '');
    
    return new Row(
        key: $row->key(),
        attributes: $attributes
    );
});

// Normally applied by processors, but can be invoked manually:
$modified = $modifier->modify($row, $reader, $writer);
```

### Column Map Modifier

The `ColumnMap` modifier transforms a row's attributes by remapping column names according to a user-defined mapping.  
It is useful when the input and output schemas differ, or when you need to rename columns during read/write operations.

**Features**

- Implements `ModifierInterface`.
- Remaps column names using a simple `['from' => 'to']` array.
- Only mapped columns that exist in the row are included in the output.
- Throws `ModifyException` if the mapping is empty.
- Works with any reader and writer combination.
- See [Modifiers](#modifiers) for more transformation utilities.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\ColumnMap;
use Tobento\Service\ReadWrite\Row\Row;

// Define a column mapping
$modifier = new ColumnMap([
    'title' => 'name',
    'status' => 'state',
]);

// Example row
$row = new Row(
    key: 1,
    attributes: [
        'title' => 'Hello',
        'status' => 'Draft',
        'ignored' => 'Not mapped',
    ]
);

// Normally applied by processors, but can be invoked manually:
$modified = $modifier->modify($row, $reader, $writer);

// Resulting row attributes:
// ['name' => 'Hello', 'state' => 'Draft']
```

**Notes**

- Only columns defined in the mapping are included in the resulting row.
- If the mapping array is empty, a ModifyException is thrown.
- Useful for renaming columns when exporting or normalizing inconsistent input data.
- Combine with other modifiers (e.g., filters or transformers) for more complex pipelines.

### Combine Fields Modifier

The `CombineFields` modifier merges multiple attributes into a single target attribute.  
It is useful for creating derived fields such as `full_name` from `first_name` and `last_name`.

**Features**
- Implements `ModifierInterface`.
- Accepts an array of source fields (dot-aware paths).
- Combines string and numeric values into a target field using a configurable separator.
- Skips non-stringable values (booleans, arrays, objects).
- Optionally removes source fields after combining.
- Throws `ModifyException` if no fields or target are defined.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\CombineFields;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new CombineFields(
    fields: ['first_name', 'last_name', 'age'],
    into: 'summary',
    separator: ' ',
    removeSourceFields: true
);

$row = new Row(1, [
    'first_name' => 'John',
    'last_name' => 'Doe',
    'age' => 42,
]);

$modified = $modifier->modify($row, $reader, $writer);

// Result:
// ['summary' => 'John Doe 42']
```

### Compute Modifier

The `Compute` modifier computes a field value using a user-defined callback.
It is useful for creating derived fields, performing custom calculations, or
generating values based on the entire row.

**Features**

- Implements `ModifierInterface`.
- Writes the computed value to a single target field.
- Callback receives the full `RowInterface` instance.
- Dot-notation supported for the output field.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Compute;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Compute(
    field: 'full_name',
    computeFn: function (RowInterface $row) {
        return trim(
            $row->get('first_name', '') . ' ' .
            $row->get('last_name', '')
        );
    }
);

$row = new Row(
    key: 1,
    attributes: [
        'first_name' => 'John',
        'last_name'  => 'Doe',
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//     'first_name' => 'John',
//     'last_name'  => 'Doe',
//     'full_name'  => 'John Doe',
// ]
```

### Default Value Modifier

The `DefaultValue` modifier ensures that missing or empty attributes are populated with predefined default values.  
It is useful for filling in required fields during imports or normalizing incomplete input data.

**Features**

- Implements `ModifierInterface`.
- Accepts a `['attribute' => defaultValue]` array.
- Dot‑aware paths supported (e.g. `user.name`, `tags.0`).
- Applies defaults when attributes are:
  - missing
  - `null`
  - empty string (`''`)
- Throws `ModifyException` if no defaults are defined.
- Works with any reader and writer combination.
- See [Modifiers](#modifiers) for more cleaning and transformation utilities.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\DefaultValue;
use Tobento\Service\ReadWrite\Row\Row;

// Define default values
$modifier = new DefaultValue([
    'status' => 'Draft',
    'created_at' => date('Y-m-d'),
]);

// Example row with missing and empty attributes
$row = new Row(
    key: 1,
    attributes: [
        'title' => 'Hello',
        'status' => '',
    ]
);

// Normally applied by processors, but can be invoked manually:
$modified = $modifier->modify($row, $reader, $writer);

// Resulting row attributes:
// [
//   'title' => 'Hello',
//   'status' => 'Draft', // replaced empty string
//   'created_at' => '2025-12-24',
// ]
```

### Encrypt Modifier

The `Encrypt` modifier encrypts one or more fields using an application-defined
encryption service based on  
[tobento/service-encryption](https://github.com/tobento-ch/service-encryption).  
It is useful for protecting sensitive data such as API keys, tokens, personal
information, or any value that must be stored securely but still be decryptable later.

**Requirements**

To use the `Encrypt` modifier, you must install the  
[tobento/service-encryption](https://github.com/tobento-ch/service-encryption) package:

```
composer require tobento/service-encryption
```

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-aware paths supported (e.g. `user.ssn`, `credentials.token`).
- Uses an `EncrypterInterface` from `service-encryption`.
- Skips `null` values automatically.
- Wraps encryption errors in a `ModifyException`.
- Works with any reader and writer combination.

**Example**

```php
use Tobento\Service\Encryption\EncrypterInterface;
use Tobento\Service\ReadWrite\Modifier\Encrypt;
use Tobento\Service\ReadWrite\Row\Row;

// $encrypter is an instance of EncrypterInterface
$modifier = new Encrypt(
    fields: ['api.key', 'api.secret'],
    encrypter: $encrypter,
);

$row = new Row(
    key: 1,
    attributes: [
        'api' => [
            'key' => 'my-api-key',
            'secret' => 'super-secret',
        ],
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//   'api' => [
//     'key' => 'ENCRYPTED',
//     'secret' => 'ENCRYPTED',
//   ],
// ]
```

**Notes**

- Only encryption is performed - decryption must be handled elsewhere using the same `EncrypterInterface`.
- If encryption fails, a `ModifyException` is thrown with the original row attached.
- Dot-notation allows encrypting nested values inside arrays or objects.
- The modifier does not enforce any specific encryption algorithm - it relies entirely on the configured `EncrypterInterface`.

### Filter Fields Modifier

The `FilterFields` modifier keeps only the specified fields and removes all others.  
It is useful for privacy filtering, export shaping, or limiting output to a
defined subset of fields.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-notation supported (e.g. `user.email`, `meta.tags.0`).
- Removes all fields not listed.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\FilterFields;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new FilterFields(['id', 'email']);

$row = new Row(
    key: 1,
    attributes: [
        'id' => 1,
        'email' => 'a@b.com',
        'password' => 'secret',
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//     'id' => 1,
//     'email' => 'a@b.com',
// ]
```

### Format Modifier

The `Format` modifier applies a user-defined formatting callback to a specific
field. It is useful for normalizing values, converting types, trimming,
cleaning, or applying any custom transformation to a single attribute.

**Features**

- Implements `ModifierInterface`.
- Formats a single field using a callback.
- Callback receives the current field value and the full `RowInterface`.
- Dot-notation supported for the target field.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Format;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Format(
    field: 'email',
    formatter: function ($value, RowInterface $row) {
        return strtolower(trim((string)$value));
    }
);

$row = new Row(
    key: 1,
    attributes: [
        'email' => '  JOHN.DOE@EXAMPLE.COM  ',
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//     'email' => 'john.doe@example.com',
// ]
```

### Hash Modifier

The `Hash` modifier hashes one or more fields using a user-defined hashing callable.  
It is useful for securely transforming sensitive values such as passwords, tokens,  
API keys, or other secrets before they are stored or processed further.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-aware paths supported (e.g. `user.password`, `credentials.token`).
- Uses a user-provided hashing callable:
  - `fn(mixed $value): string`
- Skips `null` values automatically.
- Wraps hashing errors in a `ModifyException`.
- Works with any reader and writer combination.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Hash;
use Tobento\Service\ReadWrite\Row\Row;

// Hash a password using PHP's password_hash
$modifier = new Hash(
    fields: 'password',
    hasher: fn($value) => password_hash($value, PASSWORD_DEFAULT),
);

$row = new Row(
    key: 1,
    attributes: ['password' => 'secret123']
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//   'password' => '$2y$10$....' // hashed value
// ]
```

**Notes**

- Empty strings are treated as values and will be hashed unless handled externally.
- If hashing fails, a `ModifyException` is thrown with the original row attached.
- The modifier does not enforce any specific hashing algorithm - you may use `password_hash`, `hash()`, a framework hasher, or any custom callable.
- Dot-notation allows hashing nested values inside arrays or objects.

### Lookup Modifier

The `LookupModifier` maps a source field's value to another value using either a lookup array or a callable resolver.  
It is useful for normalizing human-readable labels into IDs or codes (e.g. category name to category ID).

**Features**
- Implements `ModifierInterface`.
- Maps a source field to a target field using:
  - A static lookup array (string, int, or float keys supported).
  - A callable resolver (e.g. repository, service).
- Dot-aware paths supported.
- Optionally removes the source field after mapping.
- Throws `ModifyException` if source/target fields are missing.

**Example (array lookup)**

```php
use Tobento\Service\ReadWrite\Modifier\Lookup;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Lookup(
    field: 'category',
    into: 'category_id',
    lookup: [
        'Books' => 1,
        42 => 'Answer',
        3.14 => 'Pi',
    ],
    removeSourceField: true
);

$row = new Row(1, ['category' => 42]);

$modified = $modifier->modify($row, $reader, $writer);

// Result:
// ['category_id' => 'Answer']
```

**Example (callable lookup)**

```php
$modifier = new Lookup(
    field: 'category',
    into: 'category_id',
    lookup: fn($value) => CategoryRepository::findIdByName($value),
    removeSourceField: true
);

$row = new Row(1, ['category' => 'Books']);
$modified = $modifier->modify($row, $reader, $writer);

// Result:
// ['category_id' => 1]
```

### Mask Modifier

The `Mask` modifier masks one or more fields to hide sensitive information while
preserving enough structure for display or logging.  
It is useful for partially hiding emails, phone numbers, tokens, names, or any
other sensitive value that should not appear in plain text.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-aware paths supported (e.g. `user.email`, `customer.phone`).
- Supports a custom masking callable:
  - `fn(mixed $value): string`
- Provides sensible default masking rules:
  - Emails: `john@example.com` to `j***@example.com`
  - Strings: `Jonathan` to `J*****n`
  - Numbers: `123456789` to `1*******9`
- Skips `null` values automatically.
- Wraps masking errors in a `ModifyException`.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Mask;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Mask(
    fields: ['user.email', 'user.phone']
);

$row = new Row(
    key: 1,
    attributes: [
        'user' => [
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ],
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//   'user' => [
//     'email' => 'j***@example.com',
//     'phone' => '1********0',
//   ],
// ]
```

**Example: Custom masking**

```php
use Tobento\Service\ReadWrite\Modifier\Mask;

// Custom masking: always replace the value with six asterisks
$modifier = new Mask(
    fields: 'phone',
    masker: fn(mixed $value): string => '******',
);
```

**Notes**
- Default masking rules apply only when no custom callable is provided.
- Masking is intended for display/logging - not for security or encryption.
- Dot-notation allows masking nested values inside arrays or objects.
- If masking fails, a `ModifyException` is thrown with the original row attached.

### Redact Modifier

The `Redact` modifier removes sensitive information by replacing one or more
fields with a fixed value.  
It is useful for eliminating confidential data such as passwords, tokens,
personal identifiers, or any value that must not appear in logs, exports, or
downstream systems.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-aware paths supported (e.g. `user.password`, `customer.card.number`).
- Replaces values with a configurable replacement (default: `null`).
- Skips fields that do not exist.
- Wraps errors in a `ModifyException`.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Redact;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Redact(
    fields: ['user.password', 'user.token']
);

$row = new Row(
    key: 1,
    attributes: [
        'user' => [
            'password' => 'secret123',
            'token' => 'abc123xyz',
        ],
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//   'user' => [
//     'password' => null,
//     'token' => null,
//   ],
// ]
```

**Example: Custom replacement**

```php
use Tobento\Service\ReadWrite\Modifier\Redact;

// Replace values with a fixed string instead of null
$modifier = new Redact(
    fields: 'api.key',
    replacement: 'REDACTED',
);
```

**Notes**

- Redaction is intended for privacy and security - it removes data entirely.
- If you need partial hiding instead of full removal, use the Mask modifier.
- Dot-notation allows redacting nested values inside arrays or objects.
- If redaction fails, a `ModifyException` is thrown with the original row attached.

### Remove Fields Modifier

The `RemoveFields` modifier removes the specified fields from a row.  
It is useful for stripping sensitive data, removing debug or internal fields,
or cleaning up unwanted input before further processing.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-notation supported (e.g. `user.password`, `meta.debug.flag`).
- Removes only the listed fields.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\RemoveFields;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new RemoveFields(['password', 'debug']);

$row = new Row(
    key: 1,
    attributes: [
        'id' => 1,
        'email' => 'a@b.com',
        'password' => 'secret',
        'debug' => 'x',
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//     'id' => 1,
//     'email' => 'a@b.com',
// ]
```

### Replace Modifier

The `Replace` modifier replaces values or substrings in one or more fields.  
It is useful for cleaning up imported data, normalizing inconsistent values,
or converting placeholder values such as `"N/A"` or `"none"` into meaningful
representations.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-aware paths supported (e.g. `user.status`, `meta.note`).
- Supports exact match replacement (`strict = true`).
- Supports substring replacement (`strict = false`).
- Supports replacing `null` values when `forceNullReplacement` is enabled.
- Wraps errors in a `ModifyException`.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Replace;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Replace(
    fields: ['status'],
    replacements: [
        'N/A' => null,
        'yes' => true,
        'no'  => false,
    ],
    strict: true,
);

$row = new Row(
    key: 1,
    attributes: [
        'status' => 'yes',
    ]
);

$modified = $modifier->modify($row, $reader, $writer);

// Resulting attributes:
// [
//     'status' => true,
// ]
```

**Example: Substring replacement**

```php
use Tobento\Service\ReadWrite\Modifier\Replace;

$modifier = new Replace(
    fields: 'comment',
    replacements: [
        'foo' => 'bar',
    ],
    strict: false,
);
```

**Example: Replace null values**

```php
use Tobento\Service\ReadWrite\Modifier\Replace;

$modifier = new Replace(
    fields: 'email',
    replacements: [
        null => 'unknown',
    ],
    strict: true,
    forceNullReplacement: true,
);
```

**Notes**

- In strict mode, only exact matches are replaced.
- In substring mode, all occurrences of the search string are replaced.
- Non-string values are left unchanged unless replacing null.
- Dot-notation allows replacing values inside nested arrays or objects.
    
### Sanitize Modifier

The `Sanitize` modifier cleans row attributes using the [tobento/service-sanitizer](https://github.com/tobento-ch/service-sanitizer) package.  
It is useful for removing unwanted HTML, normalizing input, and ensuring safe, consistent values before validation or storage.

**Requirements**

To use the `Sanitize` modifier, you must install the [tobento/service-sanitizer](https://github.com/tobento-ch/service-sanitizer) package:

```
composer require tobento/service-sanitizer
```

**Features**

- Implements `ModifierInterface`.
- Accepts a `['attribute' => rules]` array of sanitation rules.
- Dot‑aware paths supported (e.g. `user.name`, `tags.0`).
- Uses `SanitizerInterface` to apply rules such as `strip_tags`, `trim`, `email`, etc.
- `strictSanitation`: if true, sanitizes missing data too.
- `returnSanitizedOnly`: if true, returns only sanitized attributes, otherwise all.
- Throws `ModifyException` if no rules are defined.
- Works with any reader and writer combination.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Sanitize;
use Tobento\Service\Sanitizer\Sanitizer;
use Tobento\Service\ReadWrite\Row\Row;

$sanitizer = new Sanitizer();

// Define sanitation rules
$modifier = new Sanitize([
    'title' => 'strip_tags|trim',
    'published_at' => 'date:Y-m-d:d.m.Y',
], $sanitizer);

$row = new Row(
    key: 1,
    attributes: [
        'title' => '<h1>Hello</h1>   ',
        'published_at' => '24.12.2025',
    ]
);

// Normally applied by processors, but can be invoked manually:
$modified = $modifier->modify($row, $reader, $writer);

// Resulting row attributes:
// [
//   'title' => 'Hello',
//   'published_at' => '2025-12-24', // normalized
// ]
```

### Skip If Modifier

The `SkipIf` modifier skips a row when a condition is met and returns a `SkipRow`  
implementing `SkippableInterface`.  
It is useful for filtering out invalid, incomplete, or unwanted rows before they  
reach later modifiers or writers.

**Features**
- Implements `ModifierInterface`.
- Supports `callable`, `array`, `string`, or `bool` conditions.
- Returns a `SkipRow` with a human-readable skip reason.
- Stops further modifiers from executing.
- Dot-aware field paths supported.

#### Supported Conditions

**1. Boolean Condition**

```php
use Tobento\Service\ReadWrite\Modifier\SkipIf;

// Skip all rows:
new SkipIf(true, 'Skipping all rows');

// Skip no rows:
new SkipIf(false);
```

**2. String Condition**

Skip if the field is missing or empty:

```php
use Tobento\Service\ReadWrite\Modifier\SkipIf;

new SkipIf('email', 'Email is required');
```

**3. Array Condition**

```php
use Tobento\Service\ReadWrite\Modifier\SkipIf;

// Skip if field is missing or empty:
new SkipIf(
    ['field' => 'username', 'missing' => true],
    'Username is missing'
);

// Skip if field equals a specific value:
new SkipIf(
    ['field' => 'status', 'equals' => 'N/A'],
    'Status is N/A'
);

// Skip if field equals zero:
new SkipIf(
    ['field' => 'age', 'equals' => 0],
    'Age cannot be zero'
);
```

**4. Callable Condition**

Skip if age is under 18:

```php
use Tobento\Service\ReadWrite\Modifier\SkipIf;
use Tobento\Service\ReadWrite\RowInterface;

new SkipIf(
    fn(RowInterface $row): bool => $row->get('age') < 18,
    'User is under 18'
);
```

### Split Modifier

The `Split` modifier splits a single string field into multiple fields using a separator.  
It is useful for breaking composite values into structured parts  
(e.g. `"John Doe"` into `first_name`, `last_name`).

**Features**
- Implements `ModifierInterface`.
- Splits one source field into multiple target fields.
- Uses a configurable separator (default: space).
- Dot-aware paths supported.
- Optionally removes the source field after splitting.
- Throws `ModifyException` if source or target fields are missing.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Split;
use Tobento\Service\ReadWrite\Row\Row;

$modifier = new Split(
    field: 'full_name',
    into: ['first_name', 'last_name'],
    separator: ' ',
    removeSourceField: true
);

$row = new Row(1, ['full_name' => 'John Doe']);
$modified = $modifier->modify($row, $reader, $writer);

// Result:
// ['first_name' => 'John', 'last_name' => 'Doe']
```

**Notes**
- Extra parts beyond the number of target fields are ignored.
- If there are fewer parts than target fields, missing parts are skipped.
    
### Trim Modifier

The `Trim` modifier cleans a row's attributes by removing leading and trailing whitespace (or other specified characters) from defined fields.  
It is useful for normalizing text input, ensuring consistent values before validation, transformation, or storage.

**Features**

- Implements `ModifierInterface`.
- Trims only the attributes explicitly defined in the constructor.
- Supports trimming custom characters via the optional `$chars` parameter.
- Supports nested and indexed paths (e.g. `user.name`, `tags.0`).
- Skips attributes that do not exist in the row.
- Throws `ModifyException` if no attributes are defined.
- Works with any reader and writer combination.
- See [Modifiers](#modifiers) for more cleaning utilities.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Trim;
use Tobento\Service\ReadWrite\Row\Row;

// Define which attributes to trim
$modifier = new Trim(['title', 'status']);

// Example row
$row = new Row(
    key: 1,
    attributes: [
        'title' => '  Hello World  ',
        'status' => ' Draft ',
        'description' => '   untouched   ',
    ]
);

// Normally applied by processors, but can be invoked manually:
$modified = $modifier->modify($row, $reader, $writer);

// Resulting row attributes:
// [
//   'title' => 'Hello World',
//   'status' => 'Draft',
//   'description' => '   untouched   ', // unchanged
// ]
```

### Unique Modifier

The `Unique` modifier ensures that one or more fields contain unique values across the import.  
It is useful for preventing duplicate emails, SKUs, usernames, or other
identifiers that must not repeat.

**Features**

- Implements `ModifierInterface`.
- Accepts a single field or an array of fields.
- Dot-aware paths supported (e.g. `user.email`, `items.0.sku`).
- Supports an optional lookup callable for external uniqueness checks  
  (e.g. database, repository, API).
- Falls back to in-memory uniqueness when no lookup is provided.
- On duplicate:
  - `'fail'` throws `ModifyException`
  - `'skip'` returns `SkipRow`
- Uses a safe stringify helper for readable error messages.
- Works with any reader and writer combination.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Unique;
use Tobento\Service\ReadWrite\Row\Row;

// Ensure email is unique across the import
$modifier = new Unique(
    fields: 'email',
    lookup: null,      // use in-memory uniqueness
    onFail: 'skip',    // or 'fail'
);

$row1 = new Row(
    key: 1,
    attributes: ['email' => 'john@example.com']
);

$row2 = new Row(
    key: 2,
    attributes: ['email' => 'john@example.com']
);

// First row passes
$modifier->modify($row1, $reader, $writer);

// Second row is skipped:
$modified = $modifier->modify($row2, $reader, $writer);

// Result:
// SkipRow {
//   key: 2,
//   attributes: ['email' => 'john@example.com'],
//   reason: 'Duplicate value for unique field "email": john@example.com'
// }
```

**Example: Database lookup**

```php
use Tobento\Service\ReadWrite\Modifier\Unique;
use Tobento\Service\ReadWrite\RowInterface;

$modifier = new Unique(
    fields: 'sku',
    lookup: fn(string $field, mixed $value, RowInterface $row): bool =>
        $productRepository->skuExists($value),
    onFail: 'fail'
);
```

**Notes**

- null values are ignored and do not count as duplicates.
- In-memory uniqueness is per import process.
- For persistent uniqueness, use a lookup callable.
- Composite uniqueness can be implemented via the lookup callable.
    
### Validation Modifier

The `Validation` modifier validates row attributes using the  
[tobento/service-validation](https://github.com/tobento-ch/service-validation) package.  
It is useful for enforcing required fields, checking formats, and ensuring data integrity before further processing or storage.

**Requirements**

To use the `Validation` modifier, you must install the  
[tobento/service-validation](https://github.com/tobento-ch/service-validation) package:

```
composer require tobento/service-validation
```

**Features**

- Implements `ModifierInterface`.
- Accepts a `['attribute' => rules]` array of validation rules.
- Dot-aware paths supported (e.g. `user.email`, `items.0.price`).
- Uses `ValidatorInterface` to apply rules such as `required`, `email`, `int`, `min`, etc.
- On validation failure:
  - `'fail'` throws `ModifyErrorsException`
  - `'skip'` returns `SkipRow`
- Groups error messages by field for readable output.
- Works with any reader and writer combination.

**Example**

```php
use Tobento\Service\ReadWrite\Modifier\Validation;
use Tobento\Service\ReadWrite\Row\Row;
use Tobento\Service\Validation\Validator;

$validator = new Validator();

// Define validation rules
$modifier = new Validation(
    rules: [
        'email' => 'required|email',
        'age'   => 'required|int|minNum:18',
    ],
    validator: $validator,
    onFail: 'skip', // or 'fail'
);

$row = new Row(
    key: 1,
    attributes: [
        'email' => 'not-an-email',
        'age'   => '17',
        'name'  => 'John Doe',
    ]
);

// Normally applied by processors, but can be invoked manually:
$modified = $modifier->modify($row, $reader, $writer);

// Result when validation fails and onFail = 'skip':
// SkipRow {
//   key: 1,
//   attributes: [
//     'email' => 'not-an-email',
//     'age'   => '17',
//     'name'  => 'John Doe',
//   ],
//   reason: 'Validation failed: [error] The email must be a valid email address (email) [error] Must be at least 18 (age)'
// }

// If onFail = 'fail', a ModifyErrorsException is thrown instead.
```

## Processors

Processors form the transformation layer between reading and writing.  
They take each row produced by a reader and pass it through a configurable pipeline of modifiers, filters, and other processing steps before the row reaches the writer.

A processor does not change how data is read or written - instead, it controls **how data flows and is transformed** between the two. This makes processors the central place for applying business logic, normalization, validation, or schema adjustments.

**Why Processors Are Useful**

- **Pipeline orchestration**: They execute modifiers in sequence, ensuring each row is transformed consistently.
- **Separation of concerns**: Readers read, writers write - processors handle everything in between.
- **Flexibility**: You can add, remove, or reorder processing steps without touching readers or writers.
- **Reusability**: The same processor configuration can be reused across different import/export workflows.
- **Consistency**: All processors follow the same contract, making them easy to integrate and extend.

Use processors whenever you need to apply transformations, filtering, or mapping logic to rows before they are written.

### Default Processor

The default `Processor` coordinates the full read → modify → write workflow using a reader, writer, and a set of modifiers.  
It handles row iteration, modifier execution, writer mode selection, error tracking, and optional result handling.

**Key Responsibilities**

- Iterates through rows from the reader using `read(offset, limit)`.
- Applies all modifiers in sequence via `ModifiersInterface`.
- Handles skippable rows before and after modification.
- Writes processed rows to the writer.
- Selects writer mode (`Overwrite`, `Append`, `Finalize`) when supported.
- Tracks successful, failed, and skipped rows.
- Wraps unexpected errors in a `ProcessException`.
- Produces a `Result` object summarizing the operation.
- Delegates row-level and result-level events to an optional `ResultHandlerInterface`.

**Example**

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\Processor\Processor;
use Tobento\Service\ReadWrite\Reader;
use Tobento\Service\ReadWrite\Writer;

// Reader and writer
$reader = new Reader\CsvStream(
    stream: new Psr17Factory()->createStreamFromFile('/data/input.csv'),
);
$writer = new Writer\CsvResource(
    resource: new Writer\Resource\LocalFile('/data/output.csv'),
);

// Create modifiers (empty for this example)
$modifiers = new Modifiers();

// Create processor
$processor = new Processor(
    modifiers: $modifiers,
    resultHandler: null,
);

// Process rows starting from the reader's current offset
$result = $processor->process(
    reader: $reader,
    writer: $writer,
    offset: $reader->currentOffset(),
    limit: null,
);

// Inspect result
echo $result->successfulRows(); // e.g. 42
```

#### Processing Flow

1. **Determine writer mode**  
   If the writer implements `ModeAwareInterface`, the processor sets the mode based on the current offset and reader state:
   - `Overwrite` when starting at offset `0`
   - `Append` when continuing and the reader is not finished
   - `Finalize` when processing the last chunk

2. **Start the writer**  
   The processor calls `writer->start()` before any rows are processed.

3. **Iterate through rows**  
   For each row returned by `reader->read(offset, limit)`:
   - Skip immediately if the row implements `SkippableInterface`
   - Apply all modifiers via `ModifiersInterface`
   - Skip again if modifiers mark the row as skippable
   - Write the row using `writer->write()`

4. **Error handling**  
   - `ModifyException` and `WriteException` increment the failed row count  
   - Any other exception is wrapped in a `ProcessException`

5. **Finish the writer**  
   After all rows are processed, the processor calls `writer->finish()`.

6. **Build the result**  
   A `Result` object is created containing:
   - Number of successful, failed, and skipped rows  
   - The reader, writer, and modifiers used  
   - Start and finish timestamps  

7. **Notify result handler**  
   If a `ResultHandlerInterface` is provided, it receives:
   - Row success events  
   - Row skip events  
   - Row failure events  
   - The final result event  

### Time Budget Processor

The `TimeBudgetProcessor` works like the default processor but adds a strict execution time limit.  
It processes rows only as long as the predicted time for the next row stays within the configured time budget.  
This makes it ideal for cron jobs, queue workers, or long-running tasks where execution time must be controlled.

The processor estimates future row duration using a moving average of the last N processed rows.

**Key Responsibilities**

- Processes rows until the time budget is reached or the reader is exhausted.
- Predicts next-row cost using a moving average of recent row processing times.
- Stops early when the next row would exceed the remaining time budget.
- Applies modifiers and handles skippable rows.
- Writes processed rows to the writer.
- Selects writer mode (`Overwrite`, `Append`, `Finalize`) when supported.
- Tracks successful, failed, and skipped rows.
- Wraps unexpected errors in a `ProcessException`.
- Produces a `Result` summarizing the operation.
- Supports optional `ResultHandlerInterface` for row-level and result-level events.

**Example**

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Tobento\Service\ReadWrite\Modifier\Modifiers;
use Tobento\Service\ReadWrite\Processor\TimeBudgetProcessor;
use Tobento\Service\ReadWrite\Reader;
use Tobento\Service\ReadWrite\Writer;

// Reader and writer
$reader = new Reader\CsvStream(
    stream: new Psr17Factory()->createStreamFromFile('/data/input.csv'),
);
$writer = new Writer\CsvResource(
    resource: new Writer\Resource\LocalFile('/data/output.csv'),
);

// Create modifiers (empty for this example)
$modifiers = new Modifiers();

// Create processor with a 20-second time budget
$processor = new TimeBudgetProcessor(
    timeBudget: 20,
    modifiers: $modifiers,
    resultHandler: null,
);

// Process rows starting from the reader's current offset
$result = $processor->process(
    reader: $reader,
    writer: $writer,
    offset: $reader->currentOffset(),
    limit: null,
);

// Inspect result
echo $result->successfulRows();
```

#### Processing Flow

1. **Determine writer mode**  
   If the writer implements `ModeAwareInterface`, the processor sets the mode based on the current offset and reader state:
   - `Overwrite` when starting at offset `0`
   - `Append` when continuing and the reader is not finished
   - `Finalize` when processing the last chunk

2. **Start the writer**  
   The processor calls `writer->start()` before any rows are processed.

3. **Initialize time-budget tracking**  
   - Convert the configured time budget from seconds to nanoseconds  
   - Record the start time using `hrtime(true)`  
   - Prepare a moving window of recent row durations (default: last 50 rows)

4. **Predict next row cost**  
   Before processing each row:
   - Compute the moving average of recent row processing times  
   - Calculate elapsed time and remaining budget  
   - Stop early if the predicted next row would exceed the remaining time budget

5. **Iterate through rows**  
   For each row returned by `reader->read(offset, limit)`:
   - Skip immediately if the row implements `SkippableInterface`
   - Apply all modifiers via `ModifiersInterface`
   - Skip again if modifiers mark the row as skippable
   - Write the row using `writer->write()`
   - Measure the row's processing time and update the moving average window

6. **Error handling**  
   - `ModifyException` and `WriteException` increment the failed row count  
   - Any other exception is wrapped in a `ProcessException`

7. **Finish the writer**  
   After processing stops (either naturally or due to time budget), the processor calls `writer->finish()`.

8. **Build the result**  
   A `Result` object is created containing:
   - Number of successful, failed, and skipped rows  
   - The reader, writer, and modifiers used  
   - Start and finish timestamps  

9. **Notify result handler**  
   If a `ResultHandlerInterface` is provided, it receives:
   - Row success events  
   - Row skip events  
   - Row failure events  
   - The final result event  

## Results

The Results system provides structured feedback about what happened during processing.  
Every processor returns a `ResultInterface` describing how many rows were processed successfully, how many failed, and how many were skipped. This makes it easy to track progress, display summaries, log outcomes, or resume processing later.

Results are split into two parts:  
- **Result Handler** - optional callbacks that receive row-level and final result events during processing.
- **Result Object** - the final summary returned by the processor, containing counters, timestamps, and references to the reader, writer, and modifiers used.

Together, these components give you full visibility into the processing workflow and allow you to integrate reporting, logging, or UI updates in a clean and consistent way.

### Result Handler

A `ResultHandlerInterface` allows you to react to processing events as they occur.  
While the `Result` object provides a final summary after processing finishes, a result handler gives you **real-time hooks** for row-level and batch-level events.

Result handlers are especially useful in queue-driven or event-driven architectures, where you may want to:

- Log each processed row  
- Dispatch queue jobs  
- Track skipped or failed rows  
- Update progress indicators  
- Emit domain events  
- Build monitoring dashboards  

Processors call the handler methods synchronously and only if a handler is provided.  
Handlers never modify rows or influence the processing flow - they are purely observational.

#### Event Methods

- **`handleRowSuccess(RowInterface $row)`**  
  Triggered when a row is processed successfully.  
  Useful for logging, progress updates, or dispatching follow-up jobs.

- **`handleRowSkip(SkippableInterface $row)`**  
  Triggered when a row is skipped by the reader or modifiers.  
  Useful for tracking skipped rows or recording data quality issues.

- **`handleRowFailure(RowInterface $row, Throwable $exception)`**  
  Triggered when a row fails due to a modification or write error.  
  Useful for logging errors, queueing retries, or alerting.

- **`handleResult(ResultInterface $result)`**  
  Triggered once after processing completes.  
  Useful for logging summaries, dispatching "batch finished" events, or updating dashboards.

#### Example: PSR‑3 Logging Result Handler

```php
use Psr\Log\LoggerInterface;
use Tobento\Service\ReadWrite\ResultHandlerInterface;
use Tobento\Service\ReadWrite\ResultInterface;
use Tobento\Service\ReadWrite\RowInterface;
use Tobento\Service\ReadWrite\Row\SkippableInterface;
use Throwable;

class LoggingResultHandler implements ResultHandlerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handleRowSuccess(RowInterface $row): void
    {
        $this->logger->info('Row processed successfully', [
            'row' => $row,
        ]);
    }

    public function handleRowSkip(SkippableInterface $row): void
    {
        $this->logger->notice('Row skipped', [
            'row' => $row,
        ]);
    }

    public function handleRowFailure(RowInterface $row, Throwable $exception): void
    {
        $this->logger->error('Row processing failed', [
            'row'       => $row,
            'exception' => $exception,
        ]);
    }

    public function handleResult(ResultInterface $result): void
    {
        $this->logger->info('Processing finished', [
            'successful' => $result->successfulRows(),
            'failed'     => $result->failedRows(),
            'skipped'    => $result->skippedRows(),
            'runtime'    => $result->runtimeInSeconds(),
        ]);
    }
}
```

### Result Object

The default `Result` implementation provides a complete summary of a processing run.  
It is returned by every processor and contains counters, timestamps, references to the components involved, and optional metadata.  
This makes it easy to display summaries, log outcomes, or resume processing based on the final state.

The `Result` object is immutable: all values are set at construction time and exposed through read-only accessors.

**Features**
- Tracks successful, failed, and skipped rows.
- Stores references to the reader, writer, and modifiers used during processing.
- Records start and finish timestamps.
- Computes total runtime in seconds.
- Supports custom metadata for additional context.
- Provides a structured timeline summary for logging or monitoring.

**Example**

```php
$result = $processor->process($reader, $writer);

echo $result->successfulRows();   // e.g. 42
echo $result->failedRows();       // e.g. 3
echo $result->runtimeInSeconds(); // e.g. 1.52
```

**Available Data**

#### Row Counters
- **`successfulRows()`**  
  Returns the number of rows that were processed and written successfully.

- **`failedRows()`**  
  Returns the number of rows that failed during modification or writing.

- **`skippedRows()`**  
  Returns the number of rows skipped either by the reader or by modifiers.

#### Components
- **`reader()`**  
  Returns the `ReaderInterface` instance used during processing.

- **`writer()`**  
  Returns the `WriterInterface` instance used during processing.

- **`modifiers()`**  
  Returns the `ModifiersInterface` applied to each row.

These references allow you to inspect configuration, offsets, or writer state after processing.

#### Metadata
- **`meta()`**  
  Returns an array of custom metadata passed into the result.  
  Useful for storing batch IDs, session information, or additional context.

#### Timing Information
- **`startedAt()`**  
  Timestamp when processing began.

- **`finishedAt()`**  
  Timestamp when processing ended.

- **`runtimeInSeconds()`**  
  Total processing duration in seconds, based on the difference between start and finish timestamps.

#### Timeline Summary
- **`timeline()`**  
  Returns a structured array containing timestamps, runtime, and row statistics.  
  Ideal for logging, monitoring, or debugging.

```php
[
    'started_at' => '2025-01-01T12:00:00+00:00',
    'finished_at' => '2025-01-01T12:00:01+00:00',
    'runtime_seconds' => 1.0,
    'rows' => [
        'successful' => 42,
        'failed' => 3,
        'skipped' => 1,
        'total' => 46,
    ],
]
```

## Events

The Import/Export workflow provides simple event value objects that describe different stages of processing:

- **ProcessStarted** - emitted when processing begins  
- **PartialProcess** - emitted during processing (e.g., after a chunk)  
- **ProcessCompleted** - emitted when processing finishes successfully  
- **ProcessFailed** - emitted when processing stops due to an error  

These events are **not dispatched automatically**.  
Your application is responsible for dispatching them, which gives you full control over how and when they are used (e.g., for logging, queueing, or UI updates).

Each event contains a `ResultInterface` so listeners can inspect progress or statistics.

### Example: Dispatching Events While Running a Processor

```php
use Psr\EventDispatcher\EventDispatcherInterface;
use Tobento\Service\ReadWrite\Event;
use Tobento\Service\ReadWrite\ProcessorInterface;
use Tobento\Service\ReadWrite\Result;

class ImportRunner
{
    public function __construct(
        private ProcessorInterface $processor,
        private EventDispatcherInterface $events,
    ) {}

    public function run($reader, $writer): void
    {
        // Dispatch: processing started
        $this->events->dispatch(new Event\ProcessStarted(
            result: new Result(
                successfulRows: 0,
                failedRows: 0,
                skippedRows: 0,
                reader: $reader,
                writer: $writer,
                modifiers: $this->processor->modifiers(),
            ),
        ));

        try {
            // Run the processor
            $result = $this->processor->process(
                reader: $reader,
                writer: $writer,
                offset: $reader->currentOffset()
            );

            // Dispatch: partial progress (optional)
            $this->events->dispatch(new Event\PartialProcess($result));

            // Dispatch: processing completed
            if ($reader->isFinished()) {
                $this->events->dispatch(new Event\ProcessCompleted($result));
            }

        } catch (\Throwable $e) {

            // Build a minimal failure result snapshot
            $failureResult = new Result(
                successfulRows: 0,
                failedRows: 0,
                skippedRows: 0,
                reader: $reader,
                writer: $writer,
                modifiers: $this->processor->modifiers(),
                meta: ['failed' => true],
            );

            // Dispatch: processing failed
            $this->events->dispatch(new Event\ProcessFailed(
                result: $failureResult,
                exception: $e,
            ));

            throw $e;
        }
    }
}

```

Just make sure you pass an event dispatcher to your runner!

## Learn More

### Using Processors with Registries and Queues

This package focuses on reading, writing, modifying, and processing data.  
It does **not** define how readers, writers, or modifiers are registered or configured.  
Different applications solve this differently (CRUD fields, JSON config, DI factories, etc.).

Because of this, applications are expected to provide their own **registry layer** that knows how to create
readers, writers, and modifiers from stored job definitions.

Below is a simplified example showing how an application might integrate a processor with a registry and a
queue worker.  
The example demonstrates a possible job handler for the [queue service](https://github.com/tobento-ch/service-queue).

```php
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tobento\Service\ReadWrite\Event;
use Tobento\Service\ReadWrite\Processor\TimeBudgetProcessor;
use Tobento\Service\ReadWrite\RegistriesInterface;
use Tobento\Service\ReadWrite\Result;
use Tobento\Service\ReadWrite\ResultHandlerInterface;
use Tobento\Service\Queue\JobHandlerInterface;
use Tobento\Service\Queue\JobInterface;
use Tobento\Service\Queue\Parameter;
use Tobento\Service\Queue\QueuesInterface;

class TimeBudgetJobHandler implements JobHandlerInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected RegistriesInterface $registries,
        protected JobRepositoryInterface $jobRepository,
        protected QueuesInterface $queues,
        protected null|ResultHandlerInterface $resultHandler = null,
        protected null|EventDispatcherInterface $eventDispatcher = null,
    ) {}

    public function handleJob(JobInterface $job): void
    {
        $id = (int)($job->getPayload()['job_id'] ?? 0);

        if (is_null($jobEntity = $this->jobRepository->findById($id))) {
            // Optional: dispatch job-not-found event
            return;
        }
        
        // Resolve reader
        $readerRegistry = $this->registries->get($jobEntity->readerId());
        if (is_null($readerRegistry)) {
            return;
        }
        $reader = $readerRegistry->createReader($this->container, $jobEntity);

        // Resolve writer + modifiers
        $writerRegistry = $this->registries->get($jobEntity->writerId());
        if (is_null($writerRegistry)) {
            return;
        }
        $writer = $writerRegistry->createWriter($this->container, $jobEntity);
        $modifiers = $writerRegistry->createModifiers($this->container, $jobEntity);

        // Job data (offset)
        $data = $job->parameters()->get(Parameter\Data::class)
            ?? new Parameter\Data(['offset' => 0]);

        if (! $job->parameters()->has(Parameter\Data::class)) {
            $job->parameters()->add($data);
        }

        $offset = $data->get('offset', 0);

        // Optional: dispatch start event
        $this->eventDispatcher?->dispatch(new Event\ProcessStarted(
            result: new Result(
                successfulRows: 0,
                failedRows: 0,
                skippedRows: 0,
                reader: $reader,
                writer: $writer,
                modifiers: $modifiers,
            ),
        ));

        try {
            // Run processor with time budget
            $processor = new TimeBudgetProcessor(
                timeBudget: $job->getPayload()['timeBudget'] ?? 20,
                modifiers: $modifiers,
                resultHandler: $this->resultHandler,
            );

            $result = $processor->process(
                reader: $reader,
                writer: $writer,
                offset: $offset,
            );

            $this->eventDispatcher?->dispatch(new Event\PartialProcess($result));

            // Requeue if not finished
            if (! $reader->isFinished()) {
                $data->set('offset', $reader->currentOffset());
                $job->parameters()->add($data);

                $this->queues
                    ->queue($job->parameters()->get(Parameter\Queue::class)->name())
                    ->push($job);

                return;
            }

            // Completed
            $this->eventDispatcher?->dispatch(new Event\ProcessCompleted($result));

        } catch (\Throwable $e) {

            $failureResult = new Result(
                successfulRows: 0,
                failedRows: 0,
                skippedRows: 0,
                reader: $reader,
                writer: $writer,
                modifiers: $modifiers,
                meta: ['failed' => true],
            );

            $this->eventDispatcher?->dispatch(new Event\ProcessFailed(
                result: $failureResult,
                exception: $e,
            ));

            throw $e;
        }
    }
}
```

**Full Example**

A complete implementation, including registries, CRUD configuration, and job entities, is available in the
[Import/Export App](https://github.com/tobento-ch/app-import-export).

# Credits

- [Tobias Strub](https://www.tobento.ch)
- [All Contributors](../../contributors)