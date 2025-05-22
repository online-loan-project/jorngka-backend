<?php

namespace App\Traits;

use Exception;

trait Face
{
    private $classifierSize;
    private $stages;
    private $image;
    private $width;
    private $height;
    private $foundRects;

    /**
     * Initialize the face detector with Haar Cascade file
     *
     * @param string|null $classifierFile Path to Haar Cascade XML file
     */
    public function initFaceDetection($classifierFile = null)
    {
        $defaultPath = public_path('haarcascade_frontalface_default.xml');
        $this->initClassifier($classifierFile ?: $defaultPath);
    }

    private function initClassifier($classifierFile)
    {
        if (!file_exists($classifierFile)) {
            throw new Exception("Classifier file not found: " . $classifierFile);
        }

        $xmls = file_get_contents($classifierFile);
        $xmls = preg_replace("/<!--[\S|\s]*?-->/", "", $xmls);
        $xml = simplexml_load_string($xmls);

        if ($xml === false) {
            throw new Exception("Failed to parse classifier XML file");
        }

        $this->classifierSize = explode(" ", strval($xml->children()->children()->size));
        $this->stages = [];

        $stagesNode = $xml->children()->children()->stages;

        foreach ($stagesNode->children() as $stageNode) {
            $stage = new Stage(floatval($stageNode->stage_threshold));

            foreach ($stageNode->trees->children() as $treeNode) {
                $feature = new Feature(
                    floatval($treeNode->_->threshold),
                    floatval($treeNode->_->left_val),
                    floatval($treeNode->_->right_val),
                    $this->classifierSize
                );

                foreach ($treeNode->_->feature->rects->_ as $r) {
                    $feature->add(Rect::fromString(strval($r)));
                }

                $stage->features[] = $feature;
            }

            $this->stages[] = $stage;
        }
    }

    /**
     * Detect faces in given image
     *
     * @param string $imageFile Path of image file
     * @throws Exception
     */
    public function scan($imageFile)
    {
        if (!file_exists($imageFile)) {
            throw new Exception("Image file not found: " . $imageFile);
        }

        $imageInfo = getimagesize($imageFile);

        if (!$imageInfo) {
            throw new Exception("Could not open file: " . $imageFile);
        }

        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];
        $imageType = $imageInfo[2];

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $this->image = imagecreatefromjpeg($imageFile);
                break;
            case IMAGETYPE_GIF:
                $this->image = imagecreatefromgif($imageFile);
                break;
            case IMAGETYPE_PNG:
                $this->image = imagecreatefrompng($imageFile);
                break;
            default:
                throw new Exception("Unknown File format: " . $imageType . ", " . $imageFile);
        }

        $this->foundRects = [];

        $maxScale = min($this->width / $this->classifierSize[0], $this->height / $this->classifierSize[1]);
        $grayImage = array_fill(0, $this->width, array_fill(0, $this->height, null));
        $img = array_fill(0, $this->width, array_fill(0, $this->height, null));
        $squares = array_fill(0, $this->width, array_fill(0, $this->height, null));

        for ($i = 0; $i < $this->width; $i++) {
            $col = 0;
            $col2 = 0;
            for ($j = 0; $j < $this->height; $j++) {
                $colors = imagecolorsforindex($this->image, imagecolorat($this->image, $i, $j));

                $value = (30 * $colors['red'] + 59 * $colors['green'] + 11 * $colors['blue']) / 100;
                $img[$i][$j] = $value;
                $grayImage[$i][$j] = ($i > 0 ? $grayImage[$i - 1][$j] : 0) + $col + $value;
                $squares[$i][$j] = ($i > 0 ? $squares[$i - 1][$j] : 0) + $col2 + $value * $value;
                $col += $value;
                $col2 += $value * $value;
            }
        }

        $baseScale = 2;
        $scale_inc = 1.25;
        $increment = 0.1;
        $min_neighbors = 3;

        for ($scale = $baseScale; $scale < $maxScale; $scale *= $scale_inc) {
            $step = (int)($scale * 24 * $increment);
            $size = (int)($scale * 24);

            for ($i = 0; $i < $this->width - $size; $i += $step) {
                for ($j = 0; $j < $this->height - $size; $j += $step) {
                    $pass = true;
                    foreach ($this->stages as $s) {
                        if (!$s->pass($grayImage, $squares, $i, $j, $scale)) {
                            $pass = false;
                            break;
                        }
                    }
                    if ($pass) {
                        $this->foundRects[] = [
                            "x" => $i,
                            "y" => $j,
                            "width" => $size,
                            "height" => $size
                        ];
                    }
                }
            }
        }
    }

    /**
     * Returns array of found faces.
     *
     * Each face is represented by an associative array with the keys x, y, width and height.
     *
     * @param bool $moreConfidence Desire more confidence what a face is, gives less results
     * @return array Found faces
     */
    public function getFaces($moreConfidence = false)
    {
        return $this->merge($this->foundRects, 2 + intval($moreConfidence));
    }

    /**
     * Gives access to image with found faces marked
     *
     * @param string|null $fileName Filename to save on disk
     * @param bool $moreConfidence Desire more confidence what a face is, gives less results
     * @param bool $showAllRects Mark all faces before merging, for debugging purposes
     * @return bool|resource If filename given, image will be saved to disk, otherwise image resource
     * @throws Exception
     */
    public function getImage($fileName = null, $moreConfidence = false, $showAllRects = false)
    {
        $canvas = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($canvas, $this->image, 0, 0, 0, 0, $this->width, $this->height, $this->width, $this->height);

        $blue = imagecolorallocate($canvas, 0, 0, 255);
        $red = imagecolorallocate($canvas, 255, 0, 0);

        if ($showAllRects) {
            foreach ($this->foundRects as $r) {
                imagerectangle($canvas, $r['x'], $r['y'], $r['x'] + $r['width'], $r['y'] + $r['height'], $blue);
            }
        }

        $rects = $this->merge($this->foundRects, 2 + intval($moreConfidence));
        foreach ($rects as $r) {
            imagerectangle($canvas, $r['x'], $r['y'], $r['x'] + $r['width'], $r['y'] + $r['height'], $red);
        }

        if (empty($fileName)) {
            return $canvas;
        }

        $array = explode('.', $fileName);
        $ext = strtolower(array_pop($array));

        switch ($ext) {
            case "jpg":
            case "jpeg":
                return imagejpeg($canvas, $fileName, 100);
            case "gif":
                return imagegif($canvas, $fileName);
            case "png":
                return imagepng($canvas, $fileName);
            default:
                throw new Exception("Unknown File format: " . $ext);
        }
    }

    private function merge($rects, $min_neighbors)
    {
        $retour = [];
        $ret = [];
        $nb_classes = 0;

        for ($i = 0; $i < count($rects); $i++) {
            $found = false;
            for ($j = 0; $j < $i; $j++) {
                if ($this->equals($rects[$j], $rects[$i])) {
                    $found = true;
                    $ret[$i] = $ret[$j];
                }
            }

            if (!$found) {
                $ret[$i] = $nb_classes;
                $nb_classes++;
            }
        }

        $neighbors = [];
        $rect = [];
        for ($i = 0; $i < $nb_classes; $i++) {
            $neighbors[$i] = 0;
            $rect[$i] = ["x" => 0, "y" => 0, "width" => 0, "height" => 0];
        }

        for ($i = 0; $i < count($rects); $i++) {
            $neighbors[$ret[$i]]++;
            $rect[$ret[$i]]['x'] += $rects[$i]['x'];
            $rect[$ret[$i]]['y'] += $rects[$i]['y'];
            $rect[$ret[$i]]['width'] += $rects[$i]['width'];
            $rect[$ret[$i]]['height'] += $rects[$i]['height'];
        }

        for ($i = 0; $i < $nb_classes; $i++) {
            $n = $neighbors[$i];
            if ($n >= $min_neighbors) {
                $r = [
                    "x" => ($rect[$i]['x'] * 2 + $n) / (2 * $n),
                    "y" => ($rect[$i]['y'] * 2 + $n) / (2 * $n),
                    "width" => ($rect[$i]['width'] * 2 + $n) / (2 * $n),
                    "height" => ($rect[$i]['height'] * 2 + $n) / (2 * $n)
                ];

                $retour[] = $r;
            }
        }
        return $retour;
    }

    private function equals($r1, $r2)
    {
        $delta = 0.2;

        $dx = abs($r1['x'] - $r2['x']);
        $dy = abs($r1['y'] - $r2['y']);
        $dw = abs($r1['width'] - $r2['width']);
        $dh = abs($r1['height'] - $r2['height']);

        return ($dx <= $delta * ($r1['width'] + $r2['width']) / 2 &&
            $dy <= $delta * ($r1['height'] + $r2['height']) / 2 &&
            $dw <= $delta * ($r1['width'] + $r2['width']) / 2 &&
            $dh <= $delta * ($r1['height'] + $r2['height']) / 2);
    }
}

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
        return new Rect(
            intval($tab[0]),
            intval($tab[1]),
            intval($tab[2]),
            intval($tab[3]),
            floatval($tab[4])
        );
    }
}

class Feature
{
    public $rects;
    public $threshold;
    public $left_val;
    public $right_val;
    public $size;

    public function __construct($threshold, $left_val, $right_val, $size)
    {
        $this->rects = [];
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
        $w = (int)($scale * $this->size[0]);
        $h = (int)($scale * $this->size[1]);
        $inv_area = 1 / ($w * $h);

        $total_x = $grayImage[$i + $w][$j + $h] + $grayImage[$i][$j] - $grayImage[$i][$j + $h] - $grayImage[$i + $w][$j];
        $total_x2 = $squares[$i + $w][$j + $h] + $squares[$i][$j] - $squares[$i][$j + $h] - $squares[$i + $w][$j];

        $moy = $total_x * $inv_area;
        $vnorm = $total_x2 * $inv_area - $moy * $moy;
        $vnorm = ($vnorm > 1) ? sqrt($vnorm) : 1;

        $rect_sum = 0;
        foreach ($this->rects as $r) {
            $rx1 = $i + (int)($scale * $r->x1);
            $rx2 = $i + (int)($scale * ($r->x1 + $r->y1));
            $ry1 = $j + (int)($scale * $r->x2);
            $ry2 = $j + (int)($scale * ($r->x2 + $r->y2));

            $rect_sum += (int)(($grayImage[$rx2][$ry2] - $grayImage[$rx1][$ry2] - $grayImage[$rx2][$ry1] + $grayImage[$rx1][$ry1]) * $r->weight);
        }

        $rect_sum2 = $rect_sum * $inv_area;

        return ($rect_sum2 < $this->threshold * $vnorm ? $this->left_val : $this->right_val);
    }
}

class Stage
{
    public $features;
    public $threshold;

    public function __construct($threshold)
    {
        $this->threshold = floatval($threshold);
        $this->features = [];
    }

    public function pass($grayImage, $squares, $i, $j, $scale)
    {
        $sum = 0;
        foreach ($this->features as $f) {
            $sum += $f->getVal($grayImage, $squares, $i, $j, $scale);
        }

        return $sum > $this->threshold;
    }
}