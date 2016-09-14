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
 * Helper to check instance already running base on key.
 *
 * @since  0.0.1
 *
 * @author sankar <sankar.suda@gmail.com>
 */
class Instance
{
    public function exists($name)
    {
        $pid = TMP.$name.'.pid';
        if ($this->pidExits($pid, $name)) {
            return true;
        }

        file_put_contents($pid, getmypid());
        // remove the lock on exit (Control+C doesn't count as 'exit'?)
        register_shutdown_function(function () use ($pid) {
            if (file_exists($pid)) {
                unlink($pid);
            }
        });

        return false;
    }

    public function done($name)
    {
        $pid = TMP.$name.'.pid';
        if (file_exists($pid)) {
            unlink($pid);
        }
    }

    public function pidExits($pid, $name)
    {
        if (file_exists($pid)) {
            $id = file_get_contents($pid);
            echo '['.$name.'] running ('.$id.')...'."\n";

            return true;
        }
    }
}
