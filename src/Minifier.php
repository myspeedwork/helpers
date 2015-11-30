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
class Minifier
{
    public function cdnify($files)
    {
        $cdn = STATICD.'cdn.json';
        if (file_exists($cdn)) {
            $cdn  = file_get_contents($cdn);
            $cdns = json_decode($cdn, true);
            if (is_array($cdns)) {
                $cnds_files = [];
                foreach ($files as $file => $attr) {
                    $nfile = str_replace(_STATIC, '', $file);
                    if (!empty($cdns[$nfile])) {
                        $file        = $cdns[$nfile];
                        $attr['cdn'] = true;
                    }

                    $cnds_files[$file] = $attr;
                }

                return $cnds_files;
            }
        }

        return $files;
    }

    public function minify($files = [])
    {
        $list = [];
        foreach ($files as $src => $attr) {
            $ext             = strtolower(strrchr($src, '.'));
            $ext             = explode('?', $ext);
            $list[$ext[0]][] = $src;
        }

        $urls = [];
        foreach ($list as $ext => $files) {
            // Send Etag hash
            $hash = md5(implode(',', $files));
           // Try the cache first to see if the minifyd files were already generated
            $cachefile = 'cache-'.$hash.$ext;
            $cacheurl  = _STATIC.$cachefile;
            $dir       = STATICD.$cachefile;

            if (!file_exists($dir)) {
                $content = '';
                foreach ($files as $file) {
                    //if files are css then compress and replace urls
                    if ($ext == '.css') {
                        $cachec = file_get_contents($file);
                        $content .= $this->absolute($cachec, $file);
                        //$content .= $this->compress($cachec);
                    } else {
                        $content .= file_get_contents($file);
                    }
                    $content .= ';';
                }

                $fp = fopen($dir, 'wb');
                fwrite($fp, $content);
                fclose($fp);
            }

            $urls[$cacheurl] = [];
        }

        return $urls;
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
        preg_match_all("/(url\((\"|'|)(.*\.(png|gif|jpg|jpeg|eot|ttf|svg|woff)([^\"|'].*)?)(\"|'|)\))/Ui", $content, $matches);

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

                if (substr($file, 0, 1) != '/' && substr($file, 0, 5) != 'http:'  && substr($file, 0, 6) != 'https:') {
                    $absolute_path = $url.ltrim($file, '.');
                    $content       = str_replace($find, 'url('.$absolute_path.')', $content);
                }
            }
        }

        return $content;
    }
}
