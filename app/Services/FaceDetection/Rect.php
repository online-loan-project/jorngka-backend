<?php

namespace App\Services\FaceDetection;

class Rect
{
    public $x1;
    public $x2;
    public $y1;
    public $y2;
    public $weight;

    public function __construct($x1, $x2, $y1, $y2, $weight)
    {
        $this->x1 = $x1;
        $this->x2 = $x2;
        $this->y1 = $y1;
        $this->y2 = $y2;
        $this->weight = $weight;
    }

    public static function fromString($text)
    {
        $tab = explode(" ", $text);
        $x1 = intval($tab[0]);
        $x2 = intval($tab[1]);
        $y1 = intval($tab[2]);
        $y2 = intval($tab[3]);
        $f = floatval($tab[4]);

        return new Rect($x1, $x2, $y1, $y2, $f);
    }
}