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

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Minify
{
    public $minify = true;

    public $defaultOptionsScript = [
            'tag'        => 'script',
            'type'       => 'text/javascript',
            'ext'        => 'js',
            'src'        => 'src',
            'allowed'    => ['.js'],
            'self_close' => false,
        ];

    public $defaultOptionsStyles = [
            'tag'        => 'link',
            'type'       => 'text/css',
            'ext'        => 'css',
            'src'        => 'href',
            'rel'        => 'stylesheet',
            'allowed'    => ['.css'],
            'self_close' => true,
            'minify'     => true,
        ];

    public function minifyStyles(&$content, $options = [])
    {
        $options = array_merge($this->defaultOptionsStyles, $options);

        return $this->minify($content, $options);
    }

    public function minifyScript(&$content, $options = [])
    {
        $options = array_merge($this->defaultOptionsScript, $options);

        return $this->minify($content, $options);
    }

    public function minify(&$content, $options)
    {
        if ($this->minify == false) {
            return $content;
        }

        $matches = [];
        $pattern = '!<'.$options['tag'].'[^>]+'.$options['type'].'[^>]+>(</'.$options['tag'].'>)?!is';
        preg_match_all($pattern, $content, $matches);

        $scripts = $matches[0];

        $files        = [];
        $lastmodified = 0;
        //remove empty sources
        foreach ($scripts as $key => $value) {
            preg_match('!'.$options['src'].'="(.*?)"!is', $value, $src);

            $source = $src[1];
            if (!$source) {
                unset($scripts[$key]);
                continue;
            }

            $ext = strrchr($source, '.');

            if (!in_array($ext, $options['allowed'])) {
                continue;
            }

            $lastmodified = max($lastmodified, @filemtime($source));
            $files[]      = $source;
            $content      = str_replace($value, '', $content);
        }

        if (count($files) == 0) {
            return $content;
        }

        $cachedir = TMP.'cache'.DS;

        // Send Etag hash
        $hash = $lastmodified.'-'.md5(@implode(',', $files));
       // Try the cache first to see if the minifyd files were already generated
        $cachefile = 'cache-'.$hash.'.'.$options['ext'];

        if (!file_exists($cachedir.$cachefile)) {
            $cache_content = '';

            foreach ($files as $k) {
                //if files are css then compress and replace urls
                if ($options['ext'] == 'css') {
                    $cachec = @file_get_contents($k);
                    $cachec = $this->absolute($cachec, $k);
                    $cache_content .= $this->compress($cachec);
                } else {
                    $cache_content .= @file_get_contents($k);
                }
            }

            $fp = fopen($cachedir.$cachefile, 'wb');
            fwrite($fp, $cache_content);
            fclose($fp);
        }

        $cacheurl = _PUBLIC.'tmp/cache/'.$cachefile;

        $newfile = '<'.$options['tag'].' type="'.$options['type'].'" '.$options['src'].'= "'.$cacheurl.'"';
        if ($options['rel']) {
            $newfile .= ' rel="'.$options['rel'].'"';
        }

        $newfile .= ($options['self_close']) ? ' />' : '></'.$options['tag'].'>';
        $newfile .= "\n";

        if ($options['ext'] == 'js' && $options['header'] != true) {
            $content = $newfile.$content;
        } else {
            $content = $content.$newfile;
        }

        return $content;
    }

    public function compress($text)
    {
        /* remove comments */
        $text = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $text);
        /* remove tabs, spaces, newlines, etc. */
        $text = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $text);

        return $text;
    }

    //Find background images in the CSS and convert their paths to absolute
    public function absolute($content, $path)
    {

        //preg_match_all( "/url\((.*?)\)/is",$content,$matches);
        preg_match_all("/(url\((\"|'|)(.*\.(png|gif|jpg|jpeg|eot|ttf|svg|woff))(\"|'|)\))/Ui", $content, $matches);

        if (count($matches[0]) > 0) {
            $path = explode('/', $path);
            $path = implode('/', array_slice($path, 0, -1));

            foreach ($matches[0] as $key => $find) {
                $file = $matches[3][$key];

                $file = trim($file);
                $file = preg_replace("@'|\"@", '', $file);
                $url  = '';
                if (substr($file, 0, 3) == '../') {
                    $last = strrchr($path, '/');
                    $url  = str_replace($last, '', $path);
                } else {
                    $url = $path.'/';
                }

                if (substr($file, 0, 1) != '/' && substr($file, 0, 5) != 'http:'  && substr($file, 0, 6) != 'https:') { //Not absolute
                    $absolute_path = $url.ltrim($file, '.');
                    $content       = str_replace($find, 'url('.$absolute_path.')', $content);
                }
            }
        }

        return $content;
    }
}
