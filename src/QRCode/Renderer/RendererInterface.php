<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode\Renderer;

use QRCode\Bitmap;

interface RendererInterface
{
    public function render(Bitmap $bitmap, int $size) : string;
}
