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

use DeviceDetector\DeviceDetector;
use DeviceDetector\Parser\Device\DeviceParserAbstract;
use Speedwork\Core\Helper;

/**
 * @vendor "piwik/device-detector": "dev-master",
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class Device extends Helper
{
    public function get($advanced = false)
    {
        DeviceParserAbstract::setVersionTruncation(DeviceParserAbstract::VERSION_TRUNCATION_NONE);
        $detect = new DeviceDetector($_SERVER['HTTP_USER_AGENT']);
        $detect->parse();

        $return = [];
        if ($detect->isBot()) {
            $return['bot'] = $detect->getBot();

            return $return;
        }

        //device wrapper
        $devicelist = [
            'desktop'       => 'computer',
            'smartphone'    => 'phone',
            'tablet'        => 'tablet',
            'feature phone' => 'phone',
        ];

        $os     = $detect->getOs();
        $client = $detect->getClient();

        $devicename = $detect->getDeviceName();
        $devicetype = (isset($devicelist[$devicename])) ? $devicelist[$devicename] : 'computer';

        //legacy params
        $return['device']          = $devicename;
        $return['type']            = $devicetype;
        $return['brand']           = $detect->getBrandName();
        $return['os']              = $os['name'];
        $return['os_version']      = $os['version'];
        $return['os_code']         = $os['short_name'];
        $return['browser']         = $client['name'];
        $return['browser_version'] = $client['version'];
        $return['browser_code']    = $client['short_name'];
        $return['browser_type']    = $client['type'];
        $return['browser_engine']  = $client['engine'];

        if (!$advanced) {
            return array_map('trim', $return);
        }

        //advanced params
        $osFamily            = \DeviceDetector\Parser\OperatingSystem::getOsFamily($os['short_name']);
        $return['os_family'] = ($osFamily !== false) ? $osFamily : 'Unknown';

        $return['model'] = $detect->getModel();

        $browserFamily            = \DeviceDetector\Parser\Client\Browser::getBrowserFamily($client['short_name']);
        $return['browser_family'] = ($browserFamily !== false) ? $browserFamily : 'Unknown';

        $touch           = $detect->isTouchEnabled();
        $return['touch'] = $touch[0];

        unset($os, $client, $osFamily, $browserFamily, $touch);

        return array_map('trim', $return);
    }

    public function check()
    {
    }
}
