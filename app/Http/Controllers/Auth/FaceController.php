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
            return $this->failed($validator->errors(), 'Error', 'Validate Fail',422);
        }

        try {
            // Store images temporarily
            $idCardPath = $request->file('id_card_image')->store('temp-faces', 'public');
            $selfiePath = $request->file('face_image')->store('temp-faces', 'public');

            $fullIdCardPath = storage_path('app/public/' . $idCardPath);
            $fullSelfiePath = storage_path('app/public/' . $selfiePath);

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
            ], 'Face comparison', 'Face comparison completed');

        } catch (\Exception $e) {
            // Clean up if error occurs
            if (isset($idCardPath)) {
                Storage::disk('public')->delete([$idCardPath, $selfiePath]);
            }
            return $this->failed($e->getMessage(),'Error', 'Error', 500);
        }
    }
}
