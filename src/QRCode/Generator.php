<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode;

use QRCode\Mode\AlphaNumeric;
use QRCode\Mode\Byte;
use QRCode\Mode\Numeric;

class Generator
{
    const FINDER_SIZE = 7;

    private array $debug;

    private string $content;
    private string $ecLevel;
    private array $encodedData;
    private int $version;
    private int $mode;
    private int $maskUsed;

    public function __construct(string $message, string $ecLevel = "L")
    {
        $this->content = $message;
        $this->ecLevel = $ecLevel;
    }

    public function debug(string $key = null, mixed $value = null) : array
    {
        if (!\is_null($key)) {
            $this->debug[$key] = $value;
        }
        return $this->debug;
    }

    public function setVersion(int $version) : void
    {
        $this->version = $version;
    }

    public function getVersion() : int
    {
        return $this->version;
    }

    public function getMode() : int
    {
        return $this->mode;
    }

    public function getEcLevel() : string
    {
        return $this->ecLevel;
    }

    public function getMaskUsed() : int
    {
        return $this->maskUsed;
    }

    public function getBitmap() : Bitmap
    {
        $this->mode = $this->detectMode($this->content);
        if (!isset($this->version)) {
            $this->version = Version::detectVersion($this->content, $this->ecLevel, $this->mode);
        }

        $this->debug('detected mode', $this->mode);
        $this->debug('version', $this->version);

        $size = Version::getModuleCount($this->version);
        $this->debug('size', $size);

        $words = $this->getEncodedData($this->mode, $this->version);
        $this->debug('encoded 8bit words', \count($words));

        $bitmap = new Bitmap($size, $words);

        $this->applyData($bitmap, $words);

        return $bitmap;
    }

    protected function applyData(Bitmap $bitmap, array $words) : void
    {
        $this->addFinderPatterns($bitmap);
        $this->addTimingPatterns($bitmap);
        $this->addDarkModule($bitmap);
        if ($this->version >= 2) {
            $this->addAlignmentPatterns($bitmap);
        }
        if ($this->version >= 7) {
            $this->addVersionInfo($bitmap);
        }
        $this->maskFormatArea($bitmap, true);

        $mask = new Mask($this);
        $bestMask = $mask->getBestMask($bitmap);

        $this->addData($bitmap, $words);

        $mask->applyMask($bitmap, $bestMask);
        $this->maskUsed = $bestMask;

        $this->debug('mask used', $this->maskUsed);

        // unmask format area
        $this->maskFormatArea($bitmap, false);
        $this->addFormatInfo($bitmap, $this->maskUsed);
    }

    public function getEncodedData(int $mode, int $version) : array
    {
        if (!isset($this->encodedData)) {
            $data = $this->getBinaryData($mode, $version);
            $data = $this->addCodewords($data, $version, $this->ecLevel);
            $data = $this->addRemainderBits($data, $version);
            $this->encodedData = $data;
        }
        return $this->encodedData;
    }

    protected function addCodewords(array $binaryData, int $version, string $ecLevel) : array
    {
        $totalGroups = Version::getGroupCount($version, $ecLevel);
        $totalEcBlocks = Version::getTotalEcBlocks($version, $ecLevel);
        $totalDataBlocks = Version::getTotalDataBlocks($version, $ecLevel);
        $encoder = new ReedSolomon($totalEcBlocks);

        $this->debug('total groups', $totalGroups);
        $this->debug('total data blocks', $totalDataBlocks);
        $this->debug('total ec blocks', $totalEcBlocks);

        $dataBlocks = [];
        $ecBlocks = [];

        $offset = 0;
        // generate ec words for each block
        for ($group = 0; $group < $totalGroups; $group++) {
            $blocksInGroup = Version::getBlocksInGroup($version, $ecLevel, $group);
            $wordsInBlock = Version::getBlockSizeInGroup($version, $ecLevel, $group);

            for ($block = 0; $block < $blocksInGroup; $block++) {
                $this->debug('block offset', $offset);
                $this->debug('words in block', $wordsInBlock);
                $blockData = \array_slice($binaryData, $offset, $wordsInBlock);
                $this->debug('block data', \array_map('bindec', $blockData));
                $encoded = $encoder->encode(\array_map('bindec', $blockData));
                $this->debug('ec data', $encoded);
                $encoded = \array_map(fn($v) => \str_pad(\decbin($v), 8, '0', STR_PAD_LEFT), $encoded);
                $this->debug("block {$block} ec blocks", \count($encoded));

                $dataBlocks[$group][$block] = $blockData;
                $ecBlocks[$group][$block] = $encoded;

                $offset += $wordsInBlock;
            }
        }

        // interleave the data & ec words
        $data = [];
        $blockIndex = 0;
        for ($i = 0; $i < $totalDataBlocks; $i++) {
            for ($group = 0; $group < $totalGroups; $group++) {
                foreach ($dataBlocks[$group] as $block => $words) {
                    $data[] = $words[$blockIndex];
                }
                $blockIndex++;
            }
        }
        $blockIndex = 0;
        for ($i = 0; $i < $totalEcBlocks; $i++) {
            for ($group = 0; $group < $totalGroups; $group++) {
                foreach ($ecBlocks[$group] as $block => $words) {
                    $data[] = $words[$blockIndex];
                }
                $blockIndex++;
            }
        }

        return $data;
    }

    protected function getBinaryData(int $mode, int $version) : array
    {
        $modeString = Version::getModeString($mode);
        $countIndicator = Version::getCountIndicator(\strlen($this->content), $version, $mode);

        $this->debug('mode string', $modeString);
        $this->debug('string length', \strlen($this->content));
        $this->debug('count indicator', $countIndicator);
        $this->debug('count indicator length', \strlen($countIndicator));

        $requiredBits = Version::getRequiredBits($version, $this->ecLevel);

        // todo: add support for kanji encoding...
        $modeEncoder = match($mode) {
            Version::MODE_NUMERIC => new Numeric(),
            Version::MODE_ALPHANUMERIC => new AlphaNumeric(),
            Version::MODE_BYTE => new Byte()
        };

        $buffer = $modeString
            . $countIndicator
            . \implode($modeEncoder->getData($this->content));

        if ($requiredBits - \strlen($buffer) <= 4 && $requiredBits > \strlen($buffer)) {
            $buffer .= \str_repeat('0', $requiredBits - \strlen($buffer));
        }
        if (\strlen($buffer) % 8 > 0) {
            $buffer .= \str_repeat('0', 8 - (\strlen($buffer) % 8));
        }

        $remainingBytes = ($requiredBits - \strlen($buffer)) / 8;
        for ($i = 0; $i < $remainingBytes; $i++) {
            $buffer .= ($i % 2 === 0)
                ? '11101100' // 0xEC
                : '00010001';// 0x11
        }

        $dataWords = \str_split($buffer, 8);
        $this->debug('data words', \count($dataWords));

        return $dataWords;
    }

    protected function addRemainderBits(array $data, int $version) : array
    {
        $bits = 0;
        if ($version >= 2 && $version <= 6) {
            $bits = 7;
        } else if (($version >= 14 && $version <= 20) || ($version >= 28 && $version <= 34)) {
            $bits = 3;
        } else if ($version >= 21 && $version <= 27) {
            $bits = 4;
        }
        if ($bits) {
            $data[] = \str_repeat('0', $bits);
        }
        $this->debug('add remainder bits', $bits);
        return $data;
    }

    protected function detectMode(string $string) : int
    {
        if (\preg_match('/^\d+$/', $string)) {
            return Version::MODE_NUMERIC;
        } else if (\preg_match('#^[' . AlphaNumeric::CHARS . ']+$#', $string)) {
            return Version::MODE_ALPHANUMERIC;
        }
        return Version::MODE_BYTE;
    }

    protected function addFinderPatterns(Bitmap $bitmap) : void
    {
        $size = $bitmap->getSize();
        $patterns = [
            // top left
            [ 0, 0 ],
            // top right
            [ 0, $size - self::FINDER_SIZE ],
            // bottom left
            [ $size - self::FINDER_SIZE, 0 ]
        ];
        // fill finder and spacers
        foreach ($patterns as $pattern) {
            list($x, $y) = $pattern;
            for ($r = -1; $r <= self::FINDER_SIZE; $r++) {
                if (($x + $r) < 0 || ($x + $r) >= $size)
                    continue;
                for ($c = -1; $c <= self::FINDER_SIZE; $c++) {
                    if (($y + $c) < 0 || ($y + $c) >= $size)
                        continue;
                    if (($r >= 0 && $r <= 6 && ($c === 0 || $c === 6)) ||
                        ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6)) ||
                        ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4))
                    {
                        $bitmap->set($x + $r, $y + $c, true, true);
                    } else {
                        $bitmap->set($x + $r, $y + $c, false, true);
                    }
                }
            }
        }
    }

    protected function addAlignmentPatterns(Bitmap $bitmap) : void
    {
        $positions = Version::getAlignmentPositions($this->version);
        foreach ($positions as $position) {
            list($row, $col) = $position;

            for ($r = -2; $r <= 2; $r++) {
                for ($c = -2; $c <= 2; $c++) {
                    if (($r === -2 || $r === 2 || $c === -2 || $c === 2) || ($r === 0 && $c === 0)) {
                        $bitmap->set($row + $r, $col + $c, true, true);
                    } else {
                        $bitmap->set($row + $r, $col + $c, false, true);
                    }
                }
            }
        }
    }

    protected function addTimingPatterns(Bitmap $bitmap) : void
    {
        for ($i = self::FINDER_SIZE - 1; $i < $bitmap->getSize(); $i++) {
            $bitmap->set($i, self::FINDER_SIZE - 1, ($i % 2 === 0), true);
            $bitmap->set(self::FINDER_SIZE - 1, $i, ($i % 2 === 0), true);
        }
    }

    protected function addDarkModule(Bitmap $bitmap) : void
    {
        $bitmap->set(4 * $this->version + 9, 8, true, true);
    }

    protected function addVersionInfo(Bitmap $bitmap) : void
    {
        $bits = Version::getVersionBits($this->version);
        if (!$bits) {
            return;
        }
        for ($i = 0; $i < 18; $i++) {
            $row = \intval(\floor($i / 3));
            $col = $i % 3 + $bitmap->getSize() - 8 - 3;
            $value = (($bits >> $i) & 1) === 1;

            $bitmap->set($row, $col, $value, true);
            $bitmap->set($col, $row, $value, true);
        }
    }

    protected function maskFormatArea(Bitmap $bitmap, bool $reserved = true) : void
    {
        $size = $bitmap->getSize();
        for ($i = 0; $i < 15; $i++) {
            if ($i < 6) {
                $bitmap->markReserved($i, 8, $reserved);
            } else if ($i < 8) {
                $bitmap->markReserved($i + 1, 8, $reserved);
            } else {
                $bitmap->markReserved($size - 15 + $i, 8, $reserved);
            }
            if ($i < 8) {
                $bitmap->markReserved(8, $size - $i - 1, $reserved);
            } else if ($i < 9) {
                $bitmap->markReserved(8, 15 - $i, $reserved);
            } else {
                $bitmap->markReserved(8, 15 - $i - 1, $reserved);
            }
        }
        $bitmap->markReserved($size - 8, 8, $reserved);
    }

    protected function addData(Bitmap $bitmap, array $data) : void
    {
        $size = $bitmap->getSize();
        $increment = -1;
        $row = $size - 1;
        $bitIndex = 0;
        $byteIndex = 0;

        for ($col = $size - 1; $col >= 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            while (true) {
                for ($c = 0; $c < 2; $c++) {
                    $colOffset = $col - $c;
                    if ($bitmap->isReserved($row, $colOffset)) {
                        continue;
                    }
                    $active = (\intval($data[$byteIndex][$bitIndex]) & 1) === 1;
                    $bitmap->set($row, $colOffset, $active);
                    $bitIndex++;
                    if ($bitIndex > 7) {
                        $byteIndex++;
                        $bitIndex = 0;
                    }
                }
                $row += $increment;
                if ($row < 0 || $size <= $row) {
                    $row -= $increment;
                    $increment = -$increment;
                    break;
                }
            }
        }
    }

    protected function addFormatInfo(Bitmap $bitmap, int $maskPattern) : void
    {
        $bits = Version::getEncodedBits(Version::getEcLevelBit($this->ecLevel), $maskPattern);
        $size = $bitmap->getSize();

        for ($i = 0; $i < 15; $i++) {
            $mod = (($bits >> $i) & 1) === 1;

            if ($i < 6) {
                $bitmap->set($i, 8, $mod, true);
            } else if ($i < 8) {
                $bitmap->set($i + 1, 8, $mod, true);
            } else {
                $bitmap->set($size - 15 + $i, 8, $mod, true);
            }

            if ($i < 8) {
                $bitmap->set(8, $size - $i - 1, $mod, true);
            } else if ($i < 9) {
                $bitmap->set(8, 15 - $i - 1 + 1, $mod, true);
            } else {
                $bitmap->set(8, 15 - $i - 1, $mod, true);
            }
        }

        $bitmap->set($size - 8, 8, true, true);
    }
}
