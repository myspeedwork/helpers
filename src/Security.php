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
 * @author Sankar <sankar.suda@gmail.com>
 */
class Security extends Helper
{
    public function isValidToken($token)
    {
        $original = $this->get('session')->get('token');

        if (empty($original)) {
            return true;
        }

        if (strcmp($token, $original) === 0) {
            return true;
        }

        return [
            'status'  => 'ERROR',
            'message' => 'Invalid token. Please try again reloading the page.',
        ];
    }
}
