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
        $config = $this->config('app.metainfo');

        if (!is_array($config)) {
            return false;
        }

        if ($config['enable'] !== true) {
            return false;
        }

        list($option, $view) = explode('.', $this->get('route'));

        $component = null;

        $matches = [
            $option.':'.$view,
            $option.':',
            $option.':*',
        ];

        $component  = $option.':'.$view;
        $components = $config['config'];

        $uniqids = [];
        foreach ($matches as $match) {
            $uniqids = $components[$match];
            if ($uniqids) {
                $component = $match;
                break;
            }
        }

        $ids = [];
        foreach ($uniqids as $uniqid) {
            $ids[] = $this->data[$uniqid] ?: '';
        }

        $row = $this->getMetainfo($ids, $component);

        if ($row['title']) {
            $this->get('template')->setMeta('title', $row['title']);
            $this->get('template')->setMeta('og:title', $row['title'], 'property');
            $this->get('template')->setMeta('twitter:title', $row['title'], 'property');
            $this->config(['app.meta.title' => $row['title']]);
        }

        if ($row['keywords']) {
            $this->get('template')->setMeta('keywords', $row['keywords']);
        }

        if ($row['descn']) {
            $this->get('template')->setMeta('description', $row['descn']);
            $this->get('template')->setMeta('og:description', $row['descn'], 'property');
            $this->get('template')->setMeta('twitter:description', $row['descn'], 'property');
        }

        if ($row['canonical']) {
            $this->get('template')->addLinkTag($this->link($row['canonical']), 'canonical');
        }

        $metas = json_decode($row['meta'], true);
        if (is_array($metas)) {
            foreach ($metas as $meta) {
                $this->get('template')->setMeta($meta['name'], $meta['content'], $meta['type']);
            }
        }
    }

    /**
     * Get meta information.
     *
     * @param string $uniqid Uniqid to get meta
     * @param string $option Identifier
     *
     * @return array Meta info
     */
    public function getMetainfo($uniqid = '', $option = '')
    {
        return $this->database->find('#__addon_metainfo', 'first', [
            'conditions' => [
                'uniqid'    => $uniqid,
                'component' => $option,
                'status'    => 1,
            ],
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
