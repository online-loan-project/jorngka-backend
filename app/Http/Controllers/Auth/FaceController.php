<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Traits\FaceComparison;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    use FaceComparison;

    /**
     * Initialize face comparison models when controller is instantiated
     */
    public function __construct()
    {
        $this->initFaceComparison();
    }

    /**
     * Detect faces in a single image
     */
    public function detectFaces(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'required', // Max 5MB
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->errors(), 'Face Detect', 'Face Detect Fail',422);
        }

        try {
            // Store the image
            $imagePath = $request->file('image')->store('temp-faces', 'public');
            $fullPath = storage_path('app/public/' . $imagePath);

            // Detect faces
            $faceData = $this->detectAndAlignFace($fullPath);

            if (!$faceData) {
                return $this->failed(null, 'No faces detected in the image', 'No faces detected in the image', 400);
            }

            // Clean up temporary file
            Storage::disk('public')->delete($imagePath);

            return $this->success([
                'face_count' => 1, // For single face detection
                'faces' => [
                    [
                        'bounding_box' => $faceData['bounding_box'],
                        'landmarks' => $faceData['landmarks'],
                        'image_size' => getimagesize($fullPath),
                    ]
                ]
            ], 'Face detection', 'Face detection completed');

        } catch (\Exception $e) {
            // Clean up if error occurs
            if (isset($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            return $this->failed($e->getMessage(), 'Error', $e->getMessage());
        }
    }

    /**
     * Compare faces from two images for eKYC verification
     */
    public function compareFaces(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_card_image' => 'required',
            'face_image' => 'required',
            'min_similarity' => 'sometimes|numeric|between:0,1',
        ]);

        if ($validator->fails()) {
            return $this->failed($validator->errors(), 'Error', 'Validate Fail', 422);
        }

        try {
            // Handle ID card image (could be file or URL)
            if ($request->hasFile('id_card_image')) {
                $idCardPath = $request->file('id_card_image')->store('temp-faces', 'public');
                $fullIdCardPath = storage_path('app/public/' . $idCardPath);
            } else {
                // Handle URL case
                $idCardUrl = $request->input('id_card_image');
                $idCardPath = $this->downloadImageFromUrl($idCardUrl, 'temp-faces');
                $fullIdCardPath = storage_path('app/public/' . $idCardPath);
            }

            // Handle face image (could be file or URL)
            if ($request->hasFile('face_image')) {
                $selfiePath = $request->file('face_image')->store('temp-faces', 'public');
                $fullSelfiePath = storage_path('app/public/' . $selfiePath);
            } else {
                // Handle URL case
                $selfieUrl = $request->input('face_image');
                $selfiePath = $this->downloadImageFromUrl($selfieUrl, 'temp-faces');
                $fullSelfiePath = storage_path('app/public/' . $selfiePath);
            }

            // Compare faces
            $result = $this->compareFacesForEKYC($fullIdCardPath, $fullSelfiePath);

            // Clean up files
            Storage::disk('public')->delete([$idCardPath, $selfiePath]);

            if (!$result['success']) {
                return $this->failed($result['message'], 'Error', 'Error', 400);
            }

            return $this->success([
                'verified' => $result['verified'],
                'similarity_score' => round($result['similarity'], 4),
                'threshold_used' => $this->similarityThreshold,
                'id_card_face' => $result['face1']['bounding_box'],
                'face_photo' => $result['face2']['bounding_box'],
                'result' => $result,
            ], 'Face comparison', 'Face comparison completed');

        } catch (\Exception $e) {
            // Clean up if error occurs
            if (isset($idCardPath)) {
                Storage::disk('public')->delete([$idCardPath, $selfiePath]);
            }
            return $this->failed($e->getMessage(),'Error', 'Error', 500);
        }
    }

    /**
     * Download image from URL and store it in public storage
     */
    protected function downloadImageFromUrl($url, $directory)
    {
        // Extract the path after /storage/
        $path = parse_url($url, PHP_URL_PATH);
        $storagePath = str_replace('/storage/', '', $path);

        // Check if file exists in storage
        if (Storage::disk('public')->exists($storagePath)) {
            $filename = pathinfo($storagePath, PATHINFO_BASENAME);
            $newPath = $directory . '/' . $filename;

            // Copy the file to temp location
            Storage::disk('public')->copy($storagePath, $newPath);

            return $newPath;
        }

        throw new \Exception("Image not found in storage");
    }
}
