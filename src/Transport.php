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
 * @author Sankar <sankar.suda@gmail.com>
 */
class Transport extends Helper
{
    public function getTransfer($value, $field = 'id', $userid = null)
    {
        $conditions   = [];
        $conditions[] = [$field => $value];
        if ($userid) {
            $conditions[] = ['user_id' => $userid];
        }

        return $this->database->find('#__transport_transfer', 'first', [
            'conditions' => $conditions,
        ]);
    }

    public function getConfig($value, $field = 'id')
    {
        if (empty($value)) {
            return [];
        }

        return $this->database->find('#__transport_config', 'first', [
            'conditions' => [$field => $value, 'status' => 1],
        ]);
    }

    public function setTransfer($name, $transfer, $meta = [], $field = 'service')
    {
        if (!is_array($transfer)) {
            $transfer = $this->getTransfer($transfer, $field);
        }

        if (!is_array($name)) {
            $name = [$name];
        }

        $save                   = [];
        $save['user_id']        = $this->userid;
        $save['fk_transfer_id'] = $transfer['id'];
        $save['service']        = $transfer['service'];
        $save['transfer_files'] = json_encode($name);
        $save['meta_value']     = json_encode($meta);
        $save['created']        = time();
        $save['status']         = 0;

        $res = $this->database->save('#__transport_process', $save);

        $id = null;
        if ($res) {
            $id = $this->database->lastInsertId();
        }

        if ($id) {
            $transfer        = $this->get('resolver')->helper('transfer');
            $transfer->debug = false;
            $transfer->start($id);
        }

        return $id;
    }
}
