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

use Stampie\Identity;
use Stampie\Message;

/**
 * @author sankar <sankar.suda@gmail.com>
 */
class StampieMessage extends Message
{
    protected $subject;
    protected $from;
    protected $to = [];

    public function __construct($to)
    {
    }
    /**
     * @param string $text
     */
    public function setText($text)
    {
        $this->text = $text;
    }

    /**
     * @param string $text
     */
    public function setFrom($from)
    {
        $this->from = $from;
    }

    /**
     * @param string $text
     */
    public function setTo($to)
    {
        foreach ($to as $value) {
            $this->to = new Identity($value['email'], $value['name']);
        }
    }

    /**
     * @param string $text
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @return string
     */
    public function getFrom()
    {
        return $this->from;
    }
}
