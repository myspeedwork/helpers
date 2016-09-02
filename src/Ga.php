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
class Ga extends Helper
{
    public $enable = true;

    public function add($account)
    {
        if (!$this->enable) {
            return false;
        }

        $header = "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');";

        $header .= "ga('create', '".$account."', 'auto');ga('send', 'pageview');";

        $this->template->addScriptDeclaration($header);
    }

    public function eventPush($category, $action, $label, $value = 1)
    {
        if (!$this->enable) {
            return false;
        }

        $code = 'if (typeof _gaq != \'undefined\') {
                    _gaq.push(["_trackEvent", "'.$category.'","'.$action.'","'.$label.'","'.$value.'"]);
                }';
        $this->template->addScriptDeclaration($code);
    }

    public function eventAdd($action, $label, $value)
    {
        if (!$this->enable) {
            return false;
        }
        $code = 'if (typeof _gaq != \'undefined\') {
                    _gaq.push(["_trackEvent","'.$action.'","'.$label.'","'.$value.'"]);
                }';
        $this->template->addScriptDeclaration($code);
    }
}
