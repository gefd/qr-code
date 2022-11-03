<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode\Mode;

interface ModeInterface
{
    public function getData(string $source) : array;
}
