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

use Speedwork\Core\Helper;

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
