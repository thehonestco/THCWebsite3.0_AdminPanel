<?php

namespace App\Services\Resources;

use App\Services\Media\MediaUploadService;

class ResourcePayloadImageService
{
    public function __construct(
        protected MediaUploadService $mediaUploadService
    ) {
    }

    public function replaceBase64ImagesWithUrls(
        ?array $payload,
        ?int $uploadedBy = null,
        array $options = [],
        array &$storedPaths = []
    ): ?array {
        if ($payload === null) {
            return null;
        }

        return $this->walk($payload, $uploadedBy, $options, $storedPaths);
    }

    protected function walk(
        mixed $value,
        ?int $uploadedBy,
        array $options,
        array &$storedPaths,
        array $path = []
    ): mixed {
        if (is_array($value)) {
            $processed = [];

            foreach ($value as $key => $item) {
                $processed[$key] = $this->walk(
                    $item,
                    $uploadedBy,
                    $options,
                    $storedPaths,
                    [...$path, (string) $key]
                );
            }

            return $processed;
        }

        if (!is_string($value) || !$this->isBase64ImageDataUri($value)) {
            return $value;
        }

        $asset = $this->mediaUploadService->uploadBase64ImageData(
            $value,
            $uploadedBy,
            [
                'status' => $options['status'] ?? 'active',
                'title' => $options['title'] ?? 'resource-payload-image',
                'original_name' => $this->buildOriginalName($path, $value),
                'metadata' => array_merge($options['metadata'] ?? [], [
                    'module' => 'resources',
                    'field' => 'resource_payload',
                    'payload_path' => implode('.', $path),
                ]),
            ],
            $storedPaths
        );

        return $asset->url;
    }

    protected function isBase64ImageDataUri(string $value): bool
    {
        return preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,/s', $value) === 1;
    }

    protected function buildOriginalName(array $path, string $value): string
    {
        preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,/s', $value, $matches);
        $mimeType = strtolower($matches[1] ?? 'image/png');
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            default => 'png',
        };

        $baseName = empty($path) ? 'resource-payload-image' : implode('-', $path);

        return $baseName . '.' . $extension;
    }
}
