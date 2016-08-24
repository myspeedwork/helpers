<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */
namespace Speedwork\Helpers;

use Speedwork\Core\Helper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Dav extends Helper
{
    public function start()
    {
        $auth = new \Sabre\HTTP\BasicAuth();

        if (!$this->auth($auth)) {
            $auth->requireLogin();
            die(trans('Authentication required'));
        }

        $this->execute();
    }

    private function auth()
    {
        // Set up the HTTP BASIC Authentication
        $realm = 'speed';
        $auth->setRealm(
            // At least remove double quotes
            implode('', explode('"', $realm))
        );
        $result = $auth->getUserPass();

        if (!$result) {
            return false;
        }

        $username = $result[0];
        $password = $result[1];

        // Note: skip authentication if already logged in  and have access
        if (!$this->get('is_user_logged_in')) {
            // Try to authenticate
            $login = $this->get('acl')->LogUserIn($username, $password);

            if ($login !== true) {
                return false;
            }
        }

        if (!$this->get('is_user_logged_in')) {
            if (!$this->get('acl')->isAllowed('webdev:**')) {
                return false;
            }
        }

        return true;
    }

    private function execute()
    {
        //configuration
        $config                         = [];
        $config['vhostenabled']         = '';
        $config['lockmanager']          = true;
        $config['pluginbrowser']        = true;
        $config['pluginmount']          = true;
        $config['plugintempfilefilter'] = true;
        $config['urlrewrite']           = true;
        $config['shared']               = _PUBLIC_DIR;

        //$root = new MyDirectory($path.'templates');

       /* $root = new \Sabre\DAV\ObjectTree(
            new \Sabre\DAV\FS\Directory($path)
        );
        */
        $root = new \Sabre\DAV\FS\Directory($config['shared']);

     /*
       $root = new DAV\SimpleCollection($path,array(
            new DAV\SimpleCollection('tmp'),
            new DAV\SimpleCollection('templates')
        ));
     */

        // Start the Virtual File System on top of the Moodle hierarchy
        $server = new \Sabre\DAV\Server($root);

        if (env('HTTP_X_FORWARDED_SERVER') && env('HTTP_HOST')) {
            env(['REQUEST_URI' => '/'.env('HTTP_HOST').env('REQUEST_URI')]);
            $server->httpRequest = new Sabre_HTTP_Request(env());
        }

        $basepath = _URL.'dev';

        // Do you have exposed the local/davroot folder using a dedicated Virtual Host?
        if (!empty($config['vhostenabled'])) {
            $basepath = $config['vhostenabled'];
        }

        $server->setBaseUri($basepath);

        // Add the Browser Plugin
        if ($config['pluginbrowser']) {
            $server->addPlugin(
                new \Sabre\DAV\Browser\Plugin()
            );
        }

        // Add the DavMount Plugin
        if ($config['pluginmount']) {
            $server->addPlugin(
                new \Sabre\DAV\Mount\Plugin()
            );
        }

    // Support for LOCK and UNLOCK
        if ($config['lockmanager']) {
            $canAddLockMngr = true;

            if ($canAddLockMngr) {
                $server->addPlugin(
                    new \Sabre\DAV\Locks\Plugin(
                        new \Sabre\DAV\Locks\Backend\File(TMP.'/locks.db')
                    )
                );
            }
        }

        // Temporary File Filter Plugin
        if ($config['plugintempfilefilter']) {
            $canAddTMPFilter = true;

            if ($canAddTMPFilter) {
                $server->addPlugin(
                    new \Sabre\DAV\TemporaryFileFilterPlugin()
                );
            }
        }

        // Finally, handle the request
        $server->exec();
    }
}
