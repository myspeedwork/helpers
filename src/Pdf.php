<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stechno.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

//"ensepar/html2pdf": "1.0.*@dev"

namespace Speedwork\Helpers;

use HTML2PDF;
use Speedwork\Config\Configure;
use Speedwork\Core\Helper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Pdf extends Helper
{
    /**
     * holds the object.
     *
     * @var unknown
     */
    public $pdf;

    /**
     * pdf template.
     *
     * @var unknown
     */
    private $template = 'pdf';

    /**
     * holds the content to generate pdf.
     *
     * @var unknown
     */
    private $content = null;

    public function start($template = 'pdf', $orientation = 'P', $size = 'A4')
    {
        $this->pdf = new HTML2PDF($orientation, $size, 'en');
        $this->pdf->setDefaultFont('Arial');
        $this->template = $template;
    }

    /**
     * add content to pdf content.
     *
     * @param string $content
     */
    public function append($content = '')
    {
        $this->content .= $content;
        unset($content); // we don't need further
    }

    public function writeHTML($content = '')
    {
        $this->append($content);
    }

    /**
     * generate table formart form array.
     *
     * @param unknown $data
     * @param unknown $fields
     * @param string  $keys
     *
     * @return string
     */
    public function toTable(&$data = [], $fields = [], $keys = false)
    {
        if ($keys) {
            $fields = array_keys($data[0]);
        }

        if (array_values($fields) === $fields) {
            foreach ($fields as $k => $field) {
                $fields[$field] = $field;
                unset($fields[$k]);
            }
        }

        $content = '<table class="table table-primary"  >';
        $content .= '<thead><tr><th>';
        $content .=  @implode('</th><th>', $fields);
        $content .= '</th></tr></thead>';
        $content .= '<tbody>';

        foreach ($data as $row) {
            $out = [];
            foreach ($fields as $k => $v) {
                $out[] = $row[$k];
            }
            $content .= '<tr><td>';
            $content .=  @implode('</td><td>', $out);
            $content .= '</td></tr>';
        }
        $content .= '</tbody>';
        $content .= '</table>';
        $this->content .= $content;
        unset($data, $fields, $out, $content);
    }

    /**
     * @param $output (string) Destination where to send the document.
     *It can take one of the following values:
     *I: send the file inline to the browser .
     *D: file download with the name given by name.
     *F: save to a local server file with the name given by name.
     *S: return the document as a string (name is ignored).
     */
    public function output($name = 'default.pdf', $output = 'D')
    {
        $this->getContent();

        $this->pdf->writeHTML($this->content, isset($_GET['vuehtml']));
        $this->pdf->Output($name, $output);

        $this->content = null;
    }

    /**
     * [send description].
     *
     * @param [type] $data [description]
     *
     * @return [type] [description]
     */
    public function send($data)
    {
        $this->getContent($data);

        $this->pdf->writeHTML($this->content, isset($_GET['vuehtml']));
        $this->pdf->Output($name, 'D');

        $this->content = null;
    }
    /**
     * Public function to get the content and replace them.
     *
     * @param [type] &$tags [description]
     *
     * @return [type] [description]
     */
    public function getContent($data)
    {
        $array_content               = [];
        $array_content['sitename']   = _SITENAME;
        $array_content['siteurl']    = _URL;
        $array_content['imageurl']   = _IMG_URL.'pdf_templates/';
        $array_content['theme_logo'] = Configure::read('theme_logo');
        $tags                        = array_merge($data['tags'], $array_content);

        $tags['name'] = 'aman';
        $content      = null;

        $filename = $data['template'];

        $path = UPLOAD.'pdf_templates'.DS;

        $filename = str_replace('.html', '.tpl', $filename);
        $filename = $path.$filename;

        $theme    = Configure::read('pdf.theme');
        $theme    = ($theme) ? $theme : 'pdf.tpl';
        $template = $path.$theme;

        if (file_exists($filename)) {
            foreach ($tags as $key => $val) {
                $this->engine->assign($key, $val);
            }

            if (file_exists($template)) {
                $emailTemplate = $this->engine->fetch($template);
            }

            $filename       = str_replace('.html', '.tpl', $filename);
            $html           = $this->engine->fetch($filename);
            $return['text'] = $html;

            //key for backup
            $html = $this->replace($tags, $html);

            //put the content into template
            $this->content = str_replace('<!--PDFBODY-->', $html, $emailTemplate);
        }
    }

    /**
     * function to replace the variable.
     *
     * @param [type] &$vars [description]
     * @param [type] &$html [description]
     *
     * @return [type] [description]
     */
    public function replace(&$vars, &$html)
    {
        if (preg_match_all('~\{\$([^{}]+)\}~', $html, $matches) && count($matches[0]) > 0) {
            foreach ($matches[0] as $key => $match) {
                $html = str_replace($match, $this->find($matches[1][$key], $vars), $html);
            }
        }

        return $html;
    }

    public function __call($function, $args)
    {
        call_user_func_array([$this->pdf->pdf,$function], $args);
    }
}
