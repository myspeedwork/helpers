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
use Speedwork\Core\Helper as BaseHelper;
use Speedwork\Core\Registry;

class Whitelabel extends BaseHelper
{
    public $enable = true;

    public function run()
    {
        if (!$this->enable) {
            return true;
        }

        $domain = strtolower(ltrim($_SERVER['HTTP_HOST'], 'www.'));

        if ($this->isCli()) {
            $domain = Configure::read('cli_domain');
        }

        if (empty($domain)) {
            return false;
        }

        $row = $this->database->find('#__whitelist_domains', 'first', [
            'conditions' => ['domain' => $domain,'status' => 1],
            ]
        );

        //$this->database->showQuery(true);

        if (!$this->isCli() && empty($row['fkuserid'])) {
            self::_deleted();
        }

        $siteid   = ($row['configid']) ? $row['configid'] : $row['id'];
        $configid = ($row['configid']) ? [$row['configid'],$row['id']] : [$row['id']];

        Registry::set('domain_owner', $row['fkuserid']);
        Registry::set('configid', $configid);
        Registry::set('siteid', $siteid);
        Configure::write('siteid', $siteid);

        if (!$this->isCli() && $row['fkuserid'] && $row['status'] != 1) {
            self::_deleted();
        }
    }

    private function _deleted()
    {
        $paths   = [];
        $paths[] = APP.'public'.DS.'templates'.DS.'system'.DS.'deleted.tpl';
        $paths[] = SYS.'templates'.DS.'system'.DS.'deleted.tpl';

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $content = file_get_contents($path);
                echo $this->parseContent($content);
                break;
            }
        }
        die('<h2 class="deleted">This site is deleted. Please contact our support team.</h2>');
    }

    private function isCli()
    {
        return (php_sapi_name() === 'cli');
    }

    private function parseContent($content)
    {
        return $content;
    }
}
