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
class Router extends Helper
{
    protected $routes  = null;
    protected $ssl     = null;
    protected $short   = null;
    protected $seo     = null;
    protected $domains = null;

    /**
     * Setup the router configuration.
     */
    protected function setup()
    {
        $config = $this->config('router');

        $this->short  = $config['short']['enable'];
        $this->routes = $config['router']['enable'];
        $this->ssl    = $config['ssl']['enable'];
        $this->seo    = $config['seo'];

        if ($this->ssl) {
            $this->ssl = $config['ssl']['config'];
        }

        if ($this->short) {
            $this->short = $config['short']['config'];
        }

        if ($this->routes) {
            $this->routes = $config['router']['routes'];
        }
    }

    /**
     * Route the incoming url to process controller.
     *
     * @return array|bool
     */
    public function route($link = [])
    {
        $this->setUp();

        $route = $this->query('route');

        if (($route == 'index.html'
            || $route == 'index.php'
            || empty($route))
        ) {
            return true;
        }

        return $this->parseRoute($route, $link);
    }

    /**
     * Parse the route with different sources.
     *
     * @param string $route
     * @param array  $link
     *
     * @return array
     */
    protected function parseRoute($route, $link = [])
    {
        // Check whether it's matches to Short Url
        if (empty($link['url']) && $this->short) {
            $link = $this->getShortUrl($route);
        }

        // Check whether it matches to routes config
        if (empty($link['url']) && $this->routes) {
            $link = ['url' => $this->getRouterUrl($route)];
        }

        // Check whether it's matches to Normal seo
        if (empty($link['url']) && $this->seo) {
            $link = ['url' => $this->getSeoUrl($route)];
        }

        return $link;
    }

    /**
     * Rewrite the give link.
     *
     * @param string $link
     * @param string $url
     *
     * @return string
     */
    public function rewrite($link, $url)
    {
        $parts  = $this->parseLink($link);
        $option = $parts['option'];
        $view   = $parts['view'];

        if (empty($option)) {
            return $url.$link;
        }

        $matches = [
            $option.':'.$view,
            $option.':',
            $option.':*',
            '*',
        ];

        $uri = null;

        if (empty($uri) && $this->short) {
            $uri = $this->setShortUrl($matches, $parts);
        }

        //if not found short url check router
        if (empty($uri) && $this->routes) {
            $uri = $this->setRouterUrl($link);
        }

        if (empty($uri) && $this->seo) {
            $uri = $this->setSeoUrl($parts);
        }

        if (!empty($uri)) {
            $link = $uri;
        }

        if (!preg_match('/(http|https):\/\//', $link)
            && substr($link, 0, 2) != '//'
        ) {
            // Domain change requests
            $url = $this->forwardDomain($matches, $url);
        }

        $link = $url.$link;

        if ($this->ssl) {
            foreach ($matches as $match) {
                if ($this->ssl[$match]) {
                    $link = str_replace('http://', 'https://', $link);
                }
            }
        }

        return $link;
    }

    /**
     * Change the domain name to other domain.
     *
     * @param array  $matches
     * @param string $url
     *
     * @return string Modified Url
     */
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

    /**
     * Get the url based on configured routes.
     *
     * @param string $route Route to check
     *
     * @return string|bool
     */
    public function getRouterUrl($route)
    {
        // Is there a literal match?  If so we're done
        if (isset($this->routes[$route])) {
            return $this->routes[$route];
        }
        // Loop through the route array looking for wild-cards
        foreach ($this->routes as $key => $val) {
            // Convert wild-cards to RegEx
            $key = str_replace([':any', ':num'], ['.+', '[0-9]+'], $key);
            // Does the RegEx match?
            if (preg_match('#^'.$key.'$#', $route)) {
                // Do we have a back-reference?
                if (strpos($val, '$') !== false && strpos($key, '(') !== false) {
                    $val = preg_replace('#^'.$key.'$#', $val, $route);
                }

                return $val;
            }
        }

        return false;
    }

    /**
     * Convert speedwork url to router url.
     *
     * @param string $uri
     */
    public function setRouterUrl($uri)
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

    /**
     * Generate Seo Url from full url.
     *
     * @param array $parts
     */
    protected function setSeoUrl($parts)
    {
        $option = $parts['option'];
        if (empty($option)) {
            return false;
        }

        $view = $parts['view'];
        unset($parts['option'], $parts['view']);
        $q = http_build_query($parts);

        $url = $option.(($view) ? '/'.$view : '').(($q) ? '?'.$q : '');

        return $url;
    }

    /**
     * Generate Normal url from seo url based on route.
     *
     * @param string $route
     *
     * @return string
     */
    protected function getSeoUrl($route)
    {
        list($path, $query)  = explode('?', $route);
        list($option, $view) = explode('/', $path);

        $link = 'index.php?';

        if ($option) {
            $link .= 'option='.$option;
        }

        if ($view) {
            $link .= '&view='.$view;
        }

        if ($query) {
            $link .= '?'.$query;
        }

        return $link;
    }

    /**
     * Get the Short Url based on route.
     *
     * @param string $route
     *
     * @return bool|array
     */
    public function getShortUrl($route)
    {
        if (!$route) {
            return false;
        }

        $row = $this->get('database')->find('#__addon_shorturls', 'first', [
            'fields'     => ['original_url', 'redirect'],
            'conditions' => [
                'OR' => [
                    'short_url' => $route,
                    'id'        => $route,
                ],
                'status' => 1,
            ],
        ]);

        return [
            'url'  => $row['original_url'],
            'type' => $row['redirect'],
        ];
    }

    /**
     * Provide short url for given long url.
     *
     * @param array $matches Configuration should match
     * @param array $parts   Url parts
     */
    protected function setShortUrl($matches = [], $parts = [])
    {
        $uri = null;

        foreach ($matches as $match) {
            $key    = $this->short[$match];
            $uniqid = $key['uniqid'];

            if ($uniqid) {
                $id = $parts[$uniqid];
                $id = ($id != 'none') ?: '';

                $row = $this->get('database')->find('#__addon_shorturls', 'first', [
                    'fields'     => ['short_url'],
                    'conditions' => [
                        'status' => 1,
                        'option' => $match,
                        'uniqid' => $id,
                    ],
                ]);

                if ($row['short_url']) {
                    $uri = $row['short_url'];
                    break;
                }
            }
        }

        return $uri;
    }

    /**
     * Convert given url to query parts.
     *
     * @param string $url
     *
     * @return array Url parts
     */
    protected function parseLink($url)
    {
        $url = parse_url($url, PHP_URL_QUERY);
        $url = explode('&', html_entity_decode($url));
        $arr = [];

        foreach ($url as $val) {
            $x          = explode('=', $val);
            $arr[$x[0]] = $x[1];
        }
        unset($val, $x, $url);

        return $arr;
    }
}
