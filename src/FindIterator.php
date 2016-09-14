<?php

/*
 * This file is part of the Speedwork package.
 *
 * (c) Sankar <sankar.suda@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Speedwork\Helpers;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Finditerator implements \Iterator
{
    private $position       = 0;
    private $total_position = 0;
    private $next_token     = null;
    private $results        = null;
    private $query          = '';
    private $database       = null;
    private $ok             = true;
    private $page           = 1;

    public function find($database, $query = [])
    {
        $this->position       = 0;
        $this->total_position = 0;
        $this->query          = $query;
        $this->next_token     = null;
        $this->page           = 1;
        $this->database       = $database;
        $this->query();
    }

    public function isOK()
    {
        return $this->ok;
    }

    public function error()
    {
        return $this->database->lastError();
    }

    /**
     * Function to query.
     *
     * @return multitype:array
     */
    private function query()
    {
        $this->query['page'] = $this->page;

        $rows = $this->database->find(
            $this->query['table'],
            $this->query['type'],
            $this->query
        );
        $this->results = $rows;

        $this->page += 1;
        $this->position   = 0;
        $this->next_token = count($rows) >= $this->query['limit'] ? true : false;
        $this->ok         = true;
    }

    public function rewind()
    {
        if ($this->total_position == 0) {
            return;
        }
        $this->position       = 0;
        $this->page           = 1;
        $this->total_position = 0;
        $this->next_token     = null;
        $this->query();
    }

    public function current()
    {
        return $this->results[$this->position];
    }

    public function key()
    {
        return $this->total_position;
    }

    public function next()
    {
        $this->position += 1;
        $this->total_position += 1;
        if (!isset($this->results[$this->position]) && $this->next_token) {
            $this->query();
        }
    }

    public function valid()
    {
        if (!$this->results || !is_array($this->results)) {
            return false;
        }

        return isset($this->results[$this->position]);
    }
}
