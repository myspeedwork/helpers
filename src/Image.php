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
