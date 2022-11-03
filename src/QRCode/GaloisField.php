<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode;

use SplFixedArray;

class GaloisField
{
    private SplFixedArray $expTable;
    private SplFixedArray $logTable;

    public function __construct()
    {
        $this->expTable = SplFixedArray::fromArray(\array_fill(0, 256, 0));
        $this->logTable = SplFixedArray::fromArray(\array_fill(0, 256, 0));

        $this->generate();
    }

    private function generate() : void
    {
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            $this->expTable[$i] = $x;
            $this->logTable[$x] = $i;

            $x = $x << 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
    }

    public function log(int $n) : int
    {
        return $this->logTable[$n % 256];
    }

    public function exp(int $n) : int
    {
        return $this->expTable[$n % 255];
    }

    public function mul(int $a, int $b) : int
    {
        return $this->exp($this->log($a) + $this->log($b));
    }
}
