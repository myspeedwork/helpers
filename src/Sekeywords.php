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
class Sekeywords extends Helper
{
    public $enable = true;

    public $servers = [
        'google'  => 'q',
        'yahoo'   => 'p',
        'msn'     => 'q',
        'ask.com' => 'q',
        'aol'     => 'q',
    ];

    public function get()
    {
        $result  = $this->getKeywords();
        $keyword = $result['keyword'];
        $engine  = $result['engine'];

        if ($result && $keyword) {
            $save                  = [];
            $save['keyword']       = $keyword;
            $save['search_engine'] = $engine;
            $save['created']       = time();

            $this->database->save('#__addon_sekeywords', $save);
        }
    }

    public function getKeywords()
    {
        $url = env('HTTP_REFERER');
        $url = parse_url($url);
        if (!isset($url['query'])) {
            return false;
        }

        foreach ($this->servers as $host => $query) {
            if (strstr($url['host'], $host)) {
                $match = [];

                preg_match('/[^a-z]'.$query.'=.+\&/U', $url['query'], $match);
                if (!isset($match[0]) || empty($match[0])) {
                    preg_match('/[^a-z]'.$query.'=.+$/', $url['query'], $match);
                }
                if (empty($match[0])) {
                    return false;
                }
                $kString = urldecode(str_replace('+', ' ', ltrim(substr(rtrim($match[0], '&'), strlen($query) + 1), '=')));

                return [
                    'keyword' => $kString,
                    'engine'  => $host,
                ];
            }
        }
    }
}
