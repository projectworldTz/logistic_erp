<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class LandingImageUploadService
{
    public function store(UploadedFile $file, string $purpose): string
    {
        $manager = new ImageManager(new Driver);

        $maxDimension = $purpose === 'avatar' ? 256 : 1600;

        $image = $manager->read($file->getRealPath())
            ->scaleDown(width: $maxDimension, height: $maxDimension);

        $path = "landing/{$purpose}-".Str::random(8).'.png';

        Storage::disk('public')->put($path, (string) $image->toPng());

        return $path;
    }
}
