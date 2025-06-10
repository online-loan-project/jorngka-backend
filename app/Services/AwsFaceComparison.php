<?php

namespace App\Services;

use Aws\Rekognition\RekognitionClient;
use Aws\Exception\AwsException;
use Illuminate\Support\Facades\Log;

class AwsFaceComparison
{
    protected $client;

    public function __construct()
    {
        $this->client = new RekognitionClient([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
        ]);
    }

    public function compareFaces(
        string $sourceImagePath,
        string $targetImagePath,
        float $similarityThreshold = 80,
        bool $drawBoundingBoxes = true,
        string $storagePath = 'face-comparisons/'
    ): array {
        try {
            $sourceImage = file_get_contents($sourceImagePath);
            $targetImage = file_get_contents($targetImagePath);

            if (!$sourceImage || !$targetImage) {
                throw new \Exception('Could not load one or both images');
            }

            $result = $this->client->compareFaces([
                'SimilarityThreshold' => $similarityThreshold,
                'SourceImage' => ['Bytes' => $sourceImage],
                'TargetImage' => ['Bytes' => $targetImage],
            ]);

            $faceMatches = $result->get('FaceMatches');
            $unmatchedFaces = $result->get('UnmatchedFaces');
            $sourceFaceDetails = $result->get('SourceImageFace');

            // Initialize response array
            $response = [
                'verified' => false,
                'similarity_score' => 0,
                'threshold_used' => $similarityThreshold,
                'annotated_source_url' => null,
                'annotated_target_url' => null,
            ];

            if (empty($faceMatches)) {
                $response = array_merge($response, [
                    'message' => 'Faces do not match',
                    'face_details' => [
                        'source' => $sourceFaceDetails ?? null,
                        'target' => $unmatchedFaces[0] ?? null,
                    ],
                    'landmarks' => [],
                    'pose' => [],
                    'quality' => [],
                ]);
            } else {
                $bestMatch = $faceMatches[0];
                $verified = $bestMatch['Similarity'] >= $similarityThreshold;

                $response = array_merge($response, [
                    'verified' => $verified,
                    'similarity_score' => $bestMatch['Similarity'],
                    'face_details' => [
                        'source' => $sourceFaceDetails,
                        'target' => $bestMatch['Face'],
                    ],
                    'landmarks' => $bestMatch['Face']['Landmarks'] ?? [],
                    'pose' => $bestMatch['Face']['Pose'] ?? [],
                    'quality' => $bestMatch['Face']['Quality'] ?? [],
                ]);
            }

            // Add bounding boxes if requested
            if ($drawBoundingBoxes) {
                try {
                    // Annotate source image
                    $response['annotated_source_url'] = $this->saveImageWithBoundingBoxes(
                        $sourceImagePath,
                        $sourceFaceDetails,
                        $storagePath,
                        'source_'
                    );

                    // Annotate target image (use matched face or first unmatched face)
                    $targetFace = !empty($faceMatches) ? $bestMatch['Face'] : ($unmatchedFaces[0] ?? null);
                    $response['annotated_target_url'] = $this->saveImageWithBoundingBoxes(
                        $targetImagePath,
                        $targetFace,
                        $storagePath,
                        'target_'
                    );
                } catch (\Exception $e) {
                    Log::error('Bounding box image creation failed: ' . $e->getMessage());
                    // Don't fail the whole request because of this
                }
            }

            return $response;

        } catch (AwsException $e) {
            Log::error('AWS Rekognition Error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'AWS Service Error: ' . $e->getAwsErrorMessage(),
                'code' => $e->getAwsErrorCode(),
            ];
        } catch (\Exception $e) {
            Log::error('Face Comparison Error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    protected function saveImageWithBoundingBoxes(
        string $imagePath,
        ?array $faceDetails,
        string $storagePath,
        string $prefix = ''
    ): ?string {
        if (!$faceDetails || !isset($faceDetails['BoundingBox'])) {
            return null;
        }

        // Load the image
        $image = imagecreatefromstring(file_get_contents($imagePath));
        if (!$image) {
            throw new \Exception('Could not create image from file');
        }

        // Get image dimensions
        $width = imagesx($image);
        $height = imagesy($image);

        // Allocate colors
        $red = imagecolorallocate($image, 255, 0, 0);
        $green = imagecolorallocate($image, 0, 255, 0);
        $white = imagecolorallocate($image, 255, 255, 255);

        // Draw bounding box
        $box = $faceDetails['BoundingBox'];
        $left = $box['Left'] * $width;
        $top = $box['Top'] * $height;
        $boxWidth = $box['Width'] * $width;
        $boxHeight = $box['Height'] * $height;

        // Draw rectangle (thicker border)
        $thickness = 3;
        for ($i = 0; $i < $thickness; $i++) {
            imagerectangle(
                $image,
                $left + $i,
                $top + $i,
                $left + $boxWidth - $i,
                $top + $boxHeight - $i,
                $green
            );
        }

        // Add label
        $label = "Face";
        $fontSize = 5; // 1-5 for built-in fonts
        $padding = 5;
        $textWidth = imagefontwidth($fontSize) * strlen($label);
        $textHeight = imagefontheight($fontSize);

        // Draw background for text
        imagefilledrectangle(
            $image,
            $left,
            $top - $textHeight - 2 * $padding,
            $left + $textWidth + 2 * $padding,
            $top,
            $green
        );

        // Draw text
        imagestring(
            $image,
            $fontSize,
            $left + $padding,
            $top - $textHeight - $padding,
            $label,
            $white
        );

        // Save the image
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }

        $filename = $prefix . uniqid() . '.jpg';
        $fullPath = $storagePath . $filename;

        imagejpeg($image, $fullPath, 90); // 90% quality
        imagedestroy($image);

        // Return public URL (adjust based on your storage setup)
        return asset($fullPath);
    }
}