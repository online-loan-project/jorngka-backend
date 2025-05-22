<?php

namespace App\Services\FaceDetection;

class Feature
{
    public $rects;
    public $threshold;
    public $left_val;
    public $right_val;
    public $size;

    public function __construct($threshold, $left_val, $right_val, $size)
    {
        $this->rects = array();
        $this->threshold = $threshold;
        $this->left_val = $left_val;
        $this->right_val = $right_val;
        $this->size = $size;
    }

    public function add(Rect $r)
    {
        $this->rects[] = $r;
    }

    public function getVal($grayImage, $squares, $i, $j, $scale)
    {
        $w = (int)($scale*$this->size[0]);
        $h = (int)($scale*$this->size[1]);
        $inv_area = 1/($w*$h);

        $total_x = $grayImage[$i+$w][$j+$h] + $grayImage[$i][$j] - $grayImage[$i][$j+$h] - $grayImage[$i+$w][$j];
        $total_x2 = $squares[$i+$w][$j+$h] + $squares[$i][$j] - $squares[$i][$j+$h] - $squares[$i+$w][$j];

        $moy = $total_x*$inv_area;
        $vnorm = $total_x2*$inv_area-$moy*$moy;
        $vnorm = ($vnorm>1) ? sqrt($vnorm) : 1;

        $rect_sum = 0;
        for($k = 0; $k < count($this->rects); $k++)
        {
            $r = $this->rects[$k];
            $rx1 = $i+(int)($scale*$r->x1);
            $rx2 = $i+(int)($scale*($r->x1 + $r->y1));
            $ry1 = $j+(int)($scale*$r->x2);
            $ry2 = $j+(int)($scale*($r->x2 + $r->y2));

            $rect_sum += (int)(($grayImage[$rx2][$ry2]-$grayImage[$rx1][$ry2]-$grayImage[$rx2][$ry1]+$grayImage[$rx1][$ry1])*$r->weight);
        }

        $rect_sum2 = $rect_sum*$inv_area;

        return ($rect_sum2 < $this->threshold*$vnorm ? $this->left_val : $this->right_val);
    }
}