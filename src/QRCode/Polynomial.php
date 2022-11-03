<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode;

use \SplFixedArray;

class Polynomial
{
    private GaloisField $field;

    public function __construct()
    {
        $this->field = new GaloisField();
    }

    public function generate(int $deg) : array
    {
        $poly = [1];
        for ($i = 0; $i < $deg; $i++) {
            $poly = $this->mul($poly, [1, $this->field->exp($i)]);
        }

        return $poly;
    }

    public function mul(array $polyA, array $polyB) : array
    {
        $result = SplFixedArray::fromArray(
            \array_fill(0, \count($polyA) + \count($polyB) - 1, 0)
        );

        for ($i = 0; $i < \count($polyA); $i++) {
            for ($j = 0; $j < \count($polyB); $j++) {
                $result[$i + $j] ^= $this->field->mul($polyA[$i], $polyB[$j]);
            }
        }

        return $result->toArray();
    }

    public function mod(array $source, array $divisor) : array
    {
        $result = $source;
        while ((\count($result) - \count($divisor)) >= 0) {
            $c = $result[0];
            for ($i = 0; $i < \count($divisor); $i++) {
                $result[$i] ^= $this->field->mul($divisor[$i], $c);
            }

            while ($result[0] === 0) {
                \array_shift($result);
            }
        }

        return $result;
    }
}
