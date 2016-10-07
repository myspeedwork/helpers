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

use Exception;
use Speedwork\Core\Helper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Email extends Helper
{
    protected $config = [];

    public function send($data = [])
    {
        return $this->sendEmail($data);
    }

    public function sendEmail($data = [])
    {
        $sent         = false;
        $name         = strtolower($data['template']);
        $name         = str_replace(['.tpl', '.html', '.txt'], '', $name);
        $data['name'] = $name;

        $this->config = $this->config('mail');
        if (is_array($data['config'])) {
            $this->config = array_merge($this->config, $data['config']);
        }

        $details         = $this->getEmailContent($data);
        $data['subject'] = $details['subject'];
        $data['text']    = $details['text'];
        $data['html']    = $details['html'];

        $data['from']        = $this->setFromHeaders($data, $name);
        $data['to']          = $this->formatMailId($data['to']);
        $data['cc']          = $this->formatMailId($data['cc']);
        $data['bcc']         = $this->formatMailId($data['bcc']);
        $data['replay']      = $this->formatMailId($data['replay'], false);
        $data['attachments'] = $this->formatAttachments($data['attachments']);

        //if disable
        if (!$this->config['enable']) {
            $data['reason'] = 'Mail sending disabled';
            $this->logMail($data, false);

            return true;
        }

        if (empty($data['to'])) {
            $data['reason'] = 'No valid email address';
            $this->logMail($data, false);

            return true;
        }

        $provider  = $this->config['provider'] ?: 'php';
        $providers = $this->config['providers'];
        $config    = $providers[$provider];
        $config    = array_merge(['from' => $data['from']], $config);
        $driver    = $config['driver'];

        try {
            $mail = $this->get('resolver')->helper($driver);
            $sent = $mail->send($data, $config);
        } catch (Exception $e) {
            $sent            = [];
            $sent['status']  = 'FAILED';
            $sent['message'] = $e->getMessage();
        }

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

    protected function setFromHeaders($data = [], $name = null)
    {
        //over write mail from per perticular template if avalable
        $from = $this->config['email_from'];
        if ($from && is_array($from) && isset($from[$name])) {
            return [
                'email' => $from[$name][0],
                'name'  => $from[$name][1],
            ];
        }

        if (is_array($data['from'])) {
            return $data['from'];
        }

        if (is_array($this->config['from'])) {
            return $this->config['from'];
        }
    }

    protected function getEmailContent($data = [])
    {
        $tags = (is_array($data['tags'])) ? $data['tags'] : [];

        if (!empty($data['template'])) {
            if ($data['html']) {
                $tags['html'] = $this->replace($tags, $data['html']);
            }

            $d = $this->getContent($data, $tags);

            $data['html'] = $d['html'];
            $data['text'] = $d['text'];
            unset($d);

            //if subject is empty, look into content for subject
            if (empty($data['subject'])) {
                if (preg_match('~<!--subject:([^>]+)-->~', $data['text'], $matches)) {
                    $data['subject'] = $matches[1];
                } else {
                    $data['subject'] = $this->config('app.name');
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

        return [
            'subject' => $data['subject'],
            'text'    => $data['text'],
            'html'    => $data['html'],
        ];
    }

    protected function formatMailId($id, $blacklist = true)
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
            $list[$value['email']] = $value;
        }

        if ($this->config['blacklist'] && $blacklist) {
            $list = $this->checkBlackList($list);
        }

        unset($id, $ids);

        return $list;
    }

    protected function checkBlackList($emails = [])
    {
        if (empty($emails)) {
            return $emails;
        }

        $rows = $this->database->find('#__addon_email_blacklist', 'all', [
            'conditions' => ['email' => $emails],
            'fields'     => ['email'],
        ]);

        $blacklist = [];
        foreach ($rows as $row) {
            $blacklist[$row['email']] = 1;
        }

        foreach ($emails as $key => $email) {
            if (isset($blacklist[$key])) {
                unset($emails[$key]);
            }
        }

        return $emails;
    }

    protected function formatAttachments($attachments = [])
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
                    'name'    => basename($attach),
                    'content' => $attach,
                ];
            }
        }

        return $att;
    }

    protected function getContent($data = [], $tags = [])
    {
        $return = [];
        // insert template
        $email_replace = $this->config['replace'];
        $array_content = (is_array($email_replace)) ? $email_replace : [];

        $locale = $this->config['locale'] ?: 'en';

        $array_content['sitename']    = $this->config('app.name');
        $array_content['admin_email'] = $this->config('app.email');
        $array_content['siteurl']     = $this->config('app.url');
        $array_content['email_url']   = path('email', true).$locale.'/';

        $tags = array_merge($tags, $array_content);
        $path = path('email').$locale.DS;

        $filename = $data['template'];
        $filename = str_replace('.html', '.tpl', $filename);
        $filename = $path.$filename;

        $theme    = $this->config['theme'];
        $theme    = ($theme) ? $theme : 'emailer.tpl';
        $theme    = $data['theme'] ?: $theme;
        $template = $path.$theme;

        if (file_exists($filename)) {
            if (file_exists($template)) {
                $emailTemplate = $this->get('engine')->create($template, $tags)->render();
            }

            $filename       = str_replace('.html', '.tpl', $filename);
            $html           = $this->get('engine')->create($filename, $tags)->render();
            $return['text'] = $html;

            //key for backup
            $html = $this->replace($tags, $html);

            //put the content into template
            $return['html'] = str_replace('<!--EMAILCONTENT-->', $html, $emailTemplate);
        }

        return $return;
    }

    protected function replace($vars = [], $html = null)
    {
        if (preg_match_all('~\{\$([^{}]+)\}~', $html, $matches) && count($matches[0]) > 0) {
            foreach ($matches[0] as $key => $match) {
                $html = str_replace($match, $this->find($matches[1][$key], $vars), $html);
            }
        }

        return $html;
    }

    protected function find($string, $vars)
    {
        $str = explode('.', $string);
        foreach ($str as $key) {
            $vars = $vars[$key];
        }

        return $vars;
    }

    protected function logMail($data = [], $status = true)
    {
        //log enable
        if (!$this->config('mail.log') || $data['log'] === false) {
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

        $from = $data['from']['email'];

        $save               = [];
        $save['from_email'] = $from;
        $save['to_email']   = implode(', ', $to);
        $save['subject']    = $data['subject'];
        $save['message']    = $data['text'];
        $save['created']    = time();
        $save['reason']     = $data['reason'];
        $save['status']     = ($status) ? 1 : 0;

        $this->database->save('#__addon_email_logs', $save);
    }
}
