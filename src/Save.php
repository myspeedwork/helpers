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
    protected $agents = [];
    protected $userid;
    protected $agent_id;

    public function beforeRun()
    {
        $tables = $this->read('event_tables');

        $this->tables   = array_merge($tables['default'], $tables['save']);
        $this->agents[] = ['users', 'user_contact_details', 'user_permissions'];

        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');
    }

    public function beforeSave(&$data, $table, &$details = [])
    {
        $table = str_replace('#__', '', $table);

        //$data = Utility::stripTags($data);

        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');

        if ($details['ignore'] === true || empty($this->userid)) {
            return true;
        }

        if ($this->agent_id && in_array($table, $this->agents)) {
            $data = $this->changeConditions($data);
        }

        $column = $this->tables[$table];

        if (!isset($column)) {
            return true;
        }

        //check is assoc array
        if (is_array($data[0])) {
            foreach ($data as &$value) {
                if (!isset($value[$column])) {
                    $value[$column] = $this->userid;
                }
            }
        } else {
            if (!isset($data[$column])) {
                $data[$column] = $this->userid;
            }
        }

        return true;
    }

    /**
     * [changeConditions description].
     *
     * @param [type] $data     [description]
     * @param [type] $agent_id [description]
     *
     * @return [type] [description]
     */
    private function changeConditions($data)
    {
        if (is_array($data[0])) {
            foreach ($data as &$value) {
                foreach ($value as $k => &$v) {
                    $v = str_replace($this->userid, $this->agent_id, $v);
                }
            }
        } else {
            foreach ($data as &$value) {
                $value = str_replace($this->userid, $this->agent_id, $value);
            }
        }

        return $data;
    }
}
