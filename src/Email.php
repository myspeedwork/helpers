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

use Speedwork\Config\Configure;
use Speedwork\Core\Helper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Email extends Helper
{
    public function send($tags = [], $to_email, $subject, $template, $html = '', $from_email = '', $from_name = '', $attachments = [])
    {
        $att = [];
        if (is_array($attachments)) {
            foreach ($attachments as $attach) {
                $name  = @basename($attach);
                $att[] = [
                        'name'    => $name,
                        'content' => $attach,
                    ];
            }
        }

        $data                = [];
        $data['subject']     = $subject;
        $data['html']        = $html;
        $data['tags']        = $tags;
        $data['to']          = $to_email;
        $data['template']    = $template;
        $data['from_name']   = $from_name;
        $data['from_email']  = $from_email;
        $data['attachments'] = $att;
        $data['headers']     = [];
        $data['theme']       = '';

        return $this->sendEmail($data);
    }

    public function sendEmail($data = [])
    {
        $enable = Configure::read('mail.enable');

        $sent  = false;
        $merge = [];

        $name         = strtolower($data['template']);
        $name         = str_replace(['.tpl','.html','.txt'], '', $name);
        $data['name'] = $name;

        $config = Configure::read('mail');

        //over write mail from per perticular template if avalable
        $email_from = $config['email_from'];
        if ($email_from && is_array($email_from)) {
            if ($email_from[$name]) {
                $data['from_email'] = $email_from[$name][0];
                $data['from_name']  = $email_from[$name][1];
            }
        }

        $data['from_name']  = (empty($data['from_name'])) ? $config['from_name'] : $data['from_name'];
        $data['from_email'] = (empty($data['from_email'])) ? $config['from_email'] : $data['from_email'];

        $data = array_merge($merge, $data);
        $tags = (is_array($data['tags'])) ? $data['tags'] : [];

        if (!empty($data['template'])) {
            if ($data['html']) {
                $tags['html'] = $this->replace($tags, $data['html']);
            }

            $d = $this->getContent($data['template'], $tags);

            $data['html'] = $d['html'];
            $data['text'] = $d['text'];
            unset($d);

            //if subject is empty, look into content for subject
            if (empty($data['subject'])) {
                if (preg_match('~<!--subject:([^>]+)-->~', $data['text'], $matches)) {
                    $data['subject'] = $matches[1];
                } else {
                    $data['subject'] = _SITENAME;
                }
            }
        } elseif (!empty($tags)) {
            $data['html'] = $this->replace($tags, $data['html']);
            $data['text'] = $data['html'];
        }

        if (empty($data['html'])) {
            $data['html'] = $data['text'];
        }

        if (empty($data['text'])) {
            $data['text'] = $data['html'];
        }

        $data['to']          = $this->formatMailId($data['to']);
        $data['cc']          = $this->formatMailId($data['cc']);
        $data['bcc']         = $this->formatMailId($data['bcc']);
        $data['replay']      = $this->formatMailId($data['replay'], false);
        $data['attachments'] = $this->formatAttachments($data['attachments']);

        //if disable
        if (!$enable) {
            $data['reason'] = 'Mail sending disabled';
            $this->logMail($data, false);

            return true;
        }

        if (empty($data['to'])) {
            $data['reason'] = 'No valid email address';
            $this->logMail($data, false);

            return true;
        }

        $provider = ($config['provider']) ? $config['provider'] : 'PHPMailer';

        $mail = $this->get('resolver')->helper($provider);
        $sent = $mail->send($data, $config[strtolower($provider)]);

        $status = $sent['status'];

        if (!$status) {
            $data['reason'] = $sent['message'];
        }

        $this->logMail($data, $status);

        if ($status) {
            return true;
        }

        return false;
    }

    private function formatMailId($id, $blacklist = true)
    {
        if (empty($id)) {
            return [];
        }

        $ids = [];
        if (is_array($id)) {
            foreach ($id as $value) {
                if (isset($value['email'])) {
                    $ids[] = $value;
                } else {
                    $ids[] = ['email' => trim($value)];
                }
            }
        } elseif (preg_match('/>[^\S]*;/', $id)) {
            $id = explode(';', $id);
            foreach ($id as $v) {
                $v     = explode('<', $v);
                $email = ($v[1]) ? rtrim(trim($v[1]), '>') : $v[0];
                $name  = ($v[1]) ? trim($v[0]) : '';
                $ids[] = ['email' => trim($email), 'name' => trim($name)];
            }
        } elseif (strstr($id, '|')) {
            $delim = (strstr($id, ',')) ? ',' : ';';
            $id    = explode('|', $id);
            foreach ($id as $v) {
                $v     = explode($delim, $v);
                $email = ($v[1]) ? trim($v[1]) : $v[0];
                $name  = ($v[1]) ? trim($v[0]) : '';
                $ids[] = ['email' => trim($email), 'name' => trim($name)];
            }
        } else {
            $id = explode(',', $id);
            foreach ($id as $v) {
                $v     = explode(';', $v);
                $email = ($v[1]) ? $v[1] : $v[0];
                $name  = ($v[1]) ? $v[0] : '';
                $ids[] = ['email' => trim($email), 'name' => trim($name)];
            }
        }

        $list = [];
        // Remove Duplicates
        foreach ($ids as $value) {
            $key        = md5($value['email']);
            $list[$key] = $value;
        }

        unset($id, $ids);

        $config = Configure::read('mail');

        if ($config['blacklist'] && $blacklist) {
            $list = $this->checkBlackList($list);
        }

        return $list;
    }

    private function checkBlackList($emails = [])
    {
        if (empty($emails)) {
            return $emails;
        }

        $rows = $this->database->find('#__addon_email_blacklist', 'all', [
            'conditions' => ['email' => $emails],
            'fields'     => ['email'],
            ]
        );

        $blacklist = [];
        foreach ($rows as $row) {
            $blacklist[md5($row['email'])] = $row['email'];
        }

        foreach ($emails as $key => $email) {
            if (isset($blacklist[$key])) {
                unset($emails[$key]);
            }
        }

        return $emails;
    }

    private function formatAttachments($attachments = [])
    {
        if (!is_array($attachments)) {
            return [];
        }

        $att = [];
        foreach ($attachments as $attach) {
            if (isset($attach['content'])) {
                $att[] = $attach;
            } else {
                $att[] = [
                    'name'    => @basename($attach),
                    'content' => $attach,
                ];
            }
        }

        return $att;
    }

    public function getContent($filename, $tags = [])
    {
        $return = [];
        // insert template
        $email_replace = Configure::read('email.replace');
        $array_content = (is_array($email_replace)) ? $email_replace : [];

        $array_content['sitename']    = _SITENAME;
        $array_content['admin_email'] = _ADMIN_MAIL;
        $array_content['siteurl']     = $this->cleanUrl(_URL);
        $array_content['email_url']   = $this->cleanUrl(_IMG_URL.'email/en/');

        $tags = array_merge($tags, $array_content);
        $path = UPLOAD.'email'.DS.'en'.DS;

        $filename = str_replace('.html', '.tpl', $filename);
        $filename = $path.$filename;

        $theme    = Configure::read('email.theme');
        $theme    = ($theme) ? $theme : 'emailer.tpl';
        $template = $path.$theme;

        if (!is_object($this->engine)) {
            $this->get('resolver')->helper('smarty')->init();
        }

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
            $return['html'] = str_replace('<!--EMAILCONTENT-->', $html, $emailTemplate);
        }

        return $return;
    }

    public function replace(&$vars, &$html)
    {
        if (preg_match_all('~\{\$([^{}]+)\}~', $html, $matches) && count($matches[0]) > 0) {
            foreach ($matches[0] as $key => $match) {
                $html = str_replace($match, $this->find($matches[1][$key], $vars), $html);
            }
        }

        return $html;
    }

    public function find($string, $vars)
    {
        $str = explode('.', $string);
        foreach ($str as $key) {
            $vars = $vars[$key];
        }

        return $vars;
    }

    public function logMail(&$data, $status = true)
    {
        //log enable
        if (!Configure::read('mail.log') || $data['log'] === false) {
            return true;
        }

        $to = [];
        if (is_array($data['to'])) {
            foreach ($data['to'] as $d) {
                $to[] = $d['email'];
            }
        } else {
            $to = $data['to'];
        }

        $save               = [];
        $save['from_email'] = $data['from_email'];
        $save['to_email']   = implode(', ', $to);
        $save['subject']    = $data['subject'];
        $save['message']    = $data['text'];
        $save['created']    = time();
        $save['reason']     = $data['reason'];
        $save['status']     = ($status) ? 1 : 0;

        $this->database->save('#__addon_email_logs', $save);
    }

    private function cleanUrl($url)
    {
        $is_ssl = Configure::read('is_ssl');
        $prefix = ($is_ssl) ? 'https://' : 'http://';

        $short = substr($url, 0, 2);
        if ($short == '//') {
            $url = $prefix.ltrim($url, '//');
        }

        if (!preg_match('/^(http|https):/', $url)) {
            $url = $prefix.$url;
        }

        return $url;
    }
}
