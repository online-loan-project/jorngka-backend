<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait UploadImage
{
    public function uploadImage(UploadedFile $image, string $directory = 'uploads', string $disk = 'public'): string
    {
        $path = $image->store($directory, $disk);
        return Storage::disk($disk)->url($path);
    }

    public function updateImage(?UploadedFile $newImage, ?string $oldImagePath, string $directory = 'uploads', string $disk = 'public'): ?string
    {
        if ($oldImagePath && Storage::disk($disk)->exists($oldImagePath)) {
            Storage::disk($disk)->delete($oldImagePath);
        }

        return $newImage ? $this->uploadImage($newImage, $directory, $disk) : null;
    }
}
