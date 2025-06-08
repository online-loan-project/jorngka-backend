<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class CambodianNIDService
{
    protected $apiKey;
    protected $baseUrl = 'https://api.ocr.space/parse/image';

    public function __construct()
    {
        $this->apiKey = env('OCR_SPACE_API_KEY', '');
    }

    /**
     * Extract text from NID image using OCR
     *
     * @param mixed $image
     * @return array
     * @throws Exception
     */
    public function extractTextFromNID($image): array
    {
        try {
            $response = Http::withHeaders([
                'apikey' => $this->apiKey,
            ])->attach(
                'file',
                file_get_contents($image->path()),
                $image->getClientOriginalName()
            )->post($this->baseUrl, [
                'language' => 'eng',
                'OCREngine' => 2,
                'isOverlayRequired' => 'false',
                'isTable' => 'true',
            ]);

            $data = $response->json();

            if ($response->failed()) {
                throw new Exception('OCR API request failed with status: '.$response->status());
            }

            if ($data['IsErroredOnProcessing']) {
                throw new Exception($data['ErrorMessage'][0] ?? 'OCR processing failed');
            }

            if (!isset($data['ParsedResults'][0]['ParsedText'])) {
                throw new Exception('No text was extracted from the image');
            }

            return $this->parseNIDText($data['ParsedResults'][0]['ParsedText']);
        } catch (Exception $e) {
            throw new Exception("Failed to process NID: " . $e->getMessage());
        }
    }

    /**
     * Parse the raw OCR text into structured NID data
     *
     * @param string $rawText
     * @return array
     */
    protected function parseNIDText(string $rawText): array
    {
        $data = [
            'nid' => null,
            'first_name' => null,
            'last_name' => null,
            'sex' => null,
            'nationality' => 'KHM',
        ];

        // 1. Extract ID number (9 digits)
        if (preg_match('/IDKHM(\d{9})/', $rawText, $matches)) {
            $data['nid'] = $matches[1];
        }

        // 2. Extract name (format: LAST<<FIRST)
        if (preg_match('/([A-Z]+)<<([A-Z]+)/', $rawText, $matches)) {
            $data['last_name'] = $matches[1]; // LA
            $data['first_name'] = $matches[2]; // SEAVYONG
        }

        // 3. Extract date of birth and sex from MRZ line (format: YYMMDD)
        if (preg_match('/(\d{2})(\d{2})(\d{2})([MF])/', $rawText, $matches)) {
            $data['sex'] = $matches[4]; // F
        }

        // Fallback patterns if MRZ parsing fails
        if (empty($data['nid']) && preg_match('/ID No[.:]\s*(\d{9})/i', $rawText, $matches)) {
            $data['nid'] = $matches[1];
        }

        if ((empty($data['first_name']) || empty($data['last_name'])) && preg_match('/([A-Z]+)\s+([A-Z]+)/', $rawText, $matches)) {
            $data['last_name'] = $matches[1];
            $data['first_name'] = $matches[2];
        }

        return $data;
    }

    /**
     * Validate NID data
     *
     * @param array $data
     * @return bool
     */
    public function validateNIDData(array $data): bool
    {
        // Validate required fields
        $requiredFields = ['nid', 'first_name', 'last_name', 'sex'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        // Validate ID number format (9 digits)
        if (!preg_match('/^\d{9}$/', $data['id_number'])) {
            return false;
        }

        // Validate sex
        if (!in_array($data['sex'], ['M', 'F'])) {
            return false;
        }

        return true;
    }
}