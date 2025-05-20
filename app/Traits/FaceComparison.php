<?php

namespace App\Traits;

use Exception;
use InvalidArgumentException;

trait FaceComparison
{
    private $faceDetectionModel;
    private $faceRecognitionModel;
    private $similarityThreshold = 0.75; // Increased threshold for stricter verification
    private $minFaceSize = 30; // Increased minimum face size
    private $defaultDetectionModelPath;
    private $defaultRecognitionModelPath;

    /**
     * Initialize face comparison models
     *
     * @param string $detectionModelPath Path to face detection model
     * @param string $recognitionModelPath Path to face recognition model
     */
    public function initFaceComparison($detectionModelPath = null, $recognitionModelPath = null)
    {
        // Initialize face detection model (MTCNN is more accurate than Haar cascades)
        $this->faceDetectionModel = $this->loadDetectionModel($detectionModelPath);

        // Initialize face recognition model (FaceNet or similar)
        $this->faceRecognitionModel = $this->loadRecognitionModel($recognitionModelPath);
    }

    /**
     * Compare faces from two images for eKYC verification
     *
     * @param string $image1Path Path to first image
     * @param string $image2Path Path to second image
     * @return array Result with similarity score and verification status
     * @throws Exception
     */
    public function compareFacesForEKYC($image1Path, $image2Path)
    {
        // Validate input images
        $this->validateImage($image1Path);
        $this->validateImage($image2Path);

        // Detect and align faces
        $face1 = $this->detectAndAlignFace($image1Path);
        $face2 = $this->detectAndAlignFace($image2Path);

        if (!$face1 || !$face2) {
            return [
                'success' => false,
                'message' => !$face1 ? 'No face detected in first image' : 'No face detected in second image',
                'similarity' => 0,
                'verified' => false
            ];
        }

        // Get face embeddings
        $embedding1 = $this->getFaceEmbedding($face1);
        $embedding2 = $this->getFaceEmbedding($face2);

        // Calculate similarity
        $similarity = $this->cosineSimilarity($embedding1, $embedding2);

        // Determine verification status
        $verified = $similarity >= $this->similarityThreshold;

        return [
            'success' => true,
            'similarity' => $similarity,
            'verified' => $verified,
            'threshold' => $this->similarityThreshold,
            'face1' => $face1,
            'face2' => $face2
        ];
    }

    /**
     * Set similarity threshold for verification
     *
     * @param float $threshold New threshold value (0-1)
     */
    public function setSimilarityThreshold($threshold)
    {
        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Threshold must be between 0 and 1');
        }
        $this->similarityThreshold = $threshold;
    }

    /**
     * Detect, align and crop face from image
     *
     * @param string $imagePath Path to image file
     * @return array|null Array with face image and metadata or null if no face detected
     */
    private function detectAndAlignFace($imagePath)
    {
        // Load image
        $image = $this->loadImage($imagePath);

        // Detect faces using MTCNN or similar accurate detector
        $faces = $this->faceDetectionModel->detect($image);

        if (empty($faces)) {
            return null;
        }

        // Get the largest face (assuming this is the primary subject)
        $primaryFace = $this->getLargestFace($faces);

        // Skip if face is too small
        if ($primaryFace['width'] < $this->minFaceSize || $primaryFace['height'] < $this->minFaceSize) {
            return null;
        }

        // Align face (important for recognition)
        $alignedFace = $this->alignFace($image, $primaryFace);

        return [
            'image' => $alignedFace,
            'bounding_box' => $primaryFace,
            'landmarks' => $primaryFace['landmarks'] ?? null
        ];
    }

    /**
     * Get face embedding (feature vector)
     *
     * @param array $face Array containing aligned face image
     * @return array Face embedding vector
     */
    private function getFaceEmbedding($face)
    {
        return $this->faceRecognitionModel->embed($face['image']);
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $vec1 First vector
     * @param array $vec2 Second vector
     * @return float Similarity score (0-1)
     */
    private function cosineSimilarity($vec1, $vec2)
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vec1 as $i => $val) {
            $dotProduct += $val * $vec2[$i];
            $normA += $val * $val;
            $normB += $vec2[$i] * $vec2[$i];
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    /**
     * Get the largest face from detected faces
     *
     * @param array $faces Array of detected faces
     * @return array Largest face data
     */
    private function getLargestFace($faces)
    {
        $largest = null;
        $maxArea = 0;

        foreach ($faces as $face) {
            $area = $face['width'] * $face['height'];
            if ($area > $maxArea) {
                $maxArea = $area;
                $largest = $face;
            }
        }

        return $largest;
    }

    /**
     * Align face based on landmarks
     *
     * @param resource $image Image resource
     * @param array $face Face data with landmarks
     * @return resource Aligned face image
     */
    private function alignFace($image, $face)
    {
        // This would use facial landmarks to align the face properly
        // Implementation depends on the face detection model used

        // For now, just crop the face region
        $cropped = imagecrop($image, [
            'x' => $face['x'],
            'y' => $face['y'],
            'width' => $face['width'],
            'height' => $face['height']
        ]);

        return $cropped;
    }

    /**
     * Validate image file
     *
     * @param string $imagePath Path to image file
     * @throws Exception
     */
    private function validateImage($imagePath)
    {
        if (!file_exists($imagePath)) {
            throw new Exception("Image file not found: " . $imagePath);
        }

        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            throw new Exception("Could not read image: " . $imagePath);
        }
    }

    /**
     * Load image from file
     *
     * @param string $imagePath Path to image file
     * @return resource Image resource
     * @throws Exception
     */
    private function loadImage($imagePath)
    {
        $imageInfo = getimagesize($imagePath);
        $imageType = $imageInfo[2];

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($imagePath);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($imagePath);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($imagePath);
            default:
                throw new Exception("Unsupported image format: " . $imageType);
        }
    }

    /**
     * Load face detection model
     *
     * @param string|null $modelPath Path to model file
     * @return object Face detection model
     */
    private function loadDetectionModel($modelPath = null)
    {
        // In a real implementation, this would load a pre-trained model
        // For this example, we'll return a mock object

        return new class {
            public function detect($image) {
                // Mock detection - in real implementation this would use MTCNN or similar
                $width = imagesx($image);
                $height = imagesy($image);

                // Return a mock face detection
                return [
                    [
                        'x' => $width * 0.2,
                        'y' => $height * 0.2,
                        'width' => $width * 0.6,
                        'height' => $height * 0.6,
                        'confidence' => 0.99,
                        'landmarks' => [
                            'left_eye' => ['x' => $width * 0.3, 'y' => $height * 0.4],
                            'right_eye' => ['x' => $width * 0.7, 'y' => $height * 0.4],
                            'nose' => ['x' => $width * 0.5, 'y' => $height * 0.5],
                            'mouth_left' => ['x' => $width * 0.4, 'y' => $height * 0.7],
                            'mouth_right' => ['x' => $width * 0.6, 'y' => $height * 0.7],
                        ]
                    ]
                ];
            }
        };
    }

    /**
     * Load face recognition model
     *
     * @param string|null $modelPath Path to model file
     * @return object Face recognition model
     */
    private function loadRecognitionModel($modelPath = null)
    {
        // In a real implementation, this would load a pre-trained model like FaceNet
        // For this example, we'll return a mock object

        return new class {
            public function embed($faceImage) {
                // Mock embedding - in real implementation this would generate a 128/512-dim vector
                $embedding = [];
                for ($i = 0; $i < 128; $i++) {
                    $embedding[] = mt_rand() / mt_getrandmax(); // Random values for demo
                }
                return $embedding;
            }
        };
    }
}