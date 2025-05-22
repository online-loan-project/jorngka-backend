<?php

namespace App\Http\Controllers;

use App\Traits\BaseApiResponse;
use App\Traits\OCR;
use App\Traits\UploadImage;

abstract class Controller
{
    //
    use BaseApiResponse, OCR, UploadImage;
}
