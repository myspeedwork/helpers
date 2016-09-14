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

use Speedwork\Core\Helper as BaseHelper;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Update extends BaseHelper
{
    protected $tables = [];

    public function beforeRun()
    {
        $tables = $this->config('database.tables');

        $this->tables = array_merge($tables['default'], $tables['update']);
    }

    public function beforeUpdate(&$query = [], $details = [])
    {
        $userid = $this->get('userid');

        if ($details['ignore'] === true || empty($userid)) {
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
