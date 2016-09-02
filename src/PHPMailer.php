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

use PHPMailer as BaseMailer;

/**
 * @vendor "phpmailer/phpmailer": "~5.2"
 *
 * @author Sankar <sankar.suda@gmail.com>
 */
class PHPMailer
{
    public function send($data = [], $config = [])
    {
        $smtp = $config['smtp'];
        $mail = new BaseMailer();

        if ($smtp) {
            $mail->IsSMTP();                 // set mailer to use SMTP
            $mail->Host     = $config['mail_host'];  // specify main and backup server
            $mail->SMTPAuth = true;     // turn on SMTP authentication
            $mail->Port     = $config['mail_port'];     // Mail server port
            $mail->Username = $config['mail_user'];  // SMTP username
            $mail->Password = $config['mail_pass']; // SMTP password
        } else {
            $mail->IsMail();
        }

        $mail->From     = $data['from_email'];
        $mail->FromName = $data['from_name'];
        $mail->IsHTML(true);
        $mail->Subject = $data['subject'];

        if (is_array($data['headers'])) {
            foreach ($data['headers'] as $k => $v) {
                $mail->AddCustomHeader($k.':'.$v);
            }
        }

        $mail->MsgHTML($data['html']);
        $mail->AltBody = $data['text'];

        if ($data['calender']) {
            $mail->Ical = $data['calender'];
        }

        foreach ($data['to'] as $value) {
            $mail->AddAddress($value['email'], $value['name']);
        }

        foreach ($data['cc'] as $value) {
            $mail->addCC($value['email'], $value['name']);
        }

        foreach ($data['bcc'] as $value) {
            $mail->addBCC($value['email'], $value['name']);
        }

        foreach ($data['replay'] as $value) {
            $mail->addReplyTo($value['email'], $value['name']);
        }

        foreach ($data['attachments'] as $value) {
            $mail->AddAttachment($value['content']);
        }

        $sent = $mail->Send();

        // Clear all addresses and attachments for next mail
        $mail->ClearAddresses();
        $mail->ClearAttachments();

        return [
            'status'  => $sent,
            'message' => $mail->ErrorInfo,
        ];
    }
}
