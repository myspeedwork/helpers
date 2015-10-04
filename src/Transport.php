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

use Speedwork\Core\Helper;

/**
 *  Helper Class to manipulate data before delete.
 *
 * @since  0.0.1
 */
class Transport extends Helper
{
    public function getTransfer($value, $field = 'id')
    {
        return $this->database->find('#__transport_transfer', 'first', [
            'conditions' => [$field => $value],
            ]
        );
    }

    public function getConfig($value, $field = 'id')
    {
        if (empty($value)) {
            return [];
        }

        return $this->database->find('#__transport_config', 'first', [
            'conditions' => [$field => $value, 'status' => 1],
            ]
        );
    }

    public function setTransfer($name, $value, $meta = [], $field = 'service')
    {
        $transfer = $this->getTransfer($value, $field);

        if (!is_array($name)) {
            $name = [$name];
        }

        $save                   = [];
        $save['fkuserid']       = $this->userid;
        $save['fk_transfer_id'] = $transfer['id'];
        $save['service']        = $transfer['service'];
        //$save['basename'] = $name;
        $save['transfer_files'] = json_encode($name);
        $save['meta_value']     = json_encode($meta);
        $save['created']        = time();
        $save['status']         = 0;

        $res = $this->database->save('#__transport_process', $save);

        $id = null;
        if ($res) {
            $id = $this->database->insertId();
        }

        if ($id) {
            $transfer        = $this->get('resolver')->helper('transfer');
            $transfer->debug = false;
            $transfer->start($id);
        }

        return $id;
    }
}
