<?php

namespace App\Traits;

use thiagoalessio\TesseractOCR\TesseractOCR;

trait OCR
{
    public function extractOcrData($imageFile)
    {
        $image = imagecreatefromstring(file_get_contents($imageFile));
        if ($image === false) {
            throw new \Exception('Failed to create image from string');
        }

        // Convert to grayscale
        imagefilter($image, IMG_FILTER_GRAYSCALE);

        // Enhance contrast - adjust value as needed (try values between -50 to -100)
        imagefilter($image, IMG_FILTER_CONTRAST, -80);

        // Optional: Apply brightness adjustment if needed
        // imagefilter($image, IMG_FILTER_BRIGHTNESS, 20);

        // Save the processed image
        $processedImagePath = tempnam(sys_get_temp_dir(), 'ocr_') . '.png';
        imagepng($image, $processedImagePath);

        // Free memory
        imagedestroy($image);

        // Process OCR with Tesseract
        $ocrText = (new TesseractOCR($imageFile))
            ->lang('eng+khm')  // Ensure Khmer is also included
            ->psm(6)  // Assume a single block of text
            ->oem(3)  // Use LSTM OCR Engine
            ->run();

        // Normalize text by removing unwanted spaces
        $lines = array_filter(array_map('trim', explode("\n", $ocrText)));

        // Initialize extracted data
        $data = [
            'first_name' => null,
            'last_name' => null,
            'nid' => null,
        ];

        foreach ($lines as $line) {
            // Detect MRZ name format
            if (preg_match('/([A-Z]+)<<([A-Z]+)/', $line, $matches)) {
                $data['first_name'] = str_replace('<', ' ', $matches[2]);
                $data['last_name'] = str_replace('<', ' ', $matches[1]);
            }

            // Detect Cambodia NID (starting with IDKHM)
            if (preg_match('/IDKHM\w?(\d{10,10})/', $line, $matches)) {
                $data['nid'] = substr($matches[1], 0, 9); // Ensure 9-digit NID
            }
        }

        logger('Scan : ', $data);

        // Clean up temporary file
        unlink($processedImagePath);
        return $data;
    }
}

