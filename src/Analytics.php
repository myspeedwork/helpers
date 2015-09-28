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

use Speedwork\Core\Helper;
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Analytics extends Helper
{
    /**
     * Global conditions for analytics.
     *
     * @param string $timeField [description]
     *
     * @return [type] [description]
     */
    public function conditions($timeField = 'senttime', $time = true)
    {
        $conditions = [];
        //add date range condition
        if ($time) {
            if ($this->post['daterange']) {
                $dateRange = explode('-', $this->post['daterange']);

                if (count($dateRange) == 1) {
                    $conditions[] = [$timeField.' BETWEEN ? AND ?' => [
                        Utility::strtotime($dateRange[0].' 00:00:00'),
                        Utility::strtotime($dateRange[0].' 23:59:59'),
                        ],
                    ];
                } elseif (count($dateRange) == 2) {
                    $conditions[] = [$timeField.' BETWEEN ? AND ?' => [
                        Utility::strtotime($dateRange[0].' 00:00:00'),
                        Utility::strtotime($dateRange[1].' 23:59:59'),
                        ],
                    ];
                }
            } elseif (!$this->post['nodaterange']) {
                $from         = Utility::strtotime(date('Y-m-d', strtotime('-7 days')).' 00:00:00');
                $conditions[] = [$timeField." > $from"];
            }
            $conditions[] = [$timeField.' < unix_timestamp(now())'];
        } else {
            if ($this->post['daterange']) {
                $dateRange = explode('-', $this->post['daterange']);

                if (count($dateRange) == 1) {
                    $conditions[] = [$timeField => Utility::strtotime($dateRange[0], true)];
                } elseif (count($dateRange) == 2) {
                    $conditions[] = [$timeField.' BETWEEN ? AND ?' => [
                        Utility::strtotime($dateRange[0], true),
                        Utility::strtotime($dateRange[1], true),
                        ],
                    ];
                }
            } elseif (!$this->post['nodaterange']) {
                $from         = Utility::strtotime(date('Y-m-d', strtotime('-7 days')), true);
                $conditions[] = [$timeField." > '$from'"];
            }
        }

        // making conditions for reseller for finding its direct user
        if ($this->power == 2 && empty($this->post['subuser'])) {
            $user = $this->database->find('#__user_to_user', 'list', [
                'conditions' => ['fkuserid' => $this->userid],
                'fields'     => ['fkuserid1'],
                ]
            );

            $conditions[] = ['fkuserid ' => $user + [$this->userid]];
        } elseif ($this->post['subuser']) {
            $conditions[] = ['fkuserid ' => $this->post['subuser']];
        }

        //$conditions[] = array('delivtime!=0');
        return $conditions;
    }

    /**
     * [generateTable description].
     *
     * @param [type] $series   [description]
     * @param [type] $name     [description]
     * @param [type] $title    [description]
     * @param [type] $callback [description]
     *
     * @return [type] [description]
     */
    public function generateTable($series, $name, $title, $callback = null)
    {
        $table = '<div style="float:left"><table class="table table-bordered">
            <thead>';
        $table .= '<tr>';
        $table .= '<th>Rank</th>';
        $table .= '<th>'.$name.'</th>';
        $table .= '<th>'.$title.'</th>';
        $table .= '</tr>';

        $table .= '</thead>
            <tbody>';

        $serial = 1;
        foreach ($series as $key => $value) {
            $value = (is_array($value)) ? $value[0] : $value;

            if ($callback != null && is_callable($callback)) {
                $value = call_user_func($callback, $value);
            }

            $table .= '<tr>';
            $table .= '<td>'.$serial.'</td>';
            $table .= '<td>'.$key.'</td>';
            $table .= '<td>'.(($value != '') ? $value : 'Unknown').'</td>';
            $table .= '</tr>';

            if ($serial % 10 == 0) {
                $table .= '</tbody></table></div><div style="float:left">';
                $table .= '<table class="table table-bordered"><thead>';
                $table .= '<tr>';
                $table .= '<th>Rank</th>';
                $table .= '<th>'.$name.'</th>';
                $table .= '<th>'.$title.'</th>';
                $table .= '</tr>';

                $table .= '</thead>
                    <tbody>';
            }

            ++$serial;
        }

        $table .= '</tbody></div>
            </table> ';

        return $table;
    }

    /**
     * [generateTableMulti description].
     *
     * @param [type] $data       [description]
     * @param array  $categories [description]
     * @param [type] $title      [description]
     * @param [type] $callback   [description]
     *
     * @return [type] [description]
     */
    public function generateTableMulti($data, $categories = [], $title = null, $callback = null)
    {
        if (!empty($data)) {
            $headers = array_keys($data);
        }
        $table = '<div style="float:left"><table class="table table-bordered">
            <thead>';

        $table .= '<tr>';
        $table .= '<th>SI</th>';
        $table .= '<th>'.(($title != '') ? $title : 'Name').'</th>';
        if (is_array($headers)) {
            foreach ($headers as $key => $value) {
                $table .= '<th>'.(($value != '') ? $value : 'Unknown').'</th>';
            }
        }

        $maintitle = $title;
        $table .= '</tr>';

        $table .= '</thead>
            <tbody>';

        $serial = 1;
        $inc    = 1;
        if (is_array($categories)) {
            foreach ($categories as $category) {
                $table .= '<tr>';
                $table .= '<td>'.$serial++.'</td>';
                $table .= '<td>'.$category.'</td>';
                if (is_array($headers)) {
                    foreach ($headers as $key => $value) {
                        $title = $data[$value][$category];
                        if ($callback != null && is_callable($callback)) {
                            $title = call_user_func_array($callback, [
                                $title,
                                $key,
                            ]);
                        }
                        $table .= '<td>'.$title.'</td>';

                        if ($inc % 20 == 0) {
                            $table .= '</tbody></table></div><div style="float:left">';
                            $table .= '<table class="table table-bordered"><thead>';
                            $table .= '<tr>';
                            $table .= '<th>SI</th>';
                            $table .= '<th>'.(($maintitle != '') ? $maintitle : 'Name').'</th>';
                            if (is_array($headers)) {
                                foreach ($headers as $key => $value) {
                                    $table .= '<th>'.(($value != '') ? $value : 'Unknown').'</th>';
                                }
                            }
                            $table .= '</tr>';

                            $table .= '</thead>
                                <tbody>';
                        }

                        ++$inc;
                    }
                }
                $table .= '</tr>';
            }
        }
        $table .= '</tbody>
            </table> ';

        return $table;
    }
}
