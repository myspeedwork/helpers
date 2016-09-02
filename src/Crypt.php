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
            $this->key = config('app.key');
        } else {
            $this->key = $key;
        }

        $this->openModule();
        $this->createInitVector();
    }

    public function __destruct()
    {
        if (is_a($this->td, 'mcrypt')) {
            mcrypt_module_close($this->td);
        }
    }

    private function openModule()
    {
        $this->td = mcrypt_module_open($this->algorithm, null, $this->mode, null);
    }

    private function createInitVector()
    {
        $this->iv = mcrypt_create_iv(mcrypt_enc_get_iv_size($this->td), MCRYPT_RAND);
    }

    private function genericInit()
    {
        return mcrypt_generic_init($this->td, $this->key, $this->iv);
    }

    public function encrypt($input)
    {
        $this->input = $input;

        $this->genericInit();
        $enc = mcrypt_generic($this->td, $this->input);
        mcrypt_generic_deinit($this->td);

        return base64_encode($enc);
    }

    /**
     * @param $enc string
     */
    public function decrypt($enc)
    {
        $this->genericInit();

        $enc = base64_decode($enc);
        $dec = mdecrypt_generic($this->td, $enc);
        mcrypt_generic_deinit($this->td);

        return $dec;
    }

    public static function getAvailable()
    {
        return mcrypt_list_algorithms();
    }
}
