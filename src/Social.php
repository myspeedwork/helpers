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

use Exception;
use Hybridauth\Endpoint;
use Hybridauth\Hybridauth;
use Speedwork\Core\Helper;

/**
 *  Helper Class to link social accounts.
 *
 * @since  0.0.1
 */
class Social extends Helper
{
    protected $instance;
    protected $config = [];
    protected $network;
    protected $profile;
    protected $providers = [];
    protected $provider_id;

    public function beforeRun()
    {
        //get social configuration
        $social = config('account.social');
        if ($social['enable'] == true) {
            $this->config = $social;

            //true|false|"error"|"info"
            $this->config['config']['debug_mode'] = 'error';
            $this->config['config']['debug_file'] = LOGS.'auth.log';

            $providers = $this->database->find('#__user_social_providers', 'all', [
                'conditions' => ['status' => 1],
                ]
            );

            foreach ($providers as &$value) {
                $meta = json_decode($value['meta'], true);

                $meta['enabled'] = true;
                $value['meta']   = $meta;

                $this->providers[$value['provider']] = $value;

                $this->config['config']['providers'][$value['provider']] = $meta;

                if ($value['is_default']) {
                    $this->config['default'] = $value['provider'];
                }
            }
        }
    }

    public function getProviderList()
    {
        $rows = $this->database->find('#__user_social_providers', 'all', [
            'conditions' => ['status' => 1],
            'fields'     => ['provider_id', 'provider', 'title', 'meta'],
            'order'      => ['ordering ASC'],
            ]
        );
        foreach ($rows as &$value) {
            $value['meta']  = json_decode($value['meta'], true);
            $value['class'] = ($value['meta']['class']) ? ($value['meta']['class']) : strtolower($value['provider']);
        }

        return $rows;
    }

    public function setProvider($provider = '')
    {
        if (!$provider || !$this->config) {
            return false;
        }

        if (!$this->config['config']['providers'][$provider]['enabled']) {
            $provider = $this->config['default'];
            if (!$this->config['config']['providers'][$provider]['enabled']) {
                return false;
            }
        }
        $this->network     = $provider;
        $this->provider_id = $this->providers[$provider]['provider_id'];

        return true;
    }

    public function connect()
    {
        $this->config['config']['base_url'] = $this->link('index.php?option=members&view=endpoint&network='.$this->network);

        try {
            $this->instance = new Hybridauth($this->config['config']);

            if (!$this->instance->isConnectedWith($this->network)) {
                $url = 'index.php?option=members&view=login&error='.urldecode('Your are not connected to '.$this->network.' or your session has expired');
                $this->redirect($url);
            }
            $adapter       = $this->instance->authenticate($this->network);
            $this->profile = $adapter->getUserProfile();

            //$this->profile = $this->setProfile($this->profile);

            if ($this->getProfile('identifier')) {
                return $this->proceed();
            }

            return false;
        } catch (Exception $e) {
            return [
                'status'  => 'ERROR',
                'message' => $this->errorhandler($e->getCode()),
            ];
        }
    }

    protected function getProfile($name)
    {
        $result = $this->profile[$name];
        if ($name == 'email' && empty($result)) {
            $result = $this->profile['displayName'].'@twitter.com';
        }

        return $result;
    }

    protected function setProfile($profile)
    {
        $meta                  = [];
        $meta['identifier']    = $profile->getIdentifier();
        $meta['webSiteURL']    = $profile->getWebSiteURL();
        $meta['profileURL']    = $profile->getProfileURL();
        $meta['photoURL']      = $profile->getPhotoURL();
        $meta['displayName']   = $profile->getDisplayName();
        $meta['description']   = $profile->getDescription();
        $meta['firstName']     = $profile->getFirstName();
        $meta['lastName']      = $profile->getLastName();
        $meta['gender']        = $profile->getGender();
        $meta['language']      = $profile->getLanguage();
        $meta['age']           = $profile->getAge();
        $meta['birthDay']      = $profile->getBirthDay();
        $meta['birthMonth']    = $profile->getBirthMonth();
        $meta['birthYear']     = $profile->getBirthYear();
        $meta['email']         = $profile->getEmail();
        $meta['emailVerified'] = $profile->getEmailVerified();
        $meta['phone']         = $profile->getPhone();
        $meta['address']       = $profile->getAddress();
        $meta['country']       = $profile->getCountry();
        $meta['region']        = $profile->getRegion();
        $meta['city']          = $profile->getCity();
        $meta['zip']           = $profile->getZip();

        return $meta;
    }

    public function linkAccount($userid)
    {
        $save                 = [];
        $save['user_id']      = $userid;
        $save['provider_id']  = $this->provider_id;
        $save['provider']     = $this->network;
        $save['identifier']   = $this->getProfile('identifier');
        $save['email']        = $this->getProfile('email');
        $save['display_name'] = $this->getProfile('displayName');
        $save['session_info'] = $this->instance->getStorageData();
        $save['profile']      = json_encode($this->profile);
        $save['created']      = time();
        $save['status']       = 1;

        $this->database->save('#__user_social', $save);

        return $this->database->lastInsertId();
    }

    public function login($userid)
    {
        //get details from users table using this id
        $row = $this->database->find('#__users', 'first', [
                'conditions' => ['userid' => $userid],
                'fields'     => ['userid','email', 'password'],
            ]
        );

        if (empty($row['userid'])) {
            return false;
        }

        $members = $this->get('resolver')->requestModel('members');

        $data             = [];
        $data['username'] = $row['email'];
        $data['password'] = $row['password'];

        return $members->login($data, false);
    }

    public function register()
    {
        $email = $this->getProfile('email');

        //link account with existing email ID
        $row = $this->database->find('#__users', 'first', [
                'conditions' => ['email' => $email],
                'fields'     => ['userid'],
            ]
        );

        if ($row['userid']) {
            $this->linkAccount($row['userid']);

            return $this->login($row['userid']);
        }

        $save               = [];
        $save['first_name'] = $this->getProfile('firstName');
        $save['last_name']  = $this->getProfile('lastName');
        $save['email']      = $email;
        $save['password']   = $this->getProfile('identifier');
        $save['mobile']     = $this->getProfile('phone');
        $save['gender']     = $this->getProfile('gender');

        $members  = $this->get('resolver')->requestModel('members');
        $response = $members->register($save);

        if ($response['status'] == 'OK') {
            $userid = $response['data']['userid'];

            $this->linkAccount($userid);
        }

        return $response;
    }

    public function proceed()
    {
        $identifier = $this->getProfile('identifier');

        $row = $this->database->find('#__user_social', 'first', [
                'conditions' => [
                    'provider_id' => $this->provider_id,
                    'identifier'  => $identifier,
                ],
                'fields' => ['id','user_id'],
            ]
        );

        // Already a member
        if ($row['id']) {
            $this->updateSession($row['id']);

            return $this->login($row['user_id']);
        }

        // New member
        return $this->register();
    }

    public function updateSession($id)
    {
        $session_info = $this->instance->getStorageData();
        if ($session_info) {
            $this->database->update('#__user_social', ['session_info' => $session_info, 'modified' => time()], ['id' => $id]);
        }
    }

    public function getProvider($provider)
    {
        return $this->database->find('#__user_social_providers', 'first', [
                'conditions' => [
                    'provider' => $provider,
                    'status'   => 1,
                ],
            ]
        );
    }

    public function endpoint()
    {
        ( new Endpoint() )->process();
        $this->connect();
    }

    public function logout()
    {
        if ($this->instance) {
            $this->instance->logoutAllProviders();
        }
    }

    private function errorhandler($errorcode)
    {
        $message = '';
        switch ($errorcode) {
            case 0:
                $message = 'Unspecified error.';
                break;
            case 1:
                $message = 'Hybridauth configuration error.';
                break;
            case 2:
                $message = 'Provider not properly configured.';
                break;
            case 3:
                $message = 'Unknown or disabled provider.';
                break;
            case 4:
                $message = 'Missing provider application credentials.';
                break;
            case 5:
                $message = 'Authentication failed. The user has canceled the authentication or the provider refused the connection.';
                break;
            case 6:
                $message = 'User profile request failed. Most likely the user is not connected to the provider and he should to authenticate again.';
                $this->logout();
                break;
            case 7:
                $message = 'User not connected to the provider.';
                $this->logout();
                break;
            case 8:
                $message = 'Provider does not support this feature.';
                break;
        }

        return $message;
    }

    public function publish($usersession, $message, $options = [])
    {
        if (!$usersession || empty($message)) {
            return ['status' => 'ERROR', 'message' => 'Missing parameters'];
        }
        $supported = [
            'Facebook',
            'Twitter',
            'Identica',
            'LinkedIn',
            'QQ',
            'Sina',
            'Murmur',
            'Pixnet',
            'Plurk',
        ];
        $message = (!is_array($message)) ? ['message' => $message] : $message;

        if (!in_array(trim($usersession['provider']), $supported)) {
            return ['status' => 'ERROR', 'message' => 'Method not supported'];
        }

        if ($options['connect']) {
            if (is_object($this->adapter)) {
                $this->adapter->logout();
                $this->link->logoutAllProviders();
                unset($this->adapter, $this->link);
            }

            try {
                $this->link = new Hybridauth($this->config['config']);
                $this->link->logoutAllProviders();
                $this->link->restoreSessionData($usersession['session_info']);
                $this->adapter = $this->link->getAdapter($usersession['provider']);
            } catch (Exception $e) {
                $error = $this->errorhandler($e->getCode());
                //Log::write('publishpost', 'Publish Error: User = '.json_encode($usersession).' Post = '.$message.' Error ='.$e->getMessage().' Custom = '.$error);

                return ['status' => 'ERROR', 'message' => $e->getMessage()];
            }
        }

        try {
            $publish = true;

            if ($usersession['provider'] == 'Facebook') {
                $publish     = false;
                $permissions = $this->adapter->api()->api('/me/permissions');
                foreach ($permissions['data'] as $permit) {
                    if ($permit['permission'] == 'publish_actions' && $permit['status'] == 'granted') {
                        $publish = true;
                        break;
                    }
                }
                unset($permissions);
            }
            if ($usersession['provider'] == 'LinkedIn') {
                $message = (is_array($message)) ? $message['message'] : $message;
            }

            if ($publish) {
                try {
                    $this->adapter->setUserStatus($message);
                } catch (Exception $e) {
                    return ['status' => 'ERROR', 'message' => $e->getMessage()];
                }
            } else {
                return ['status' => 'ERROR', 'message' => 'Publish permission declined'];
            }
        } catch (Exception $e) {
            $error = $this->errorhandler($e->getCode());
            //Log::write('publishpost', 'Publish Error: User = '.json_encode($usersession).' Post = '.$message.' Error ='.$e->getMessage().' Custom = '.$error);

            return ['status' => 'ERROR', 'message' => $e->getMessage()];
        }

        return ['status' => 'OK', 'message' => 'Success'];
    }
}
