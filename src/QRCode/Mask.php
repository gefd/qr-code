<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode;

class Mask
{
    const PATTERN_000 = 0;
    const PATTERN_001 = 1;
    const PATTERN_010 = 2;
    const PATTERN_011 = 3;
    const PATTERN_100 = 4;
    const PATTERN_101 = 5;
    const PATTERN_110 = 6;
    const PATTERN_111 = 7;

    const NUM_PATTERNS = 8;

    private Generator $qrCode;
    private array $penalties = [
        'N1' => 3,
        'N2' => 3,
        'N3' => 40,
        'N4' => 10
    ];

    public function __construct(Generator $qrCode)
    {
        $this->qrCode = $qrCode;
    }

    public function getBestMask(Bitmap $bitmap) : int
    {
        $bestMask = 0;
        $lowestPenalty = PHP_INT_MAX;

        for ($mask = 0; $mask < self::NUM_PATTERNS; $mask++) {
            $this->applyMask($bitmap, $mask);

            $penalty = $this->getPenaltyN1($bitmap)
                + $this->getPenaltyN2($bitmap)
                + $this->getPenaltyN3($bitmap)
                + $this->getPenaltyN4($bitmap);

            // undo mask
            $this->applyMask($bitmap, $mask);

            if ($penalty < $lowestPenalty) {
                $lowestPenalty = $penalty;
                $bestMask = $mask;
            }
        }

        return $bestMask;
    }

    public function applyMask(Bitmap $bitmap, int $mask) : void
    {
        $size = $bitmap->getSize();
        for ($col = 0; $col < $size; $col++) {
            for ($row = 0; $row < $size; $row++) {
                if ($bitmap->isReserved($row, $col)) {
                    continue;
                }
                $maskBit = $this->getMaskBit($mask, $row, $col);
                $bitmap->xor($row, $col, $maskBit);
            }
        }
    }

    protected function getMaskBit(int $mask, int $row, int $col) : bool
    {
        return match($mask) {
            self::PATTERN_000 => ($row + $col) % 2 === 0,
            self::PATTERN_001 => $row % 2 === 0,
            self::PATTERN_010 => $col % 3 === 0,
            self::PATTERN_011 => ($row + $col) % 3 === 0,
            self::PATTERN_100 => (\floor($row / 2) + \floor($col / 3)) % 2 === 0,
            self::PATTERN_101 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
            self::PATTERN_110 => ((($row * $col) % 2) + (($row * $col) % 3) % 2) === 0,
            self::PATTERN_111 => ((($row * $col) % 3) + (($row * $col) % 2) % 2) === 0
        };
    }

    // penalise greater than 5 adjacent blocks of the same colour
    protected function getPenaltyN1(Bitmap $bitmap) : int
    {
        $points = 0;
        $size = $bitmap->getSize();

        for ($row = 0; $row < $size; $row++) {
            $sameCol = $sameRow = 0;
            $lastCol = $lastRow = null;

            for ($col = 0; $col < $size; $col++) {
                $mod = $bitmap->get($row, $col);
                if ($mod === $lastCol) {
                    $sameCol++;
                } else {
                    if ($sameCol >= 5) {
                        $points += $this->penalties['N1'] + ($sameCol - 5);
                    }
                    $lastCol = $mod;
                    $sameCol = 1;
                }

                $mod = $bitmap->get($col, $row);
                if ($mod === $lastRow) {
                    $sameRow++;
                } else {
                    if ($sameRow >= 5) {
                        $points += $this->penalties['N1'] + ($sameRow - 5);
                    }
                    $lastRow = $mod;
                    $sameRow = 1;
                }
            }

            if ($sameCol >= 5) {
                $points += $this->penalties['N1'] + ($sameCol - 5);
            }
            if ($sameRow >= 5) {
                $points += $this->penalties['N1'] + ($sameRow - 5);
            }
        }

        return $points;
    }

    // penalise 2x2 blocks of the same colour
    protected function getPenaltyN2(Bitmap $bitmap) : int
    {
        $size = $bitmap->getSize();
        $points = 0;

        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $sum = \intval($bitmap->get($row, $col))
                    + \intval($bitmap->get($row, $col + 1))
                    + \intval($bitmap->get($row + 1, $col))
                    + \intval($bitmap->get($row + 1, $col + 1));
                if ($sum === 4 || $sum === 0) {
                    $points++;
                }
            }
        }

        return $points * $this->penalties['N2'];
    }

    // penalise alternating dark/light modules followed by 4 white modules
    protected function getPenaltyN3(Bitmap $bitmap) : int
    {
        $size = $bitmap->getSize();
        $points = 0;

        for ($row = 0; $row < $size; $row++) {
            $bitsCol = $bitsRow = 0;
            for ($col = 0; $col < $size; $col++) {
                $bitsCol = (($bitsCol << 1) & 0x7FF) | $bitmap->get($row, $col);
                if ($col >= 10 && ($bitsCol === 0x5D0 || $bitsCol === 0x05D)) {
                    $points++;
                }

                $bitsRow = (($bitsRow << 1) & 0x7FF) | $bitmap->get($col, $row);
                if ($col >= 10 && ($bitsRow === 0x5D0 || $bitsRow === 0x05D)) {
                    $points++;
                }
            }
        }

        return $points * $this->penalties["N3"];
    }

    // penalise a poor ratio of dark/light modules
    protected function getPenaltyN4(Bitmap $bitmap) : int
    {
        $ratio = $bitmap->getDarkCount() * 100 / $bitmap->getCount();
        $points = \intval(\abs(\ceil($ratio / 5) - 10));

        return $points * $this->penalties['N4'];
    }
}
