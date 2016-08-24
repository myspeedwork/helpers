<?php

/**
 * This file is part of the Infibuzz application.
 *
 * @license http://opensource.org/licenses/MIT
 *
 * @link http://gitlab.devtools.com/infibuzz/infibuzz
 *
 * @version 0.0.1
 */
namespace Speedwork\Helpers;

use Intervention\Image\ImageManager;

/**
 * @author Sankar <sankar.suda@gmail.com>
 */
class Image
{
    public function make($photo = null)
    {
        $manager = new ImageManager();

        return $manager->make($photo);
    }
}
