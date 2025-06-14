<?php

namespace App\Http\Controllers\Borrower;

use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\Liveliness;
use App\Models\NidInformation;
use App\Traits\BaseApiResponse;
use App\Traits\TelegramNotification;
use Illuminate\Http\Request;
use App\Services\CambodianNIDService;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Image;

class NidController extends Controller
{
    use TelegramNotification, BaseApiResponse;

    // store nid information
    public function store(Request $request)
    {
        $nidService = new CambodianNIDService();

        // Validate the request
        $request->validate([
            'nid_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        $userData = auth()->user(); // Get the authenticated user

        // Check if the file is an image
        if (!$request->file('nid_image')) {
            return $this->failed(null,'Image Error', 'Invalid image file.', 422);
        }
        $optimizedImage = $this->optimizeImage($request->file('nid_image'));

        $data = $nidService->extractTextFromNID($optimizedImage); // Process the NID image
        Log::channel('nid_log')->info(
            'NID Information',
            [
                'user_id' => $userData->id,
                'nid_number' => $data['nid'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'image_path' => $request->file('nid_image')->store('nid_images', 'public'),
            ]
        );

        // if $data no nid number
        if (empty($data['nid'])) {
            $this->sendTelegram(
                $userData->telegram_chat_id,
                <<<MSG
❌  NID number not found in the image.

📞 Contact support if you have any questions.  

This is an automated message.  
MSG);
            return $this->failed(null,'OCR Error', 'NID number not found in the image. Please input a clear image.', 422);
        }

        //if first name and last name null
        if (empty($data['last_name'])) {
            $this->sendTelegram(
                $userData->telegram_chat_id,
                <<<MSG
❌  Last Name not found in the image.

📞 Contact support if you have any questions.  

This is an automated message.  
MSG);
            return $this->failed(null,'OCR Error', 'Last Name not found in the image. Please input a clear image.', 422);
        }

        if (empty($data['first_name'])) {
            $this->sendTelegram(
                $userData->telegram_chat_id,
                <<<MSG
❌  First Name not found in the image.

📞 Contact support if you have any questions.  

This is an automated message.  
MSG);
            return $this->failed(null,'OCR Error', 'First Name not found in the image. Please input a clear image.', 422);
        }

        if (!$userData) {
            return $this->failed(null,'Borrower', 'User not found.', 404);
        }
        // get borrower data from user_id
        $borrowerData = Borrower::query()->where('user_id', $userData->id)->first();
        if (!$borrowerData) {
            return $this->failed(null,'Borrower', 'Borrower not found.', 404);
        }

        // match the extracted data with the borrower data only first_name, last_name
        $firstNameMatch = strtolower($data['first_name']) === strtolower($borrowerData->first_name);
        $lastNameMatch = strtolower($data['last_name']) === strtolower($borrowerData->last_name);
        $swappedNameMatch = strtolower($data['first_name']) === strtolower($borrowerData->last_name)
            && strtolower($data['last_name']) === strtolower($borrowerData->first_name);

        if (!($firstNameMatch && $lastNameMatch) && !$swappedNameMatch) {
            return $this->failed(null, 'NID Error', 'NID information does not match with the borrower data.', 422);
        }

        $image = $request->file('nid_image');
        $imagePath = null;
        if ($image) {
            $imagePath = $this->uploadImage($image, 'nid_info', 'public');
        }

        // store nid number in the database
        $nidInformation = NidInformation::query()->create([
            'nid_number' => $data['nid'],
            'nid_image' => $imagePath,
            'status' => 1,
            'request_loan_id' => 0,
            'user_id' => $userData->id,
        ]);

        //$nidInformation first name last name
        $nidInformation->first_name = $data['first_name'];
        $nidInformation->last_name = $data['last_name'];

        return $this->success($nidInformation, 'NID Success','NID information extracted successfully.');
    }
    // show nid information
    public function show(Request $request)
    {
        // get nid information
        $nidInformation = NidInformation::query()
            ->where('nid_number', $request->nid_number)->where('status', 1)
            ->limit(1)
            ->first();
        if (!$nidInformation) {
            return $this->failed('NID information not found', 404);
        }
        return $this->success($nidInformation, 'NID information retrieved successfully.');
    }

    //get latest nid image
    public function getLatestNidImage(Request $request)
    {
        $user = auth()->user();
        $nidInformation = NidInformation::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$nidInformation) {
            return $this->failed('NID information not found', 404);
        }

        return $this->success($nidInformation, 'NID information retrieved successfully.');
    }

    //getLatestLivelinessImage
    public function getLatestLivelinessImage(Request $request)
    {
        $user = auth()->user();
        $liveliness = Liveliness::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$liveliness) {
            return $this->failed(null, 'NID information not found','NID information not found', 404);
        }

        return $this->success($liveliness, 'Liveliness', 'Liveliness information retrieved successfully.');
    }

    protected function optimizeImage($image)
    {
        // Define max file size in bytes (1024KB)
        $maxSize = 1024 * 1024;

        // Get image info
        $imageInfo = getimagesize($image);
        $mime = $imageInfo['mime'];

        // Create image resource based on MIME type
        switch ($mime) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($image);
                break;
            case 'image/png':
                $img = imagecreatefrompng($image);
                break;
            default:
                throw new \Exception('Unsupported image type');
        }

        // Get original dimensions
        $width = imagesx($img);
        $height = imagesy($img);

        // Resize if too large (e.g., max width 1200px)
        $maxWidth = 1200;
        if ($width > $maxWidth) {
            $ratio = $maxWidth / $width;
            $newWidth = $maxWidth;
            $newHeight = (int)($height * $ratio);

            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($img); // Free original image memory
            $img = $resized;
        }

        // Try reducing quality to get under 1MB
        $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.jpg';
        $quality = 90;

        do {
            imagejpeg($img, $tempPath, $quality);
            $fileSize = filesize($tempPath);
            $quality -= 5;
        } while ($fileSize > $maxSize && $quality > 10);

        imagedestroy($img); // Free resized image memory

        logger($fileSize);
        // Return the optimized image as an UploadedFile
        return new \Illuminate\Http\UploadedFile(
            $tempPath,
            $image->getClientOriginalName(),
            null,
            null,
            true // Mark it as a test file so Laravel doesn't move it again
        );
    }

}
