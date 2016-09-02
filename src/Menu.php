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
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Menu extends Helper
{
    public function buildMenu($parent, $menuData, $optional = [], $ul = true, $nohideParent = true)
    {
        $html = '';
        $hide = ($optional['hideChild'] && !$nohideParent) ? true : false;

        if (isset($menuData['parents'][$parent])) {
            $html .= ($ul) ? '<ul '.($hide ? ' style="display:none"' : 'class="nav navbar-nav navbar-left"').'>' : '';
            $i     = 1;
            $total = count($menuData['parents'][$parent]);

            foreach ($menuData['parents'][$parent] as $itemId) {
                $data = $menuData['items'][$itemId];

                $property = $this->createMenu($data);

                $access = $this->hasPermission($property['link']);

                if (!isset($menuData['parents'][$itemId])) {
                    if (!$access) {
                        continue;
                    }

                    $html .= '<li '.$property['lattr'].'>';
                    $html .= '<a '.$property['attr'].'><i '.$property['iattr'].'></i><span>'.$property['name'].'</span></a>';

                    $html .= ($ul !== true && $i == 1) ? '<ul '.($hide ? ' style="display:none"' : '').'>' : '';

                    if ($ul == false && $i == $total) {
                        $html .= '</ul></li>';
                    }
                }

                if (isset($menuData['parents'][$itemId])) {
                    if ($access) {
                        $html .= "<li data-tag='b'".$property['lattr'].' '.(($access) ? '' : 'no-permisson').'>';
                        $html .= '<a '.$property['attr'].'><i '.$property['iattr'].'></i><span>'.$property['name'].'</span></a>';
                    }

                    $html .= self::buildMenu($itemId, $menuData, $optional, $access, false);

                    if ($access) {
                        $html .= '</li>';
                    }
                }
                ++$i;
            }

            $html .= ($ul) ? '</ul>' : '';
        }

        return $html;
    }

    public function display($menu_type)
    {
        $menuData = $this->getMenuDetails($menu_type);

        return $this->build(0, $menuData);
    }

    protected function build($parent = 0, $rows = [])
    {
        $html = [];

        if (isset($rows['parents'][$parent])) {
            foreach ($rows['parents'][$parent] as $id) {
                $data = $rows['items'][$id];

                $property = $this->createMenu($data, true);

                $access = $this->hasPermission($property['link']);

                if (!isset($rows['parents'][$id])) {
                    if (!$access) {
                        continue;
                    }

                    $html[$id] = $property;
                }

                if (isset($rows['parents'][$id])) {
                    if ($access) {
                        $html[$id] = $property;
                    }

                    $html[$id]['childs'] = $this->build($id, $rows);
                }
            }
        }

        return $html;
    }

    public function createMenu($data, $detail = false)
    {
        $property = [];

        $link         = ($data['link']) ? $data['link'] : '#';
        $attributes   = (array) json_decode($data['attributes'], true);
        $data['slug'] = preg_replace('/[^\da-z]/i', '-', strtolower($data['name']));

        $link = str_replace('{id}', $data['menu_id'], $link);
        $link = str_replace('{name}', $data['slug'], $link);

        //replace url
        $matches = [];
        preg_match_all('~\{([^{}]+)\}~', $link, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $k    = $match[0];
            $v    = $data[$match[1]];
            $link = str_replace($k, $v, $link);
        }

        if ($link && $link != '#') {
            $url = $link;
        } else {
            $url = $attributes['link'];
        }

        if ($link != '#') {
            $link = $this->link($link);
        }

        $name = null;
        if ($attributes['name'] != 'none') {
            $name    = $data['name'];
            $matches = [];

            preg_match_all('~\{([^{}]+)\}~', $name, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $k    = $match[0];
                $v    = $this->get($match[1]);
                $name = str_replace($k, $v, $name);
            }
        }

        $liattr = [];
        foreach ($attributes as $k => $v) {
            if (strpos($k, 'l:') !== false) {
                unset($attributes[$k]);
                $k          = trim($k, 'l:');
                $liattr[$k] = $v;
            }
        }

        $iattr = [];
        foreach ($attributes as $k => $v) {
            if (strpos($k, 'i:') !== false) {
                unset($attributes[$k]);
                $k         = trim($k, 'i:');
                $iattr[$k] = $v;
            }
        }

        if (!$detail) {
            $attrib = ['href' => $link];

            $attributes          = array_merge($attrib, $attributes);
            $attributes['title'] = Utility::specialchars($name);

            $property['lattr'] = Utility::parseAttributes($liattr);
            $property['attr']  = Utility::parseAttributes($attributes);
        }

        $property['iattr'] = Utility::parseAttributes($iattr);
        $property['link']  = ($attributes['link']) ? $attributes['link'] : $url;
        $property['name']  = $name;
        $property['url']   = $link;

        return $property;
    }

    private function getMenuDetails($menu_type, $parents = false)
    {
        if ($menu_type && !is_array($menu_type)) {
            $menu_type = explode(',', $menu_type);
        }

        $conditions   = [];
        $conditions[] = (!$this->is_user_logged_in) ? ['access <>' => 1] : ['access <>' => 2];
        $conditions[] = ($parents) ? ['parent_id' => 0] : '';

        if ($menu_type && count($menu_type) > 0) {
            $conditions[] = ['menu_type' => $menu_type];
        }

        $conditions[] = ['status' => 1];

        $result = $this->database->find('#__core_menu', 'all', [
            'conditions' => $conditions,
            'order'      => ['parent_id', 'ordering'],
            'cache'      => 'daily',
        ]);

        // Create a multidimensional array to conatin a list of items and parents
        $menuData = [
            'items'   => [],
            'parents' => [],
        ];
        // Builds the array lists with data from the menu table
        foreach ($result as $menuItem) {
            // Creates entry into items array with current menu item id ie. $menuData['items'][1]
            $menuData['items'][$menuItem['menu_id']] = $menuItem;
            // Creates entry into parents array. Parents array contains a list of all items with children
            $menuData['parents'][$menuItem['parent_id']][] = $menuItem['menu_id'];
        }

        return $menuData;
    }

    public function displayMenu($menu_type, $parents = false, $hideChild = true)
    {
        $menuData = $this->getMenuDetails($menu_type, $parents);

        $options = [];

        $options['hideChild'] = $hideChild;

        return self::buildMenu(0, $menuData, $options, true);
    }

    // Menu builder function, parentId 0 is the root
    public function buildMenuSelect($parent, $menuData, $selected, $select_parents, $type, $level = 0)
    {
        $sel = explode(',', $selected);

        $html = '';
        if (isset($menuData['parents'][$parent])) {
            $html .= '<ul>';
            foreach ($menuData['parents'][$parent] as $itemId) {
                $input = true;

                $data       = $menuData['items'][$itemId];
                $name       = $data['name'];
                $input_type = ($type == 0) ? 'checkbox' : 'radio';

                $template = '';

                if ($input) {
                    $se       = (in_array($data['menu_id'], $sel)) ? 'checked="checked"' : '';
                    $template = '<input type="'.$input_type.'" name="category[]"
                        class="liChild_'.$data['menu_id'].'  liParent_'.$data['parent_id'].'"
                        '.(($select_parents) ? ' onclick="checkparent('.$data['parent_id'].')"' : '').'
                        value="'.$data['menu_id'].'" '.$se.'/>';
                } else {
                    $template = '<input type="'.$input_type.'" disabled="disabled" />';
                }

                if (!isset($menuData['parents'][$itemId])) {
                    $html .= '<li><label>';
                    $html .= $template.$name.'</label>';
                    $html .= '</li>';
                }
                if (isset($menuData['parents'][$itemId])) {
                    ++$level;

                    $html .= '<li><label for="'.$level.'">';
                    $html .= $template.$name.'</label>';
                    $html .= self::buildMenuSelect($itemId, $menuData, $selected, $select_parents, $type, $level);
                    $html .= '</li>';
                }
            }
            $html .= '</ul>';
        }

        return $html;
    }

    public function menuSelect($menu_type, $only_parents = false, $selected = '', $select_parents = true, $type = 0, $all = false)
    {
        if ($menu_type) {
            $menu_type = explode(',', $menu_type);
        }
        $cond = [];

        if ($all === false) {
            $cond[] = (!$this->is_user_logged_in) ? ['access <>' => 1] : ['access <>' => 2];
            $cond[] = ($only_parents) ? ['parent_id' => 0] : '';
            $cond[] = ['status' => 1];
        }

        if ($menu_type && count($menu_type) > 0) {
            $cond[] = ['menu_type' => $menu_type];
        }

        $result = $this->database->find('#__core_menu', 'all', [
            'conditions' => $cond,
            'order'      => ['parent_id', 'ordering'],
            'cache'      => 'daily',
        ]);

        // Create a multidimensional array to conatin a list of items and parents
        $menuData = [
            'items'   => [],
            'parents' => [],
        ];
        // Builds the array lists with data from the menu table
        foreach ($result as $menuItem) {
            // Creates entry into items array with current menu item id ie. $menuData['items'][1]
            $menuData['items'][$menuItem['menu_id']] = $menuItem;
            // Creates entry into parents array. Parents array contains a list of all items with children
            $menuData['parents'][$menuItem['parent_id']][] = $menuItem['menu_id'];
        }

        $menu = self::buildMenuSelect(0, $menuData, $selected, $select_parents, $type);

        return $menu;
    }

    public function menuIDToName($id, $implode = ',')
    {
        $res      = $this->database->find('#__core_menu', 'all', ['conditions' => ['menu_id' => $id], 'fields' => ['name']]);
        $menuName = [];
        foreach ($res as $data) {
            $menuName[] = $data['name'];
        }

        return implode($implode, $menuName);
    }

    public function menuIDToNames($id)
    {
        if (!$id) {
            return [];
        }

        $res = $this->database->find('#__core_menu', 'all', [
            'conditions' => ['menu_id' => $id],
            'fields'     => ['name', 'link', 'menu_id', 'menu_type'],
            'order'      => ['parent_id ASC', 'ordering'],
        ]);

        $menu = [];
        foreach ($res as $data) {
            $link   = $this->parselink($data);
            $menu[] = ['name' => $data['name'], 'link' => $link];
        }

        return $menu;
    }

    public function menuId($cats = '')
    {
        $categoriesurl = [];
        if ($cats) {
            $menus = $this->database->find('#__core_menu', 'all', [
                'fields'     => ['menu_id', 'name', 'parent_id', 'link'],
                'order'      => ['parent_id'],
                'conditions' => ['menu_id' => $cats],
            ]);

            $menuData = [
                'items'   => [],
                'parents' => [],
            ];
            foreach ($menus as $menu) {
                $menuData['items'][$menu['menu_id']]       = $menu;
                $menuData['parents'][$menu['parent_id']][] = $menu['menu_id'];
            }

            $categoriesurl = $this->renderCategoires($menuData);
        }

        return $categoriesurl;
    }

    public function renderCategoires($menuData = [], $parent = 0)
    {
        $categories = [];

        if (isset($menuData['parents'][$parent])) {
            foreach ($menuData['parents'][$parent] as $menuid) {
                $data = $menuData['items'][$menuid];

                $link = $this->parselink($data);
                if (!$this->hasPermission($link)) {
                    continue;
                }

                if (!isset($menuData['parents'][$menuid])) {
                    $categories[] = '<a href="'.$link.'">'.$data['name'].'</a>';
                }
                if (isset($menuData['parents'][$menuid])) {
                    $categories[] = '<a href="'.$link.'">'.$data['name'].'</a>';
                    $ca           = $this->renderCategoires($menuData, $menuid);
                    $categories   = array_merge($categories, $ca);
                }

                if ($parent == 0) {
                    break;
                }
            }
        }

        return    $categories;
    }

    public function menuIDToParents($id, $implode = ' &raquo; ')
    {
        if (!$id) {
            return [];
        }
        $row = $this->database->find('#__core_menu', 'first', ['conditions' => ['menu_id' => $id], 'fields' => ['name', 'parent_id']]);
        $m[] = $row['name'];
        $pid = $row['parent_id'];

        if ($pid != 0) {
            $m[] = self::menuIDToParents($pid);
        }

        return implode($implode, array_reverse($m));
    }

    public function getParentLinks($id)
    {
        if (!$id) {
            return [];
        }

        $res = $this->database->find('#__core_menu', 'all', [
            'conditions' => ['menu_id' => $id],
            'fields'     => ['name', 'link', 'menu_id', 'menu_type', 'parent_id'],
            'order'      => ['parent_id DESC', 'ordering'],
        ]);

        $menu = [];
        foreach ($res as $data) {
            $pid    = $data['parent_id'];
            $link   = $this->parselink($data, false);
            $menu[] = ['name' => $data['name'], 'link' => $link];

            if ($pid != 0) {
                $menu = array_merge($menu, $this->getParentLinks($pid));
            }
        }

        return $menu;
    }

    public function hasPermission($link)
    {
        if (empty($link)) {
            return true;
        }
        //check has permission to access this mneu
        // if not has permission don't render the menu
        $parse = parse_url($link);

        if ($parse['query']) {
            parse_str($parse['query'], $parse);
            $option = $parse['option'];
            $view   = $parse['view'];
            $task   = $parse['task'];

            if ($option && !$this->acl->isAllowed($option, $view, $task)) {
                return false;
            }
        }

        return true;
    }

    public function parselink($data, $linked = true)
    {
        $link    = ($data['link']) ? $data['link'] : '#';
        $matches = [];
        preg_match_all('~\{([^{}]+)\}~', $link, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $k    = $match[0];
            $v    = $data[$match[1]];
            $link = str_replace($k, $v, $link);
        }
        if ($linked == true) {
            return $this->link($link);
        } else {
            return $link;
        }
    }
}
