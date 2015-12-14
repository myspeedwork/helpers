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
 *  Helper Class to manipulate data before save.
 *
 * @since  0.0.1
 */
class Save extends BaseHelper
{
    protected $tables = [];

    public function beforeRun()
    {
        $tables = config('app.tables');

        $this->tables = array_merge($tables['default'], $tables['save']);
    }

    public function beforeSave(&$data, $table, &$details = [])
    {
        $table = str_replace('#__', '', $table);

        //$data = Utility::stripTags($data);

        $userid = $this->get('userid');

        if ($details['ignore'] === true || empty($userid)) {
            return true;
        }

        $column = $this->tables[$table];

        if (!isset($column)) {
            return true;
        }

        //check is assoc array
        if (is_array($data[0])) {
            foreach ($data as &$value) {
                if (!isset($value[$column])) {
                    $value[$column] = $userid;
                }
            }
        } else {
            if (!isset($data[$column])) {
                $data[$column] = $userid;
            }
        }

        return true;
    }
}
