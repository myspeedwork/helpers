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

use Speedwork\Config\Configure;
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
    protected $agents = [];
    protected $userid;
    protected $agent_id;

    public function beforeRun()
    {
        $tables = Configure::read('event_tables');

        $this->tables   = array_merge($tables['default'], $tables['update']);
        $this->agents[] = ['users', 'user_contact_details', 'user_permissions'];

        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');
    }

    public function beforeUpdate(&$query = [], $details = [])
    {
        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');

        //$query['fields'] = Utility::stripTags($query['fields']);

        if ($details['ignore'] === true || empty($this->userid)) {
            return true;
        }

        $table = str_replace('#__', '', $query['table']);

        if ($this->agent_id && in_array($table, $this->agents)) {
            $query = $this->changeConditions($query);
        }

        $column = $this->tables[$table];

        if (!isset($column)) {
            return true;
        }

        $alias = ($query['alias']) ? $query['alias'].'.' : '';

        $query['conditions'][] = [$alias.$column => $this->userid];

        return true;
    }

    /**
     * [changeConditions description].
     *
     * @param [type] $query    [description]
     * @param [type] $agent_id [description]
     *
     * @return [type] [description]
     */
    private function changeConditions(&$query)
    {
        $conditions = $query['conditions'];

        foreach ($conditions as $key => &$value) {
            $value = str_replace($this->userid, $this->agent_id, $value);
        }

        $query['conditions'] = $conditions;

        return $query;
    }
}
