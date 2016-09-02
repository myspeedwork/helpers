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
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Metainfo extends Helper
{
    public function index()
    {
        $config = config('app.metainfo');

        if (!is_array($config)) {
            return false;
        }

        if ($config['enable'] !== true) {
            return false;
        }

        $option    = $this->get('option');
        $view      = $this->get('view');
        $component = null;

        $matches = [
            $option.':'.$view,
            $option.':',
            $option.':*',
        ];

        $component  = $option.':'.$view;
        $components = $config['components'];

        foreach ($matches as $match) {
            $uniqid = $components[$match];
            if ($uniqid) {
                $component = $match;
                break;
            }
        }

        $id  = $this->get[$uniqid];
        $id  = ($id) ? $id : '';
        $row = $this->getMetainfo($id, $component);

        if ($row['title']) {
            $this->get('template')->setTitle($row['title']);
            $this->get('template')->setMetaData('og:title', $row['title'], 'property');
            $this->get('template')->setMetaData('twitter:title', $row['title'], 'property');
            config(['app.title' => $row['title']]);
        }

        if ($row['keywords']) {
            $this->get('template')->setKeywords($row['keywords']);
        }

        if ($row['descn']) {
            $this->get('template')->setDescription($row['descn']);
            $this->get('template')->setMetaData('og:description', $row['descn'], 'property');
            $this->get('template')->setMetaData('twitter:description', $row['descn'], 'property');
        }

        if ($row['canonical']) {
            $this->get('template')->addHeadLink($this->link($row['canonical']), 'canonical');
        }

        $metas = json_decode($row['meta'], true);
        if (is_array($metas)) {
            foreach ($metas as $meta) {
                $this->get('template')->setMetaData($meta['name'], $meta['content'], $meta['type']);
            }
        }
    }

    public function getMetainfo($uniqid = '', $option = '')
    {
        return $this->database->find('#__addon_metainfo', 'first', [
            'conditions' => ['uniqid' => $uniqid, 'component' => $option, 'status' => 1],
        ]);
    }

    public function parseUrl($url)
    {
        $parts          = Utility::parseQuery($url);
        $save['option'] = $parts['option'];
        $save['view']   = $parts['view'];
        $save['option'] = str_replace('com_', '', $save['option']);

        if (empty($save['uniqueid'])) {
            //get config
            $conf   = config('app.metainfo.config');
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

        return $save;
    }
}
