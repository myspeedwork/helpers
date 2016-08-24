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
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Content extends Helper
{
    public $enable = true;

    public function index($content, $break = false)
    {
        if (!$this->enable) {
            return $content;
        }

        $pattern = '#src="uploads([^"]+)"#iU';
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $key => $value) {
                $content = str_replace($value, 'src="'._PUBLIC.'uploads'.$matches[1][$key].'"', $content);
            }
        }

      //replace gallery plugin tage with gallery
      //<!-- <speed:module name="banners.slider" id="1" /> -->

        //$pattern = '#<speed:(module|component) name="([^"]+)" id="([^"]+)" \/>#iU';
        $pattern = '#<speed:(module|component) name="([^"]+)" (.*)\/>#iU';
        $matches = [];

        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[0] as $k => $v) {
                $type = $matches[1][$k];
                $m    = $matches[2][$k];
                $attr = $matches[3][$k];

                $attribs = $attr;
                $return  = '';
                if ($attr) {
                    $attr = Utility::parseXmlAttributes($attr);
                }

                $m1           = explode('.', $m);
                $name         = $m1[0];
                $attr['view'] = $m1[1];

                if ($type == 'module' && $name) {
                    $return = $this->renderModule($name, $attr);
                }
                if ($type == 'component' && $name) {
                    $return = $this->renderComponent($name, $attr);
                }

                $match   = '<!-- <speed:'.$type.' name="'.$m.'" '.trim($attribs).' /> -->';
                $content = str_replace($match, $return, $content);
            }
        }

        if ($break) {
            $pattern = '<!-- pagebreak -->';
            $content = explode($pattern, $content, 2);
            $content = $content[0];
        }

        return $content;
    }

    public function renderModule($name, $attribs = [])
    {
        return $this->get('resolver')->module($name, $attribs['view'], $attribs);
    }

    public function renderComponent($name, $attribs = [])
    {
        return $this->get('resolver')->component($name, $attribs['view'], $attribs);
    }
}
