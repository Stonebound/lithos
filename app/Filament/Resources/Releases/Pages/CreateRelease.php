<?php

declare(strict_types=1);

namespace App\Filament\Resources\Releases\Pages;

use App\Filament\Resources\Releases\ReleaseResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateRelease extends CreateRecord
{
    protected static string $resource = ReleaseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // If a zip was uploaded, set source_type/path accordingly.
        $zip = $data['source_zip'] ?? null;
        if ($zip) {
            $data['source_type'] = 'zip';
            // FileUpload stores a relative path like 'uploads/filename.zip' on 'local' disk.
            $data['source_path'] = Storage::disk('local')->path($zip);
            unset($data['source_zip']);
        }

        // Ensure NOT NULL constraints are satisfied even when using provider (no immediate source).
        if (! isset($data['source_type']) || ! isset($data['source_path'])) {
            $placeholderDir = 'tmp/releases/'.uniqid('release_', true);
            Storage::disk('local')->makeDirectory($placeholderDir);
            $data['source_type'] = 'dir';
            $data['source_path'] = $placeholderDir;
        }

        return $data;
    }
}
