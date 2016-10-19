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
class Encryption extends Helper
{
    public function encrypt($value)
    {
        $crypt = $this->getHelper('crypt');
        $crypt->start('tripledes');

        return $crypt->encrypt($value);
    }

    public function decrypt($value)
    {
        $crypt = $this->getHelper('crypt');
        $crypt->start('tripledes');

        return trim($crypt->decrypt($value), "\0");
    }
}
