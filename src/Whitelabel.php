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

use Speedwork\Core\Helper as BaseHelper;

class Whitelabel extends BaseHelper
{
    public function run()
    {
        if (!config('app.whitelabel')) {
            return false;
        }

        $domain = strtolower(ltrim(env('HTTP_HOST'), 'www.'));

        if ($this->isCli()) {
            $domain = config('app.cli_domain');
        }

        if (empty($domain)) {
            return false;
        }

        $row = $this->database->find('#__whitelist_domains', 'first', [
            'conditions' => ['domain' => $domain, 'status' => 1],
        ]);

        if (!$this->isCli() && empty($row['user_id'])) {
            $this->isDeleted();
        }

        $siteid   = ($row['configid']) ? $row['configid'] : $row['id'];
        $configid = ($row['configid']) ? [$row['configid'],$row['id']] : [$row['id']];

        $this->set('domain_owner', $row['user_id']);
        $this->set('configid', $configid);
        $this->set('siteid', $siteid);

        config(['app.siteid' => $siteid]);

        if (!$this->isCli() && $row['user_id'] && $row['status'] != 1) {
            $this->isDeleted();
        }
    }

    private function isDeleted()
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
