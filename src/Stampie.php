<?php

/**
 * This file is part of the Speedwork package.
 *
 * (c) 2s Technologies <info@2stechno.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Speedwork\Helpers;

use Buzz;
use Speedwork\Core\Helper;
use Stampie as BaseStampie;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Stampie extends Helper
{
    /**
     * Function to get the channel details.
     *
     * @param [type] $id [description]
     *
     * @return [type] [description]
     */
    public function send($data = [], $config = [])
    {
        //checking the configuration
        if ((empty($config['user']) && empty($config['pass']))) {
            return false;
        }

        $adapter = new BaseStampie\Adapter\Buzz(new Buzz\Browser());

        $message = $this->get('resolver')->helper('StampieMessage');
        $message->setTo($data['to']);
        $message->setHtml($data['html']);
        $message->setText($data['text']);
        $message->setFrom($data['from_mail']);
        $message->setSubject($data['subject']);

        $provider = $config['adapter'];

        switch ($provider) {
            case 'sendgrid':
                $mailer = new BaseStampie\Mailer\SendGrid($adapter, $config['user'].':'.$config['pass']);
                break;
            case 'postmark':
                $mailer = new BaseStampie\Mailer\Postmark($adapter, $config['user']);
                break;
            case 'mailgun':
                $mailer = new BaseStampie\Mailer\MailGun($adapter, $config['user'].':'.$config['pass']);
                break;
            case 'mandrill':
                $mailer = new BaseStampie\Mailer\Mandrill($adapter, $config['user']);
                break;
        }

        $return = $mailer->send($message);

        printr($return);
        die;

        return $return;
    }
}
