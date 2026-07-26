<?php

namespace App\Services\Media;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaPresignService
{
    public function generate(string $originalFilename, string $mimeType): array
    {
        $extension = strtolower((string) pathinfo($originalFilename, PATHINFO_EXTENSION));
        $key = $this->buildKey($extension);
        $expiry = now()->addMinutes((int) config('media.presign.expiry_minutes', 5));

        $signed = Storage::disk($this->disk())->temporaryUploadUrl($key, $expiry, [
            'ContentType' => $mimeType,
        ]);

        return [
            'original_filename' => $originalFilename,
            'key' => $key,
            'upload_url' => $signed['url'],
            'headers' => $signed['headers'] ?? [],
            'mime_type' => $mimeType,
        ];
    }

    protected function buildKey(string $extension): string
    {
        $directory = trim((string) config('media.presign.directory', 'uploads'), '/');
        $name = Str::lower(Str::uuid()->toString());

        return $directory . '/' . $name . ($extension !== '' ? '.' . $extension : '');
    }

    protected function disk(): string
    {
        return (string) config('media.disk', config('filesystems.default'));
    }
}
