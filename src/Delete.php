<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stechno.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Helpers;

use Speedwork\Config\Configure;
use Speedwork\Core\Helper as BaseHelper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Delete extends BaseHelper
{
    protected $tables = [];
    protected $agents = [];
    protected $userid;
    protected $agent_id;

    public function beforeRun()
    {
        $tables = Configure::read('event_tables');

        $this->tables   = array_merge($tables['default'], $tables['delete']);
        $this->agents[] = ['users'];

        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');
    }

    public function beforeDelete(&$query = [])
    {
        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');

        if (empty($this->userid)) {
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

        // Replacing the userid with agent id
        foreach ($conditions as $key => &$value) {
            $value = str_replace($this->userid, $this->agent_id, $value);
        }

        $query['conditions'] = $conditions;

        return $query;
    }
}
