<?php

namespace App\Traits;

use Exception;

trait FaceCompare
{
    private $classifierSize;
    private $stages;
    private $image;
    private $width;
    private $height;
    private $foundRects;

    /**
     * Initialize the face detector with classifier
     *
     * @param string $classifierFile Path to classifier file
     * @throws Exception
     */
    public function initFaceDetector($classifierFile = null)
    {
        if (is_null($classifierFile)) {
            $classifierFile = public_path('haarcascade_frontalface_default.xml');

            // Copy default classifier if not exists
            if (!file_exists($classifierFile)) {
                $defaultClassifier = file_get_contents('https://github.com/opencv/opencv/raw/master/data/haarcascades/haarcascade_frontalface_default.xml');
                file_put_contents($classifierFile, $defaultClassifier);
            }
        }

        $this->initClassifier($classifierFile);
    }

    private function initClassifier($classifierFile)
    {
        if (!file_exists($classifierFile)) {
            throw new Exception("Classifier file not found: ".$classifierFile);
        }

        $xmls = file_get_contents($classifierFile);
        $xmls = preg_replace("/<!--[\S|\s]*?-->/", "", $xmls);
        $xml = simplexml_load_string($xmls);

        $this->classifierSize = explode(" ", strval($xml->children()->children()->size));
        $this->stages = array();

        $stagesNode = $xml->children()->children()->stages;

        foreach($stagesNode->children() as $stageNode)
        {
            $stage = new \App\Services\FaceDetection\Stage(floatval($stageNode->stage_threshold));

            foreach($stageNode->trees->children() as $treeNode)
            {
                $feature = new \App\Services\FaceDetection\Feature(
                    floatval($treeNode->_->threshold),
                    floatval($treeNode->_->left_val),
                    floatval($treeNode->_->right_val),
                    $this->classifierSize
                );

                foreach($treeNode->_->feature->rects->_ as $r)
                {
                    $feature->add(\App\Services\FaceDetection\Rect::fromString(strval($r)));
                }

                $stage->features[] = $feature;
            }

            $this->stages[] = $stage;
        }
    }

    /**
     * Detect faces in given image
     *
     * @param string $imageFile path of image file
     * @throws Exception
     */
    public function scanImage($imageFile)
    {
        $imageInfo = getimagesize($imageFile);

        if(!$imageInfo)
        {
            throw new Exception("Could not open file: ".$imageFile);
        }

        $this->width = $imageInfo[0];
        $this->height = $imageInfo[1];
        $imageType = $imageInfo[2];

        if( $imageType == IMAGETYPE_JPEG )
        {
            $this->image = imagecreatefromjpeg($imageFile);
        }
        elseif( $imageType == IMAGETYPE_GIF )
        {
            $this->image = imagecreatefromgif($imageFile);
        }
        elseif( $imageType == IMAGETYPE_PNG )
        {
            $this->image = imagecreatefrompng($imageFile);
        }
        else
        {
            throw new Exception("Unknown Fileformat: ".$imageType.", ".$imageFile);
        }

        $this->foundRects = array();

        $maxScale = min($this->width/$this->classifierSize[0], $this->height/$this->classifierSize[1]);
        $grayImage = array_fill(0, $this->width, array_fill(0, $this->height, null));
        $img = array_fill(0, $this->width, array_fill(0, $this->height, null));
        $squares = array_fill(0, $this->width, array_fill(0, $this->height, null));

        for($i = 0; $i < $this->width; $i++)
        {
            $col=0;
            $col2=0;
            for($j = 0; $j < $this->height; $j++)
            {
                $colors = imagecolorsforindex($this->image, imagecolorat($this->image, $i, $j));

                $value = (30*$colors['red'] +59*$colors['green'] +11*$colors['blue'])/100;
                $img[$i][$j] = $value;
                $grayImage[$i][$j] = ($i > 0 ? $grayImage[$i-1][$j] : 0) + $col + $value;
                $squares[$i][$j]=($i > 0 ? $squares[$i-1][$j] : 0) + $col2 + $value*$value;
                $col += $value;
                $col2 += $value*$value;
            }
        }

        $baseScale = 2;
        $scale_inc = 1.25;
        $increment = 0.1;
        $min_neighbors = 3;

        for($scale = $baseScale; $scale < $maxScale; $scale *= $scale_inc)
        {
            $step = (int)($scale*24*$increment);
            $size = (int)($scale*24);

            for($i = 0; $i < $this->width-$size; $i += $step)
            {
                for($j = 0; $j < $this->height-$size; $j += $step)
                {
                    $pass = true;
                    $k = 0;
                    foreach($this->stages as $s)
                    {

                        if(!$s->pass($grayImage, $squares, $i, $j, $scale))
                        {
                            $pass = false;
                            break;
                        }
                        $k++;
                    }
                    if($pass)
                    {
                        $this->foundRects[]= array("x" => $i, "y" => $j, "width" => $size, "height" => $size);
                    }
                }
            }
        }
    }

    /**
     * Return array of found faces.
     *
     * Each face is represented by an associative array with the keys x, y, width and hight.
     *
     * @param bool $moreConfidence desire more confidence what a face is, gives less results
     * @return array found faces
     */
    public function getDetectedFaces($moreConfidence = false)
    {
        return $this->mergeRects($this->foundRects, 2 + intval($moreConfidence));
    }

    /**
     * Get image with found faces marked
     *
     * @param string $fileName filename to save on disk
     * @param bool $moreConfidence desire more confidence what a face is, gives less results
     * @param bool $showAllRects mark all faces before merging, for debugging purposes
     * @return bool|resource if filename given, image will be saved to disk, otherwise image ressource
     * @throws Exception
     */
    public function getProcessedImage($fileName = null, $moreConfidence = false, $showAllRects = false)
    {
        $canvas = imagecreatetruecolor($this->width, $this->height);
        imagecopyresampled($canvas, $this->image, 0, 0, 0, 0, $this->width, $this->height, $this->width, $this->height);

        $blue = imagecolorallocate($canvas, 0, 0, 255);
        $red = imagecolorallocate($canvas, 255, 0, 0);

        if($showAllRects)
        {
            foreach($this->foundRects as $r)
            {
                imagerectangle( $canvas, $r['x'], $r['y']  , $r['x']+$r['width']  , $r['y']+$r['height'], $blue);
            }
        }

        $rects = $this->mergeRects($this->foundRects, 2 + intval($moreConfidence));
        foreach($rects as $r)
        {
            imagerectangle( $canvas, $r['x'], $r['y']  , $r['x']+$r['width']  , $r['y']+$r['height'], $red);
        }

        if(empty($fileName))
        {
            return $canvas;
        }

        $array = explode('.', $fileName);
        $ext = strtolower(array_pop($array));

        if( $ext == "jpg" || $ext == "jpeg" )
        {
            return imagejpeg($canvas, $fileName, 100);
        }
        elseif( $ext == "gif" )
        {
            return imagegif($canvas, $fileName);
        }
        elseif( $ext == "png")
        {
            return imagepng($canvas, $fileName);
        }
        else
        {
            throw new Exception("Unknown Fileformat: ".$ext);
        }
    }

    private function mergeRects($rects, $min_neighbors)
    {
        $retour = array();
        $ret = array();
        $nb_classes = 0;

        for($i = 0; $i < count($rects); $i++)
        {
            $found = false;
            for($j = 0; $j < $i; $j++)
            {
                if($this->rectsEqual($rects[$j], $rects[$i]))
                {
                    $found = true;
                    $ret[$i] = $ret[$j];
                }
            }

            if(!$found)
            {
                $ret[$i] = $nb_classes;
                $nb_classes++;
            }
        }


        $neighbors = array();
        $rect = array();
        for($i = 0; $i < $nb_classes; $i++)
        {
            $neighbors[$i] = 0;
            $rect[$i] = array("x" => 0, "y" => 0, "width" => 0, "height" => 0);
        }

        for($i = 0; $i < count($rects); $i++)
        {
            $neighbors[$ret[$i]]++;
            $rect[$ret[$i]]['x'] += $rects[$i]['x'];
            $rect[$ret[$i]]['y'] += $rects[$i]['y'];
            $rect[$ret[$i]]['width'] += $rects[$i]['width'];
            $rect[$ret[$i]]['height'] += $rects[$i]['height'];
        }

        for($i = 0; $i < $nb_classes; $i++ )
        {
            $n = $neighbors[$i];
            if( $n >= $min_neighbors)
            {
                $r = array("x" => 0, "y" => 0, "width" => 0, "height" => 0);
                $r['x'] = ($rect[$i]['x']*2 + $n)/(2*$n);
                $r['y'] = ($rect[$i]['y']*2 + $n)/(2*$n);
                $r['width'] = ($rect[$i]['width']*2 + $n)/(2*$n);
                $r['height'] = ($rect[$i]['height']*2 + $n)/(2*$n);

                $retour[] = $r;
            }
        }
        return $retour;
    }

    private function rectsEqual($r1, $r2)
    {
        $distance = (int)($r1['width']*0.2);

        if( $r2['x'] <= $r1['x'] + $distance &&
            $r2['x'] >= $r1['x'] - $distance &&
            $r2['y'] <= $r1['y'] + $distance &&
            $r2['y'] >= $r1['y'] - $distance &&
            $r2['width'] <= (int)( $r1['width'] * 1.2 ) &&
            (int)( $r2['width'] * 1.2 ) >= $r1['width'] )
        {
            return true;
        }

        if( $r1['x'] >= $r2['x'] &&
            $r1['x'] + $r1['width'] <= $r2['x'] + $r2['width'] &&
            $r1['y'] >= $r2['y'] &&
            $r1['y'] + $r1['height'] <= $r2['y'] + $r2['height'] )
        {
            return true;
        }

        return false;
    }

    public function getImageWidth()
    {
        return $this->width;
    }

    public function getImageHeight()
    {
        return $this->height;
    }

    /**
     * Compare two faces from different images
     *
     * @param array $face1 First face data
     * @param array $face2 Second face data
     * @return array Similarity score and match status
     */
    public function compareFaces($face1, $face2)
    {
        if (empty($face1) || empty($face2)) {
            return [
                'score' => 0,
                'match' => false,
                'message' => 'Cannot compare - need face data for both images'
            ];
        }

        // Simple similarity calculation (for demonstration only)
        $widthSimilarity = 1 - abs($face1['width'] - $face2['width']) / max($face1['width'], $face2['width']);
        $heightSimilarity = 1 - abs($face1['height'] - $face2['height']) / max($face1['height'], $face2['height']);
        $positionSimilarity = 1 - (abs($face1['x'] - $face2['x']) + abs($face1['y'] - $face2['y'])) /
            ($this->getImageWidth() + $this->getImageHeight());

        $similarityScore = ($widthSimilarity + $heightSimilarity + $positionSimilarity) / 3 * 100;

        return [
            'score' => round($similarityScore, 2),
            'match' => $similarityScore > 90,
            'message' => $similarityScore > 90 ? 'Possible match' : 'Different faces'
        ];
    }
}