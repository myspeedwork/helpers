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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Twig extends Engine
{
    public function init()
    {
        $loader = new \Twig_Loader_Filesystem('/');
        $twig   = new \Twig_Environment($loader, [
            'cache'       => STORAGE.'views'.DS,
            'debug'       => true,
            'auto_reload' => true,
            'autoescape'  => false,
        ]);

        $twig->addFilter(new \Twig_SimpleFilter('todate', [$this, 'modifierTodate']));
        $twig->addFilter(new \Twig_SimpleFilter('dashed', [$this, 'modifierDashed']));
        $twig->addFilter(new \Twig_SimpleFilter('status', [$this, 'modifierStatus']));
        $twig->addFilter(new \Twig_SimpleFilter('link', [$this, 'modifierLink']));

        $twig->addFunction(new \Twig_SimpleFunction('link', [$this, 'link']));
        $twig->addFunction(new \Twig_SimpleFunction('speed', [$this, 'execute']));

        return $twig;
    }

    /*
     * Template engine parse link
     * $allowed vars : link
     */
    public function link($url)
    {
        return Baserouter::link($url);
    }

    /*
     &* check is valid function to execute
    */
    public function execute($method, $params)
    {
        if (in_array($method, $this->allowed)) {
            if ($method == 't') {
                $method = 'trans';
            }

            return $this->$method($params);
        }
    }
}
