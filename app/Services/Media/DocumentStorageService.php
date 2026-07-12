<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;

class DocumentStorageService
{
    public function passthrough(string $sourcePath, string $targetPath, ?string $mimeType, ?string $extension): array
    {
        if (!@copy($sourcePath, $targetPath)) {
            throw new MediaProcessingException('File could not be prepared for upload.');
        }

        return [
            'mime_type' => $mimeType ?: 'application/octet-stream',
            'extension' => $extension ?: 'bin',
        ];
    }
}
