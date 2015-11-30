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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Access extends Helper
{
    public function getPermissions($userid)
    {
        return $this->acl->getPermissions($userid);
    }

    public function hasAccess($key, $permissions)
    {
        $return = true;

        // get group permissions
        if (is_array($permissions['group'])) {
            $return = $this->isAllowed($key, $permissions['group']);
        }

        // Global include permissions
        if (!$return && is_array($permissions['include'])) {
            if ($this->isAllowed($key, $permissions['include'])) {
                $return = true;
            }
        }

        // Global exclude permissions
        if ($return && is_array($permissions['exclude'])) {
            if ($this->isAllowed($key, $permissions['exclude'])) {
                $return = false;
            }
        }

        // User include permissions
        if (!$return && is_array($permissions['user_include'])) {
            if ($this->isAllowed($key, $permissions['user_include'])) {
                $return = true;
            }
        }

        // User exclude permissions
        if ($return && is_array($permissions['user_exclude'])) {
            if ($this->isAllowed($key, $permissions['user_exclude'])) {
                $return = false;
            }
        }

        return $return;
    }

    public function isAllowed($permission, $permissions = [])
    {
        $permission = strtolower(trim($permission));

        foreach ($permissions as $value) {
            $value = strtolower(trim($value));

            if ($this->matchPermission($value, $permission)) {
                return true;
            }
        }

        return false;
    }

    public function matchPermission($permission, $key)
    {
        $key = (!is_array($key)) ? explode(':', $key) : $key;

        list($component, $view, $task) = $key;

        $return = true;
        if ($permission == '*') {
            return $return; // Super Admin Bypass found
        }

        if ($permission == $component.':**') {
            return $return; // Component Wide Bypass with tasks
        }

        if (!$task && $permission == $component.':*') {
            return $return; // Component Wide Bypass without tasks found
        }

        if (!$view && !$task && $permission == $component.':') {
            return $return; // Component Wide Bypass found
        }

        if (!$task && $permission == $component.':'.$view) {
            return $return; // Specific view without any task like view
        }

        if ($task && $permission == $component.':'.$view.':'.$task) {
            return $return; // Specific view with perticular task found
        }

        if ($task && $permission == $component.':*:'.$task) {
            return $return; // Any view with perticular task found
        }

        if ($task && $permission == $component.':*:*') {
            return $return; // Any view and task
        }

        if ($permission == $component.':'.$view.':*') {
            return $return; // Specific view with all tasks permission found
        }

        return false;
    }

    public function getComponent($link)
    {
        if (empty($link)) {
            return [];
        }

        $parse = parse_url($link);

        if ($parse['query']) {
            parse_str($parse['query'], $parse);
            $option = $parse['option'];
            $view   = $parse['view'];
            $task   = $parse['task'];

            return [$option, $view, $task];
        }

        return [];
    }
}
