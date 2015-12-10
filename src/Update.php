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
use Speedwork\Util\Utility;

/**
 *  Helper Class to manipulate data before update.
 *
 * @since  0.0.1
 */
class Update extends BaseHelper
{
    protected $tables = [];

    public function beforeRun()
    {
        $tables = $this->read('event_tables');

        $this->tables = array_merge($tables['default'], $tables['update']);
    }

    public function beforeUpdate(&$query = [], $details = [])
    {
        $userid = $this->get('userid');

        //$query['fields'] = Utility::stripTags($query['fields']);

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
