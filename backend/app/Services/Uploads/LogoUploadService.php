<?php

namespace App\Services\Uploads;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class LogoUploadService
{
    public function store(UploadedFile $file, string $tenantSlug): string
    {
        $manager = new ImageManager(new Driver);

        $image = $manager->read($file->getRealPath())
            ->scaleDown(width: 512, height: 512);

        $path = "logos/{$tenantSlug}-".Str::random(8).'.png';

        Storage::disk('public')->put($path, (string) $image->toPng());

        return $path;
    }
}
