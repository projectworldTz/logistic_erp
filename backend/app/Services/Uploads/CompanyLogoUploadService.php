<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CompanyLogoUploadService
{
    public function store(UploadedFile $file, ?string $previousPath): string
    {
        $manager = new ImageManager(new Driver);

        $image = $manager->read($file->getRealPath())->scaleDown(width: 512, height: 512);

        $path = 'company-logos/'.Str::random(12).'.png';

        Storage::disk('public')->put($path, (string) $image->toPng());

        if ($previousPath && Storage::disk('public')->exists($previousPath)) {
            Storage::disk('public')->delete($previousPath);
        }

        return $path;
    }
}
