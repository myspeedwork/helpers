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

use Smarty as BaseSmarty;
use Speedwork\Config\Configure;
use Speedwork\Core\Helper;
use Speedwork\Util\Router as BaseRouter;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Smarty extends Helper
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

    public function modifierDashed($string)
    {
        $string = strip_tags($string);
        $string = preg_replace('/[^\da-z]/i', '-', $string);

        return $string;
    }

    public function modifierTodate($time, $format = null)
    {
        if (empty($time) || $time == '0000-00-00') {
            return;
        }

        if (!is_numeric($time)) {
            $parts = explode('/', $time);

            if ($parts[0] && strlen($parts[0]) != 4) {
                $time = str_replace('/', '-', trim($time));
            }
            $time = strtotime($time);
        }

        if (empty($format)) {
            $format = 'M d, Y h:i A';
        }

        return date($format, $time);
    }

    public function modifierStatus($status, $type = null)
    {
        if ($type == 1) {
            $data = '<a data-status="1"';
            if ($status) {
                $data .= 'style="display:none"';
            }
            $data .= '>';
            $data .= '<i class="fa fa-ban fa-lg" role="tooltip" title="Click to Approve"></i></a>';

            $data .= '<a data-status="0"';
            if ($status == 0) {
                $data .= 'style="display:none"';
            }
            $data .= '>';
            $data .= '<i class="fa fa-check fa-lg" role="tooltip" title="Click to unapprove"></i></a>';

            return $data;
        }

        if ($status == 1) {
            return '<a><i class="fa fa-lg fa-check" role="tooltip" title="Active"></i></a>';
        }

        if ($status == 9) {
            return '<a><i class="fa fa-lg fa-trash-o" role="tooltip" title="Deleted"></i></a>';
        }

        return '<a><i class="fa fa-lg fa-ban" role="tooltip" title="InActive"></i></a>';
    }

    /*
     &* check is valid function to execute
    */
    public function execute($params)
    {
        $allowed = [
            't',
            'trans',
            'link',
            'layout',
            'request',
            'template',
            'countModules',
            'config',
            'render',
        ];
        $first = key($params);

        if ($first && in_array($first, $allowed)) {
            if ($first == 't') {
                $first = 'trans';
            }

            return $this->$first($params);
        }
    }

    /*
     * Template engine parse link
     * $allowed vars : link
     */
    public function link(&$params)
    {
        return Baserouter::link($params['link']);
    }

    /*
     * Count no. of modules in position
     * {code}
     * {assign var="left_count" value="{speed countModules="right"}"}
     * {/code}
     */
    public function countModules(&$params)
    {
        $position = $params['countModules'];

        return $this->get('resolver')->countModules($position);
    }
    /*
     * get the configuration options
    */
    public function config(&$params)
    {
        return Configure::read($params['config']);
    }

    /*
     * To run functions in template class
     * {code}
     * {speed template="script" params="showcase.js"}
     * {speed template="setMetaData" params="viewport','320"}
     * {/code}
     */
    public function template(&$params)
    {
        $function = $params['template'];
        $template = $this->get('template');
        unset($params['template']);
        call_user_func_array([$template, $function], $params);
    }

    /*
     * Template engine to include layout
     * $allowed vars : layout,name,type:module,component
     * {code}
     * {speed layout="layout_list" name="books"}
     * {/code}
     */
    public function layout(&$params)
    {
        $layout = $params['layout'];
        $type   = $params['type'];
        $name   = $params['name'];

        return $this->get('resolver')->requestLayout($name, $layout, $type);
    }

    /*
     * Template engine to parse language
     * $allowed vars : t,r:replace string
     * {code}
     * {speed t="myname"}
     * {speed t="my name is %s" r="sankar"}
     * {speed t="my name is %s. email is %s" r="sankar','sankar.suda@gmail.com"}
     * {/code}
     */
    public function trans(&$params)
    {
        $t = $params['t'];
        unset($params['t']);

        return $t;
    }

    /*
     * Template engine to render modules, components
     * $allowed vars :
     * {code}
     * {speed request=module|component}
     * {/code}
     */
    public function request(&$params)
    {
        $type = $params['type'];
        $app  = $this->get('resolver');

        if (empty($params['view'])) {
            $option           = explode('.', $params['request']);
            $params['view']   = $option[1];
            $params['option'] = $option[0];
        } else {
            $params['option'] = $params['request'];
        }

        if ($type == 'module') {
            echo $app->module($params['option'], $params['view'], $params);
        } else {
            echo $app->component($params['option'], $params['view'], $params);
        }
    }

    public function render(&$params = [])
    {
        return $this->get('template')->getBuffer($params['render'], $params['name'], $params);
    }
}
