<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode\Mode;

class Numeric implements ModeInterface
{
    public function getData(string $source) : array
    {
        $data = [];
        foreach (\str_split($source, 3) as $tuple) {
            $bits = match(\strlen((string)\intval($tuple))) {
                3 => 9,
                2 => 7,
                1 => 4
            };
            $data[] = \str_pad(\decbin(\intval($tuple)), $bits, '0', STR_PAD_LEFT);
        }

        return $data;
    }
}
