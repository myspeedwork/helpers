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
use Speedwork\Core\Router as BaseRouter;
use Speedwork\Util\Str;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Router extends Helper
{
    public $_config = [];
    public $routes  = [];
    public $ssl     = [];
    public $short   = false;
    public $router  = false;
    public $seo     = false;
    public $domains = null;

    public function index()
    {
        BaseRouter::addRewrite($this);

        $link  = [];
        $route = $this->get['route'];
        $short = $this->get['short'];

        if ($short) {
            $router = $this->get('resolver')->helper('router.txtly');
            $res    = $router->route($short);
            if ($res === true) {
                return true;
            }

            $link['url'] = $res;
        }

        $config       = $this->config('router');
        $this->short  = $config['short']['enable'];
        $this->router = $config['router']['enable'];
        $this->seo    = $config['seo']['enable'];

        if ($this->short) {
            $this->_config = $config['short']['config'];
        }

        if ($this->router) {
            $this->routes = $config['router']['routes'];
        }

        if (($route == 'index.html' || $route == 'index.php'
            || empty($route)) && empty($link)
        ) {
            return false;
        }

        $link = $this->generateUrl($route, $link);
        if (empty($link)) {
            return false;
        }

        return $this->processUrl($link);
    }

    /**
     * Route the link based on error code and url.
     *
     * @param array $link Url and error ocode
     *
     * @return array Parsed url options
     */
    protected function processUrl($link = [])
    {
        $type = $link['type'];
        $url  = $link['url'];

        if (empty($url)) {
            $url = 'index.php?option=errors';
        }

        if ($type == '301') {
            //Permanent (301)
            header('HTTP/1.1 301 Moved Permanently');
            header('Location:'.$url);

            return true;
        }

        if ($type == '302') {
            header('Location: '.$url);

            return true;
        }

        $values = parse_url($url, PHP_URL_QUERY);
        parse_str($values, $values);

        foreach ($values as $key => $val) {
            $_REQUEST[$key]   = $val;
            $_GET[$key]       = $val;
            $this->data[$key] = $val;
            $this->get[$key]  = $val;
        }

        return $values;
    }

    protected function generateUrl($route, $link = [])
    {
        if (empty($link['url']) && $this->router) {
            $link = ['url' => $this->route($route)];
        }

        if (empty($link['url']) && $this->short) {
            $link = $this->getUrl($route);
        }

        if (empty($link['url']) && $this->seo) {
            $link = ['url' => $this->getSeoNormal($route)];
        }

        return $link;
    }

    public function rewrite($link, $url)
    {
        $parts  = $this->parseQuery($link);
        $option = $parts['option'];
        $view   = $parts['view'];

        if (empty($option)) {
            return $url.$link;
        }

        $k   = $option.':'.$view;
        $uri = null;

        if ($this->short) {
            //for short
            $key    = $this->_config[$k];
            $uniqid = $key['uniqid'];
            if ($uniqid == '') {
                $key    = $this->_config[$option.':*'];
                $uniqid = $key['uniqid'];
            }

            if ($uniqid) {
                $id       = $parts[$uniqid];
                $id       = ($id == 'none') ? '' : $id;
                $shorturl = $this->setUrl(['option' => $k, 'uniqid' => $id]);

                if ($shorturl) {
                    $uri = $shorturl;
                }
            }
        }

        //if not found short url check router
        if ($this->router && empty($uri)) {
            $uri = $this->setRouter($link);
        }

        if ($this->seo && empty($uri)) {
            $uri = $this->setSeoNormal($parts);
        }

        if (!empty($uri)) {
            $link = $uri;
        }

        $matches = [
            $option.':'.$view,
            $option.':',
            $option.':*',
            '*',
        ];

        if (!preg_match('/(http|https):\/\//', $link)
            && substr($link, 0, 2) != '//'
        ) {
            // Domain change requests
            $url = $this->forwardDomain($matches, $url);
        }

        $link = $url.$link;
        //check is component is required ssl
        if ($this->ssl[$k] || $this->ssl[$option.':*']) {
            $link = str_replace('http://', 'https://', $link);
        }

        return $link;
    }

    protected function forwardDomain($matches, $url)
    {
        $checked = false;
        if ($this->domains === null) {
            $siteid  = $this->config('app.siteid');
            $forward = $this->config('router.forward');

            if ($forward['enable']
                && (empty($forward['site'])
                || in_array($siteid, $forward['site']))
            ) {
                $this->domains = $forward['config'];
            }
        }

        if (is_array($this->domains)) {
            foreach ($this->domains as $u => $domain) {
                foreach ($matches as $match) {
                    if ($domain[$match]) {
                        $url     = $u;
                        $checked = true;
                        break;
                    }
                }

                if ($checked) {
                    break;
                }
            }
        }

        return $url;
    }

    public function setUrl($options = [])
    {
        $option = $options['option'];
        $uniqid = $options['uniqid'];

        if (!$option) {
            return false;
        }

        $data = $this->database->find('#__addon_shorturls', 'first', [
            'fields'     => ['short_url'],
            'conditions' => [
                'status'    => 1,
                'component' => $option,
                'uniqid'    => $uniqid,
            ],
        ]);

        return $data['short_url'];
    }

    public function getUrl($shorturl)
    {
        if (!$shorturl) {
            return false;
        }

        $data = $this->database->find('#__addon_shorturls', 'first', [
            'fields'     => ['original_url', 'redirect'],
            'conditions' => ['OR' => ['short_url' => $shorturl, 'id' => $shorturl], 'status' => 1],
        ]);

        return ['url' => $data['original_url'], 'type' => $data['redirect']];
    }

    public function save($save = [], $conditions = [])
    {
        //sanitize short url
        $parts             = $this->parseQuery($save['original_url']);
        $save['component'] = $parts['component'];
        $save['view']      = $parts['view'];

        $save['component'] = str_replace('com_', '', $save['component']);

        if (empty($save['uniqid'])) {
            //get config
            $conf   = $this->config('router.short.generate');
            $k      = $save['component'].':'.$save['view'];
            $key    = $conf[$k];
            $uniqid = $key['uniqid'];
            if ($uniqid == '') {
                $key    = $conf[$save['component'].':*'];
                $uniqid = $key['uniqid'];
            }
            $save['uniqid'] = $parts[$uniqid];
        }

        if (empty($save['component']) || empty($save['short_url'])) {
            return false;
        }

        $save['short_url'] = Str::slug($save['short_url']);

        self::checkShortUrl($save);

        $id = $save['id'];
        unset($save['id']);

        $save['component'] = $save['option'].':'.$save['view'];
        unset($save['view']);

        if (count($conditions) > 0) {
            $conditions['component'] = $save['component'];

            $row = $this->database->find('#__addon_shorturls', 'first', [
                'conditions' => $conditions,
            ]);

            $id = $row['id'];
        }

        if ($id) {
            return $this->database->update('#__addon_shorturls', $save, ['id' => $id]);
        } else {
            $save['created'] = time();

            return $this->database->save('#__addon_shorturls', $save);
        }
    }

    public function isKeyExists($save = [])
    {
        $row = $this->database->find('#__addon_shorturls', 'first', [
            'conditions' => ['short_url' => $save['shorturl']],
        ]);

        if (!isset($row['id']) || $save['id'] == $row['id']) {
            return false;
        }

        return true;
    }

    public function checkShortUrl($save = [])
    {
        if ($this->isKeyExists($save)) {
            $shorturl          = $save['short_url'];
            $lastdigit         = strrchr($shorturl, '-');
            $lastdigit         = (int) trim($lastdigit, '-');
            $adddigit          = $lastdigit + 1;
            $shorturl          = $shorturl.'-'.$adddigit;
            $save['short_url'] = $shorturl;

            return $this->checkShortUrl($save);
        }

        return $save;
    }

    /* SEO BASED ON ROUTER*/
    public function route($uri)
    {
        // Is there a literal match?  If so we're done
        if (isset($this->routes[$uri])) {
            return $this->routes[$uri];
        }
        // Loop through the route array looking for wild-cards
        foreach ($this->routes as $key => $val) {
            // Convert wild-cards to RegEx
            $key = str_replace([':any', ':num'], ['.+', '[0-9]+'], $key);
            // Does the RegEx match?
            if (preg_match('#^'.$key.'$#', $uri)) {
                // Do we have a back-reference?
                if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                    $val = preg_replace('#^'.$key.'$#', $val, $uri);
                }

                return $val;
            }
        }

        return false;
    }

    public function setRouter($uri)
    {
        // $uri is expected to be a string, in the form of index.php?option=com&view=v
        // trim leading and trailing slashes, just in case
        $uri = trim($uri, '/');

        // Loop through all routes to check for back-references, then see if the user-supplied URI matches one
        foreach ($this->routes as $key => $val) {
            // Do we have a back-reference?
            if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                // Find all back-references in custom route and CI route
                preg_match_all('/\(.+?\)/', $key, $keyRefs);
                preg_match_all('/\$.+?/', $val, $valRefs);
                $keyRefs = $keyRefs[0];

                // Create URI Regex, to test passed-in uri against a custom route's CI ( standard ) route
                $uriRegex = $val;

                // Extract positional parameters (backreferences), and order them such that
                // the keys of $goodValRefs dirrectly mirror the correct value in $keyRefs
                $goodValRefs = [];
                foreach ($valRefs[0] as $ref) {
                    $tempKey = substr($ref, 1);
                    if (is_numeric($tempKey)) {
                        --$tempKey;
                        $goodValRefs[$tempKey] = $ref;
                    }
                }

                //quote and replace
                $uriRegex = preg_quote($uriRegex);
                $uriRegex = preg_replace('/\\\\(\\$[0-9]+)/', '$1', $uriRegex);

                // Replaces back-references in CI route with custom route's regex [ $1 replaced with (:num), for example ]
                foreach ($goodValRefs as $tempKey => $ref) {
                    if (isset($keyRefs[$tempKey])) {
                        $uriRegex = str_replace($ref, $keyRefs[$tempKey], $uriRegex);
                    }
                }

                // replace :any and :num with .+ and [0-9]+, respectively
                $uriRegex = str_replace([':any', ':num'], ['.+', '[0-9]+'], $uriRegex);

                // regex creation is finished.  Test it against uri
                if (preg_match('#^'.$uriRegex.'$#', $uri)) {
                    // A match was found.  We can now build the custom URI
                    // We need to create a custom route back-referenced regex, to plug user's uri params into the new routed uri.
                    // First, find all custom route strings between capture groups
                    $key = str_replace(':any', '.+', str_replace(':num', '[0-9]+', $key));

                    $routeString = preg_split('/\(.+?\)/', $key);

                    // build regex using original CI route's back-references
                    $replacement = '';
                    $rsEnd       = count($routeString) - 1;

                    // merge route strings with original back-references, 1-for-1, like a zipper
                    for ($i = 0; $i < $rsEnd; ++$i) {
                        $replacement .= $routeString[$i].$valRefs[0][$i];
                    }
                    $replacement .= $routeString[$rsEnd];

                    /*
                    At this point,our variables are defined as:
                    $uriRegex:        regex to match against user-supplied URI
                    $replacement:    custom route regex, replacing capture-groups with back-references

                    All that's left to do is create the custom URI, and return the site_url
                    */
                    return preg_replace('#^'.$uriRegex.'$#', $replacement, $uri);
                }
            } elseif ($val == $uri) {
                // If there is a literal match AND no back-references are setup, and we are done
                return $key;
            }
        }

        return false;
    }

    /* SEO NORMAL
    * replace only component and view
    */
    protected function setSeoNormal($parts)
    {
        $option = $parts['option'];
        if (empty($option)) {
            return false;
        }

        $view = $parts['view'];
        unset($parts['option'], $parts['view']);
        $q = http_build_query($parts);

        return $option.(($view) ? '/'.$view : '').(($q) ? '?'.$q : '');
    }

    protected function getSeoNormal($url)
    {
        $url  = explode('/', $url);
        $link = 'index.php?';

        if ($url[0]) {
            $link .= 'option='.$url[0];
        }

        if ($url[1]) {
            $link .= '&view='.$url[1];
        }

        return $link;
    }

    protected function parseQuery($var)
    {
        $var = parse_url($var, PHP_URL_QUERY);
        $var = explode('&', html_entity_decode($var));
        $arr = [];

        foreach ($var as $val) {
            $x          = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $var);

        return $arr;
    }
}
