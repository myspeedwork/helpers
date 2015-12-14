<?php

/**
 * This file is part of the Speedwork package.
 *
 * @link http://github.com/speedwork
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

//"ensepar/html2pdf": "1.0.*@dev"

namespace Speedwork\Helpers;

use HTML2PDF;
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
    private $pdf;

    /**
     * holds the content to generate pdf.
     *
     * @var unknown
     */
    private $content = null;

    public function start($orientation = 'P', $size = 'A4')
    {
        $this->pdf = new HTML2PDF($orientation, $size, 'en');
        $this->pdf->setDefaultFont('Arial');
        $this->content = null;

        return $this->pdf;
    }

    public function setContent($data = null)
    {
        $this->content .= $data;

        return $this;
    }

    public function getPdf()
    {
        return $this->pdf;
    }

    /**
     * add content to pdf content.
     *
     * @param string $content
     */
    public function append($content = '')
    {
        return $this->setContent($content);
    }

    public function writeHTML($content = '')
    {
        return $this->setContent($content);
    }

    /**
     * @param $output (string) Destination where to send the document.
     *It can take one of the following values:
     *I: send the file inline to the browser .
     *D: file download with the name given by name.
     *F: save to a local server file with the name given by name.
     *S: return the document as a string (name is ignored).
     */
    public function send($data, $name = 'document.pdf', $output = 'D')
    {
        $this->getContent($data);

        $this->pdf->writeHTML($this->content);
        $this->pdf->output($name, $output);

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
        if (empty($data['template'])) {
            return false;
        }

        $tags             = [];
        $tags['sitename'] = _SITENAME;
        $tags['siteurl']  = _URL;
        $tags['imageurl'] = _IMG_URL.'pdf_templates/';

        $tags = array_merge($data['tags'], $tags);

        $filename = $data['template'];

        $path = UPLOAD.'pdf_templates'.DS;

        $filename = str_replace('.html', '.tpl', $filename);
        $filename = $path.$filename;

        $theme    = config('app.pdf.theme');
        $theme    = ($theme) ? $theme : 'theme.tpl';
        $template = $path.$theme;

        if (file_exists($filename)) {
            if (file_exists($template)) {
                $emailTemplate = $this->get('engine')->create($template, $tags)->render();
            }

            $html = $this->get('engine')->create($filename, $tags)->render();

            //put the content into template
            $this->content = str_replace('<!--PDFBODY-->', $html, $emailTemplate);
        }
    }

    public function __call($function, $args)
    {
        call_user_func_array([$this->pdf->pdf, $function], $args);
    }
}
