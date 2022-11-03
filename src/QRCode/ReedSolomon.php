<?php
declare(strict_types=1);
/**
 * @author Geoff Davis <gef.davis@gmail.com>
 */

namespace QRCode;

class ReedSolomon
{
    private Polynomial $polynomial;
    private array $polyGenerated;
    private int $degree;

    public function __construct(int $degree)
    {
        $this->degree = $degree;
        $this->polynomial = new Polynomial();
        $this->polyGenerated = $this->polynomial->generate($degree);
    }

    public function encode(array $data) : array
    {
        $padded = \array_merge($data, \array_fill(0, $this->degree, 0));

        $remain = $this->polynomial->mod($padded, $this->polyGenerated);

        $offset = $this->degree - \count($remain);
        if ($offset > 0) {
            $data = \array_merge($remain, \array_slice(\array_fill(0, $this->degree, 0), \count($data)));
        } else {
            $data = $remain;
        }

        return $data;
    }
}
