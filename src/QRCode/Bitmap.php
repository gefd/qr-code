<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode;

use SplFixedArray;

class Bitmap
{
    private int $size;
    private array $data;

    private SplFixedArray $map;
    private SplFixedArray $preserve;

    public function __construct(int $size, array $data)
    {
        $this->size = $size;
        $this->data = $data;

        $this->map = SplFixedArray::fromArray(\array_fill(0, $size * $size, false));
        $this->preserve = SplFixedArray::fromArray(\array_fill(0, $size * $size, false));
    }

    public function ascii() : string
    {
        $string = '';
        for ($i = 0; $i < $this->size; $i++) {
            for ($j = 0; $j < $this->size; $j++) {
                $string .= \is_null($this->get($i, $j))
                    ? ($this->isReserved($i, $j) ? '?' : '!')
                    : ($this->get($i, $j) ? '#' : '.');
            }
            $string .= PHP_EOL;
        }

        return $string;
    }

    public function getSize() : int
    {
        return $this->size;
    }

    public function getOffset(int $offset) : bool
    {
        return (bool)$this->map[$offset];
    }

    protected function offset(int $row, int $col) : int
    {
        return $row * $this->size + $col;
    }

    public function get(int $row, int $col) : bool
    {
        return (bool)$this->map[$this->offset($row, $col)];
    }

    public function set(int $row, int $col, bool $value, bool $preserve = false) : void
    {
        $offset = $this->offset($row, $col);
        if (!$this->preserve[$offset]) {
            $this->map[$offset] = $value;
        }
        $this->preserve[$offset] = $preserve;
    }

    public function xor(int $row, int $col, bool $value) : void
    {
        $offset = $this->offset($row, $col);
        if (!$this->preserve[$offset]) {
            $this->map[$offset] ^= $value;
        }
    }

    public function getCount() : int
    {
        return \count($this->map);
    }

    public function getDarkCount() : int
    {
        return \array_sum($this->map->toArray());
    }

    public function markReserved(int $row, int $col, bool $reserved) : self
    {
        $this->preserve[$this->offset($row, $col)] = $reserved;

        return $this;
    }

    public function isReserved(int $row, int $col) : bool
    {
        return $this->preserve[$this->offset($row, $col)];
    }
}
