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

use Speedwork\Config\Configure;
use Speedwork\Core\Helper;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class Crypt extends Helper
{
    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $input;

    /*
     * @var string
     */
    private $algorithm;

    /*
     * @var resource
     */
    private $td;

    /*
     * @var string
     */
    private $iv;

    public $mode = 'ecb';

    public function start($algorithm, $key = '')
    {
        $this->algorithm = $algorithm;

        if (empty($key)) {
            $this->key = Configure::read('Security.cipherSeed');
        } else {
            $this->key = $key;
        }

        $this->open_module();
        $this->create_init_vector();
    }

    public function __destruct()
    {
        mcrypt_module_close($this->td);
    }

    private function open_module()
    {
        $this->td = mcrypt_module_open($this->algorithm, null, $this->mode, null);
    }

    private function create_init_vector()
    {
        $this->iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($this->td), MCRYPT_RAND);
    }

    private function generic_init()
    {
        return mcrypt_generic_init($this->td, $this->key, $this->iv);
    }

    public function encrypt($input)
    {
        $this->input = $input;

        $this->generic_init();
        $enc = mcrypt_generic($this->td, $this->input);
        mcrypt_generic_deinit($this->td);

        return base64_encode($enc);
    }

    /**
     * @param $enc string
     */
    public function decrypt($enc)
    {
        $ret = $this->generic_init();
        $enc = base64_decode($enc);
        $dec = mdecrypt_generic($this->td, $enc);
        mcrypt_generic_deinit($this->td);

        return $dec;
    }

    public static function get_available_algorithms()
    {
        return mcrypt_list_algorithms();
    }
}
