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

use Smarty as BaseSmarty;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Smarty extends Engine
{
    public function init()
    {
        $smarty = new BaseSmarty();

        $smarty->enableSecurity('Speedwork\\Helpers\\SmartySecurity');
        $smarty->setTemplateDir(STORAGE);
        $smarty->setCompileDir(STORAGE.'views'.DS);
        $smarty->setConfigDir(STORAGE.'configs'.DS);
        $smarty->setCacheDir(CACHE);

        //register smarty functions
        $smarty->registerPlugin('function', 'speed', [$this, 'execute']);
        $smarty->registerPlugin('modifier', 'todate', [$this, 'modifierTodate']);
        $smarty->registerPlugin('modifier', 'status', [$this, 'modifierStatus']);
        $smarty->registerPlugin('modifier', 'dashed', [$this, 'modifierDashed']);

        return $smarty;
    }

    /*
     &* check is valid function to execute
    */
    public function execute($params)
    {
        $method = key($params);

        if ($method && in_array($method, $this->allowed)) {
            if ($method == 't') {
                $method = 'trans';
            }

            return $this->$method($params);
        }
    }
}
