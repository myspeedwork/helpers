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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Encryption
{
    public function encrypt($value)
    {
        $value = base64_encode($value);

        return rtrim($value, '=');
    }

    public function decrypt($value)
    {
        $value = $value.str_repeat('=', strlen($value) % 4);

        return base64_decode($value);
    }
}
