<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Helpers;

use Ddeboer\DataImport\Reader\ArrayReader;
use Ddeboer\DataImport\Reader\CsvReader;
use Ddeboer\DataImport\Reader\ExcelReader;
use EmptyIterator;
use Guzzle\Iterator\ChunkedIterator;
use Guzzle\Iterator\MapIterator;
use LimitIterator;
use Speedwork\Core\Helper;
use SplFileObject;
use Traversable;

/**
 * Reader class to get details from iterator.
 *
 * @since  0.0.1
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class Reader extends Helper
{
    public function fetchAll($reader, callable $callable = null, $options = [])
    {
        $ext = (!empty($options['ext'])) ? $options['ext'] : strtolower(strrchr($reader, '.'));
        $ext = strtolower($ext);

        if (!in_array($ext, ['.array', '.iterator']) &&
            (!is_file($reader) || !file_exists($reader))) {
            return new EmptyIterator();
        }

        $lines      = ($ext == '.txt') ? 1 : 5;
        $duplicates = CsvReader::DUPLICATE_HEADERS_INCREMENT;

        if (!in_array($ext, ['.array','.iterator'])) {
            $file = new SplFileObject($reader);
            //Auto detect delimiter
            if (empty($options['delimiter'])) {
                $options['delimiter'] = $this->detectDelimiter($reader, $lines);
            }
        }

        switch ($ext) {
            case '.txt':
            case '.csv':

                if ($options['delimiter']) {
                    $reader = new CsvReader($file, $options['delimiter']);
                } else {
                    $reader = new CsvReader($file);
                }
                break;
            case '.xls':
            case '.xlsx':

                if ($options['delimiter'] === "\t" && $ext == '.xls') {
                    $reader = new CsvReader($file, $options['delimiter']);
                } else {
                    $reader     = new ExcelReader($file, null, 0);
                    $duplicates = null;
                }

                break;

            case '.array':
                if (!is_array($reader)) {
                    $reader = explode("\n", $reader);
                }

                $reader = new ArrayReader($reader);
                break;

            case '.iterator':
            default:
                break;
        }

        if (!in_array($ext, ['.array','.iterator']) && $options['header']) {
            $reader->setHeaderRowNumber($options['header'] - 1, $duplicates);
        }

        // Closing the file object
        $file = null;

        return $this->modify($reader, $callable, $options);
    }

    /**
     * Modify the iterator using callback closure.
     *
     * @param Traversable $reader   Travarsable object
     * @param callable    $callable [description]
     * @param array       $options  [description]
     *
     * @return Traversable [description]
     */
    public function modify($reader, callable $callable = null, $options = [])
    {
        if (is_numeric($options['limit'])) {
            $offset = 0;
            if ($options['offset']) {
                $offset = $options['offset'];
            } elseif ($options['page']) {
                $offset = $options['limit'] * ($options['page'] - 1);
            }

            if ($offset && empty($options['total'])) {
                $options['total'] = $this->count($reader);
            }

            // number of records to fetch total
            if ($options['total']) {
                $total = $offset + $options['limit'];
                if ($total > $options['total']) {
                    $options['limit'] = $options['total'] - $offset;
                }
            }

            $options['offset'] = $offset;
            $count             = $options['total'];

            if ($count !== null && $offset >= $count) {
                $reader = new EmptyIterator();
            } else {
                $reader = new LimitIterator($reader, $options['offset'], $options['limit']);
            }
        }

        if (!is_null($callable)) {
            $reader = new MapIterator($reader, $callable);
        }

        return $reader;
    }

    public function fetchHeader($file, callable $callable = null, $options = [])
    {
        $options['limit'] = 1;
        $columns          = $this->fetchArray($file, $callable, $options);

        return $columns[0];
    }

    public function fetchArray($file, callable $callable = null, $options = [])
    {
        $rows = $this->fetchAll($file, $callable, $options);

        return iterator_to_array($rows);
    }

    public function fetchCount($file, callable $callable = null, $options = [])
    {
        $rows = $this->fetchAll($file, $callable, $options);

        return iterator_count($rows);
    }

    public function count(Traversable $iterator)
    {
        return iterator_count($iterator);
    }

    public function hasNext(Traversable $iterator)
    {
        return iterator_count($iterator);
    }

    public function getChunk(Traversable $iterator, $size = 100)
    {
        return new ChunkedIterator($iterator, $size);
    }

    public function toArray(Traversable $iterator)
    {
        return iterator_to_array($iterator);
    }

    public function detectDelimiter($file, $sample = 5)
    {
        $delimsRegex = "|,;:\t"; // whichever is first in the list will be the default
        $delims      = str_split($delimsRegex);
        $delimCount  = $delimiters  = [];
        foreach ($delims as $delim) {
            $delimCount[$delim] = 0;
            $delimiters[]       = $delim;
        }

        $lines = $this->getLines($file, $sample);

        foreach ($lines as $row) {
            $row      = preg_replace('/\r\n/', '', trim($row));  // clean up .. strip new line and line return chars
            $row      = preg_replace("/[^$delimsRegex]/", '', $row);  // clean up .. strip evthg which is not a dilim'r
            $rowChars = str_split($row);  // break it apart char by char

            foreach ($rowChars as $char) {
                foreach ($delimiters as $delim) {
                    if (strpos($char, $delim) !== false) {
                        // if the char is the delim ...
                        ++$delimCount[$delim];  // ... increment
                    }
                }
            }
        }

        $detected = array_keys($delimCount, max($delimCount));

        return $detected[0];
    }

    public function getLines($file, $total = 5)
    {
        $handle = fopen($file, 'r');

        $line  = 0;
        $lines = [];
        while (!feof($handle)) {
            $lines[] = fgets($handle, 1024);
            ++$line;
            if ($line >= $total) {
                break;
            }
        }

        fclose($handle);

        return $lines;
    }
}
