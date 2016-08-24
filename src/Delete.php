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

use Speedwork\Core\Helper as BaseHelper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Delete extends BaseHelper
{
    protected $tables = [];

    public function beforeRun()
    {
        $tables = $this->config('database.tables');

        $this->tables = array_merge($tables['default'], $tables['delete']);
    }

    public function beforeDelete(&$query = [])
    {
        $userid = $this->get('userid');

        if (empty($userid)) {
            return true;
        }

        $table  = str_replace('#__', '', $query['table']);
        $column = $this->tables[$table];

        if (!isset($column)) {
            return true;
        }

        $alias = ($query['alias']) ? $query['alias'].'.' : '';

        $query['conditions'][] = [$alias.$column => $userid];

        return true;
    }
}
