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
use Smarty_Security;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class SmartySecurity extends Smarty_Security
{
    public $php_handling    = BaseSmarty::PHP_REMOVE;
    public $static_classes  = 'none';
    public $streams         = null;
    public $allow_constants = false;
     // allow everthing as modifier
    public $modifiers = [];

    public function isTrustedResourceDir($filepath)
    {
        return true;
    }
}
