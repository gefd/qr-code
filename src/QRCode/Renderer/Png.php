<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode\Renderer;

use QRCode\Bitmap;

class Png
    implements RendererInterface
{
    public function render(Bitmap $bitmap, int $size) : string
    {
        $blockSize = \intval(\floor($size / ($bitmap->getSize() + 8)));
        $offset = \intval(\floor($size - ($blockSize * $bitmap->getSize())) / 2);

        $image = \imagecreate($size, $size);
        $black = \imagecolorallocate($image, 0, 0, 0);
        $white = \imagecolorallocate($image, 255, 255, 255);

        \imagefill($image, 0, 0, $white);

        for ($row = 0; $row < $bitmap->getSize(); $row++) {
            for ($col = 0; $col < $bitmap->getSize(); $col++) {
                $x = $offset + ($row * $blockSize);
                $y = $offset + ($col * $blockSize);
                if ($bitmap->get($row, $col)) {
                    \imagefilledrectangle($image, $x, $y, $x + $blockSize, $y + $blockSize, $black);
                }
            }
        }

        \ob_start();
        \imagepng($image);

        return \ob_get_clean();
    }
}
