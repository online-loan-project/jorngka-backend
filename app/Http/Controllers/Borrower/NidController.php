<?php

namespace App\Http\Controllers\Borrower;

use App\Constants\ConstRequestLoanStatus;
use App\Http\Controllers\Controller;
use App\Models\Borrower;
use App\Models\NidInformation;
use App\Models\RequestLoan;
use App\Traits\TelegramNotification;
use Illuminate\Http\Request;

class NidController extends Controller
{
    use TelegramNotification;

    // store nid information
    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            'nid_image' => 'required',
        ]);

        $userData = auth()->user(); // Get the authenticated user

        // Check if the file is an image
        if (!$request->file('nid_image')) {
            return $this->failed(null,'Image Error', 'Invalid image file.', 422);
        }

        $data = $this->extractOcrData($request->file('nid_image')); // Extract OCR data

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
        if (strtolower($data['first_name']) !== strtolower($borrowerData->first_name) || strtolower($data['last_name']) !== strtolower($borrowerData->last_name)) {

            return $this->failed(null ,'NID Error','NID information does not match with the borrower data.', null, 422);
        }

        $image = $request->file('nid_image');
        $imagePath = null;
        if ($image) {
            $imagePath = $this->uploadImage($image, 'nid_info', 'public');
        }

        logger('NID Information:', $data);
        // store nid number in the database
        $nidInformation = NidInformation::query()->create([
            'nid_number' => $data['nid'],
            'nid_image' => $imagePath,
            'status' => 1,
            'request_loan_id' => 0,
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
}
