<?php

namespace App\Services\FaceDetection;

class Stage
{
    public $features;
    public $threshold;

    public function __construct($threshold)
    {
        $this->threshold = floatval($threshold);
        $this->features = array();
    }

    public function pass($grayImage, $squares, $i, $j, $scale)
    {
        $sum = 0;
        foreach($this->features as $f)
        {
            $sum += $f->getVal($grayImage, $squares, $i, $j, $scale);
        }

        return $sum > $this->threshold;
    }
}