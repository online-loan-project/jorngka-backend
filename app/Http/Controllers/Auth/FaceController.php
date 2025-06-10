<?php

namespace App\Http\Controllers\Auth;

use App\Constants\ConstUserRole;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Borrower;
use App\Models\NidInformation;
use App\Services\AwsFaceComparison;
use App\Traits\FaceComparison;
use App\Traits\OpenCV;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class FaceController extends Controller
{

    /**
     * Compare faces from two images for eKYC verification
     */
    public function compareFaces(Request $request)
    {
        $user = auth()->user();
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

            $threshold = $request->input('min_similarity', 80);

            $comparisonService = new AwsFaceComparison();
            $result = $comparisonService->compareFaces($fullIdCardPath, $fullSelfiePath, $threshold, true);

            // Clean up files
            Storage::disk('public')->delete([$idCardPath, $selfiePath]);

            if ($result['verified']) {
                // update face_verified_at user
                $user->face_verified_at = now();
                $user->save();
            }

            $profile = null;
            //check $user->role if admin or borrower so join the table
            if ($user->role == ConstUserRole::BORROWER) {
                $profile = Borrower::query()->where('user_id', $user->id)->first();
            }

            if ($user->role == ConstUserRole::ADMIN) {
                $profile = Admin::query()->where('user_id', $user->id)->first();
            }

            //add $profile to user
            $user->profile = $profile;
            $user->role = (int) $user->role;
            $user->status = (int) $user->status;

            $nid_information = NidInformation::query()
                ->where('user_id', $user->id)
                ->where('status', 1)
                ->first();

            return $this->success([
                'user' => $user,
                'result' => $result,
                'nid' => $nid_information
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
