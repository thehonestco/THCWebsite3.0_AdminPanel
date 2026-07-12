<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;

class DocumentStorageService
{
    public function preparePdf(string $sourcePath, string $targetPath): array
    {
        if (!@copy($sourcePath, $targetPath)) {
            throw new MediaProcessingException('PDF file could not be prepared for upload.');
        }

        return [
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
        ];
    }
}
