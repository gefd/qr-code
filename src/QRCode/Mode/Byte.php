<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode\Mode;

class Byte implements ModeInterface
{
    public function getData(string $source) : array
    {
        $data = [];
        $chars = \str_split($source);
        foreach ($chars as $char) {
            $data[] = \str_pad(\decbin(\ord($char)), 8, '0', STR_PAD_LEFT);
        }
        return $data;
    }
}
