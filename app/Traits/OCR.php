<?php

namespace App\Traits;

use thiagoalessio\TesseractOCR\TesseractOCR;

trait OCR
{
    public function extractOcrData($imageFile)
    {
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
        return $data;
    }
}

