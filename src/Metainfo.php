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
use Speedwork\Core\Helper;
use Speedwork\Core\Registry;
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Metainfo extends Helper
{
    public $enable = true;

    public function index()
    {
        if (!$this->enable) {
            return false;
        }

        $canonicals = Registry::get('canonical');
        if ($canonicals && is_array($canonicals)) {
            foreach ($canonicals as $k => $link) {
                if (!preg_match('/(http|https):\/\//', $link) && substr($link, 0, 2) != '//') {
                    $link = _URL.$link;
                }
                $this->get('template')->addHeadLink($link, 'canonical');
            }
        }

        $conf = Configure::read('metainfo_url_config');

        if (!is_array($conf)) {
            return false;
        }

        $option = $this->option;
        $view   = $this->view;
        $option = str_replace('com_', '', $option);

        $k = $option.':'.$view;

        //for short
        $key    = $conf[$k];
        $uniqid = $key['uniqid'];
        if ($uniqid == '') {
            $key    = $conf[$option.':*'];
            $uniqid = $key['uniqid'];
        }

        //$this->get('template')->setMetaData('og:url',$linkallreviews,'property');

        if ($uniqid):
            $id = $_GET[$uniqid];
        $id     = ($id == 'none') ? '' : $id;
        $meta   = $this->getMetainfo($id, $k);
        if ($meta['meta_title']) {
            $this->get('template')->setTitle($meta['meta_title']);
            $this->get('template')->setMetaData('og:title', $meta['meta_title'], 'property');
        }
        if ($meta['meta_keywords']) {
            $this->get('template')->setKeywords($meta['meta_keywords']);
        }
        if ($meta['meta_descn']) {
            $this->get('template')->setDescription($meta['meta_descn']);
            $this->get('template')->setMetaData('og:description', $meta['meta_descn'], 'property');
        }
        endif;
        //end short
    }

    public function save($save = [], $condition = [])
    {

        //sanitize short url
        $parts          = Utility::parseQuery($save['original_url']);
        $save['option'] = $parts['option'];
        $save['view']   = $parts['view'];
        $save['option'] = str_replace('com_', '', $save['option']);

        if (empty($save['uniqueid'])) {
            //get config
            $conf   = Configure::read('metainfo_url_config');
            $k      = $save['option'].':'.$save['view'];
            $key    = $conf[$k];
            $uniqid = $key['uniqid'];
            if ($uniqid == '') {
                $key    = $conf[$save['option'].':*'];
                $uniqid = $key['uniqid'];
            }
            $save['uniqueid'] = $parts[$uniqid];
        }

        if (empty($save['option'])) {
            return false;
        }

        //validate
        if (empty($save['meta_title'])) {
            return false;
        }

        $id = $save['id'];
        unset($save['id']);

        $save['option'] = $save['option'].':'.$save['view'];
        unset($save['view']);

        if (count($condition) > 0) {
            //check already exists
            $condition['option'] = $save['option'];
            $row                 = $this->database->find('#__addon_metainfo', 'first', ['conditions' => $condition]);
            $id                  = $row['id'];
        }

        if ($id) {
            return $this->database->update('#__addon_metainfo', $save, ['id' => $id]);
        } else {
            $save['added'] = time();

            return $this->database->save('#__addon_metainfo', $save);
        }
    }

    public function getMetainfo($uniq_id, $option = '')
    {
        $option = (empty($option)) ? $this->option.':'.$this->view : $option;

        $data = $this->database->find('#__addon_metainfo', 'first', ['conditions' => ['uniqueid' => $uniq_id,
                                                                                            'option' => $option,
                                                                                            ],
                                                                          ]
                                        );

        return $data;
    }
}
