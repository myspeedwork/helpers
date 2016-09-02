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

use Speedwork\Core\Helper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Audit extends Helper
{
    public function afterSave($results, $params = [], $details = [], $query = null)
    {
        if ($details['audit'] === false) {
            return $results;
        }

        $this->audit($results, $params, $details, $query, 'SAVE');

        return $results;
    }

    public function afterUpdate($results, $params = [], $details = [], $query = null)
    {
        $this->audit($results, $params, $details, $query, 'UPDATE');

        return $results;
    }

    public function afterDelete($results, $params = [], $details = [], $query = null)
    {
        $this->audit($results, $params, $details, $query, 'DELETE');

        return $results;
    }

    private function audit($results, $params = [], $details = [], $query = null, $type = 'UPDATE')
    {
        if ($details['audit'] === false) {
            return $results;
        }

        $save               = [];
        $save['user_id']    = $this->get('userid');
        $save['table_name'] = $params['table'];
        $save['route']      = app('option').':'.app('view');
        $save['sql_query']  = $query;
        $save['ip']         = ip();
        $save['created']    = time();
        $save['type']       = $type;

        $this->database->save('#__addon_audit', $save, ['audit' => false]);
    }
}
