<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode\Mode;

class AlphaNumeric implements ModeInterface
{
    const CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    protected function char(string $c) : int
    {
        return \strpos(self::CHARS, $c);
    }

    public function getData(string $source) : array
    {
        $data = [];
        foreach (\str_split($source, 2) as $pair) {
            $p = \str_split($pair);
            if (isset($p[1])) {
                // 11bit for a pair of characters
                $group = 45 * $this->char($p[0])
                    + $this->char($p[1]);
                $group = \str_pad(\decbin($group), 11, '0', STR_PAD_LEFT);
            } else {
                // 6bit for a single character, when the string length is uneven
                $group = $this->char($p[0]);
                $group = \str_pad(\decbin($group), 6, '0', STR_PAD_LEFT);
            }
            $data[] = $group;
        }

        return $data;
    }
}
