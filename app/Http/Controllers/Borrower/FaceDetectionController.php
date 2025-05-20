<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Traits\FaceCompare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FaceDetectionController extends Controller
{
    use FaceCompare;

    public function faceMatch(Request $request)
    {
        try {
            ini_set('max_execution_time', 180);
            // Validate the request
            $request->validate([
                'nid' => 'required|url',
                'liveliness' => 'required|url',
            ]);

            logger("Face Detection Request", [
                'nid' => $request->input('nid'),
                'liveliness' => $request->input('liveliness')
            ]);

            $this->initFaceDetector();

            // Generate unique identifiers for this request
            $uniqueId = uniqid();
            $timestamp = now()->format('YmdHis');

            // Ensure directories exist
            Storage::disk('local')->makeDirectory('nid');
            Storage::disk('local')->makeDirectory('liveliness');
            Storage::disk('public')->makeDirectory('nid_liveliness');

            // Download images from URLs and convert to blob
            $nidUrl = $request->input('nid');
            $livelinessUrl = $request->input('liveliness');

            // Function to download and convert image to blob
            $downloadImage = function($url) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
                $imageData = curl_exec($ch);

                if ($imageData === false) {
                    throw new \Exception("Failed to download image from URL: " . curl_error($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode !== 200) {
                    throw new \Exception("HTTP request failed with code: $httpCode");
                }

                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                if (!str_contains($contentType, 'image')) {
                    throw new \Exception("URL does not point to an image (Content-Type: $contentType)");
                }

                curl_close($ch);
                return $imageData;
            };

            // Download NID image as blob
            $nidBlob = $downloadImage($nidUrl);
            $nidFilename = "nid_{$timestamp}_{$uniqueId}.jpg";
            $storedImage1Path = 'nid/' . $nidFilename;
            Storage::disk('local')->put($storedImage1Path, $nidBlob);

            // Download Liveliness image as blob
            $livelinessBlob = $downloadImage($livelinessUrl);
            $livelinessFilename = "liveliness_{$timestamp}_{$uniqueId}.jpg";
            $storedImage2Path = 'liveliness/' . $livelinessFilename;
            Storage::disk('local')->put($storedImage2Path, $livelinessBlob);

            // Get full paths
            $fullImage1Path = Storage::disk('local')->path($storedImage1Path);
            $fullImage2Path = Storage::disk('local')->path($storedImage2Path);

            // Verify files exist before processing
            if (!file_exists($fullImage1Path)) {
                throw new \Exception("NID image not found at: ".$fullImage1Path);
            }
            if (!file_exists($fullImage2Path)) {
                throw new \Exception("Liveliness image not found at: ".$fullImage2Path);
            }

            // Create unique output filenames
            $nidOutputFilename = "nid_detected_{$timestamp}_{$uniqueId}.jpg";
            $livelinessOutputFilename = "liveliness_detected_{$timestamp}_{$uniqueId}.jpg";

            // Process images
            $this->scanImage($fullImage1Path);
            $facesNid = $this->getDetectedFaces();
            $output1 = Storage::disk('public')->path("nid_liveliness/{$nidOutputFilename}");
            $this->getProcessedImage($output1, true, false);

            $this->scanImage($fullImage2Path);
            $facesLiveliness = $this->getDetectedFaces();
            $output2 = Storage::disk('public')->path("nid_liveliness/{$livelinessOutputFilename}");
            $this->getProcessedImage($output2, true, false);

            // Compare faces if found
            $comparison = null;
            if (count($facesNid) > 0 && count($facesLiveliness) > 0) {
                $comparison = $this->compareFaces($facesNid[0], $facesLiveliness[0]);
            }

            $data = [
                'faces1' => $facesNid,
                'faces2' => $facesLiveliness,
                'output1' => Storage::url("nid_liveliness/{$nidOutputFilename}"),
                'output2' => Storage::url("nid_liveliness/{$livelinessOutputFilename}"),
                'comparison' => $comparison
            ];

            return $this->success($data, 'Face Detection', 'Faces detected and compared successfully');

        } catch (\Exception $e) {
            return $this->failed(null, 'Face Detection Error', $e->getMessage(), 500);
        }
    }
}
