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

use League\CommonMark\CommonMarkConverter;
use Speedwork\Core\Helper;

/**
 *  Helper Class to manipulate data before delete.
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class Find extends Helper
{
    protected $tables = [];
    protected $agents = [];
    protected $userid;
    protected $agent_id;

    protected $markdown = [];

    public function beforeRun()
    {
        $tables = $this->read('event_tables');

        $this->tables   = array_merge($tables['default'], $tables['find']);
        $this->agents[] = ['users', 'user_contact_details', 'user_permissions'];

        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');

        //mark down tables
        $this->markdown = [];
    }

    public function beforeFind(&$query = [])
    {
        //disable filetrs
        if (empty($this->tables) || $query['ignore'] === true) {
            return true;
        }

        $this->userid   = $this->get('userid');
        $this->agent_id = $this->get('agent_id');

        if (empty($this->userid)) {
            return true;
        }

        $table = str_replace('#__', '', $query['table']);

        if ($this->agent_id && in_array($table, $this->agents)) {
            $query = $this->changeConditions($query, $this->agent_id);
        }

        $column = $this->tables[$table];
        if (!isset($column)) {
            return true;
        }

        $conditions   = $query['conditions'];
        $alias        = ($query['alias']) ? $query['alias'].'.' : '';
        $conditions[] = [$alias.$column => $this->userid];

        $query['conditions'] = $conditions;

        return true;
    }

    public function afterFind(&$rows, $params)
    {
        return $rows;
        if (!in_array($params['type'], ['first', 'all'])) {
            return $rows;
        }

        $table   = str_replace('#__', '', $params['table']);
        $columns = $this->markdown[$table];
        if ($columns && is_array($columns)) {
            $converter = new CommonMarkConverter();
            foreach ($rows as &$row) {
                foreach ($columns as $column) {
                    $row[$column] = $converter->convertToHtml($row[$column]);
                }
            }
        }

        return $rows;
    }

    /**
     * [changeConditions description].
     *
     * @param [type] $query    [description]
     * @param [type] $agent_id [description]
     *
     * @return [type] [description]
     */
    private function changeConditions($query, $agent_id)
    {
        $conditions = $query['conditions'];

        foreach ($conditions as $key => &$value) {
            $value = str_replace($this->userid, $agent_id, $value);
        }

        $query['conditions'] = $conditions;

        return $query;
    }
}
