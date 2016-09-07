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
use Speedwork\Util\Utility;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Events extends Helper
{
    /**
     * Attach general events.
     *
     * @return [type] [description]
     */
    public function attach()
    {
        $events = $this;

        $this->get('dispatcher')->addListener('members.after.login', function ($event) use ($events) {
            $events->membersAfterLogin($event);
        });

        $this->get('dispatcher')->addListener('members.before.logout', function ($event) use ($events) {
            $events->membersBeforeLogout($event);
        });

        $this->get('dispatcher')->addListener('members.before.login', function ($event) use ($events) {
            $events->membersBeforeLogin($event);
        });

        $this->get('dispatcher')->addListener('members.login.failed', function ($event) use ($events) {
            $events->membersLoginFailed($event);
        });
    }

    public function membersLoginFailed($event)
    {
        $attempt_id = $this->get('session')->get('attempt_id');
        if ($attempt_id) {
            return $this->database->update('#__user_login_attempts', [
                'attempts = attempts + 1',
                'last_attempt_at' => time(),
                ],
                ['id' => $attempt_id]
            );
        }

        $save                    = [];
        $save['username']        = $event['username'];
        $save['ip_address']      = ip();
        $save['attempts']        = 1;
        $save['last_attempt_at'] = time();

        $this->database->save('#__user_login_attempts', $save);
    }

    public function membersBeforeLogin($event)
    {
        // Check is account blocked
        $row = $this->database->find('#__user_login_attempts', 'first', [
            'conditions' => ['OR' => ['username' => $event['username'], 'ip_address' => ip()]],
        ]);

        if (empty($row['id'])) {
            return true;
        }

        $attempts = $row['attempts'];
        $this->get('session')->set('login_attempts', $attempts);
        $this->get('session')->set('attempt_id', $row['id']);

        if ($attempts < 10) {
            return true;
        }

        $last_attempt = $row['last_attempt_at'];
        if ($last_attempt < strtotime('-1 HOUR')) {
            return true;
        }

        $event->stopPropagation();

        $event->results = [
            'status'  => 'ERROR',
            'message' => 'Your account is temporarly blocked for an hour due to multiple invalid attempts.',
        ];
    }

    public function membersAfterLogin($event)
    {
        $attempt_id = $this->get('session')->get('attempt_id');

        if ($attempt_id) {
            $this->database->delete('#__user_login_attempts',
                ['OR' => ['username' => $event['user']['username'], 'ip_address' => ip()]]
            );

            $this->get('session')->remove('attempt_id');
        }

        // Check is fake login
        if ($this->view == 'auth') {
            return true;
        }

        //save in login history
        if (empty($event['userid'])) {
            return true;
        }

        $save               = [];
        $save['session_id'] = $this->get('session')->getId();
        $save['source']     = 'Website';
        $save['ip']         = Utility::ip();
        $save['host']       = env('HTTP_HOST');
        $save['agent']      = env('HTTP_USER_AGENT');
        $save['referer']    = env('HTTP_REFERER');
        $save['created']    = time();
        $save['status']     = 1;

        $this->database->save('#__user_login_history', $save);
        $id = $this->database->lastInsertId();
        $this->get('session')->set('login_history_id', $id);

        $user = $event['user'];
        // Time to change the password
        $change_password = $this->link('members/changepass');

        //1 : force change
        //2 : advice to change

        if ($user['last_pw_change'] && $user['last_pw_change'] < strtotime('-90 DAY')) {
            $this->get('session')->set('password_change_required', 2);
            $this->get('session')->getFlashBag()->add('flash', "It's been 90 days since you changed your password. Please change it now!");

            $this->redirect($change_password);

            return true;
        }

        // Password is week
        $pattern = '/'.config('app.patterns.password').'/';
        if ($user['plain_password'] && !preg_match($pattern, $user['plain_password'])) {
            $this->get('session')->set('password_change_required', 2);
            $this->get('session')->getFlashBag()->add('flash', 'Your Password is not strong enough. Please change it now!');

            $this->redirect($change_password);

            return true;
        }

        // Password contain username
        if (stristr($user['plain_password'], $user['username']) !== false) {
            $this->get('session')->set('password_change_required', 1);
            $this->get('session')->getFlashBag()->add('flash', 'Your password contains username in it. please change.');

            $this->redirect($change_password);

            return true;
        }

        if ($change_required) {
            $this->get('session')->set('password_change_required', $change_required);
            $this->redirect($change_password);
        }
    }

    public function membersBeforeLogout()
    {
        $id = $this->get('session')->get('login_history_id');
        if ($id) {
            $this->database->update('#__user_login_history', [
                'status' => 2, 'modified' => time(),
                ], ['id' => $id]);
        }
    }
}
