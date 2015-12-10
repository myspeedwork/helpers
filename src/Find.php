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

use Speedwork\Core\Helper;

/**
 *  Helper Class to manipulate data before delete.
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class Find extends Helper
{
    protected $tables = [];

    public function beforeRun()
    {
        $tables = $this->read('event_tables');

        $this->tables = array_merge($tables['default'], $tables['find']);
    }

    public function beforeFind(&$query = [])
    {
        //disable filetrs
        if (empty($this->tables) || $query['ignore'] === true) {
            return true;
        }

        $userid = $this->get('userid');

        if (empty($userid)) {
            return true;
        }

        $table = str_replace('#__', '', $query['table']);

        $column = $this->tables[$table];
        if (!isset($column)) {
            return true;
        }

        $conditions   = $query['conditions'];
        $alias        = ($query['alias']) ? $query['alias'].'.' : '';
        $conditions[] = [$alias.$column => $userid];

        $query['conditions'] = $conditions;

        return true;
    }
}
