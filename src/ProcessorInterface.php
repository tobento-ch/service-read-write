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
 
namespace Tobento\Service\ReadWrite;

use Tobento\Service\ReadWrite\Exception\ProcessException;

interface ProcessorInterface
{
    /**
     * Returns the modifiers.
     *
     * @return ModifiersInterface
     */
    public function modifiers(): ModifiersInterface;
    
    /**
     * Processes data by reading rows from the given reader
     * and writing them to the specified writer.
     *
     * The operation starts at the given offset and continues
     * for up to the specified limit of rows. If limit is null,
     * all available rows from the offset will be processed.
     *
     * @param ReaderInterface $reader The source of rows to read.
     * @param WriterInterface $writer The target to write rows into.
     * @param int $offset Row index or byte position, depending on reader implementation.
     * @param int|null $limit Maximum number of rows to process, or null for no limit.
     * @return ResultInterface Result object containing details about the processing outcome.
     * @throws ProcessException
     */
    public function process(
        ReaderInterface $reader,
        WriterInterface $writer,
        int $offset = 0,
        null|int $limit = null
    ): ResultInterface;
}