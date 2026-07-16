<?php

namespace App\Services\Media;

use App\Exceptions\MediaProcessingException;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaUploadService
{
    public function __construct(
        protected ImageConversionService $imageConversionService,
        protected VideoConversionService $videoConversionService,
        protected DocumentStorageService $documentStorageService
    ) {
    }

    public function uploadMany(array $files, ?int $uploadedBy = null, array $options = []): Collection
    {
        $storedPaths = [];

        return DB::transaction(function () use ($files, $uploadedBy, $options, &$storedPaths) {
            try {
                $assets = collect();

                foreach ($files as $index => $file) {
                    $asset = $this->uploadFile($file, $uploadedBy, [
                        'status' => $options['status'] ?? 'active',
                        'metadata' => $options['metadata'] ?? [],
                    ], $storedPaths);
                    $assets->push($asset);
                }

                return $assets;
            } catch (\Throwable $exception) {
                foreach ($storedPaths as $storedPath) {
                    Storage::disk($this->disk())->delete($storedPath);
                }

                throw $exception;
            }
        });
    }

    public function uploadFile(
        UploadedFile $file,
        ?int $uploadedBy = null,
        array $options = [],
        array &$storedPaths = []
    ): MediaAsset {
        $sourcePath = $file->getRealPath();

        if (!$sourcePath) {
            throw new MediaProcessingException('Uploaded file could not be processed.');
        }

        $mediaType = $this->determineMediaType($file);
        $directory = $this->buildDirectory($mediaType);
        $baseName = Str::lower(Str::uuid()->toString());
        $convertedExtension = match ($mediaType) {
            'image' => 'webp',
            'video' => 'webm',
            default => strtolower((string) $file->getClientOriginalExtension()) ?: 'bin',
        };
        $fileName = $baseName . '.' . $convertedExtension;
        $storagePath = $directory . '/' . $fileName;
        $temporaryTarget = storage_path('app/tmp/' . $fileName);

        if (!is_dir(dirname($temporaryTarget))) {
            mkdir(dirname($temporaryTarget), 0777, true);
        }

        $conversionResult = match ($mediaType) {
            'image' => $this->imageConversionService->convertToWebp($sourcePath, $temporaryTarget),
            'video' => $this->videoConversionService->convertToWebm($sourcePath, $temporaryTarget),
            default => $this->documentStorageService->passthrough(
                $sourcePath,
                $temporaryTarget,
                $file->getMimeType(),
                $file->getClientOriginalExtension()
            ),
        };

        $stream = fopen($temporaryTarget, 'r');

        if (!$stream) {
            @unlink($temporaryTarget);
            throw new MediaProcessingException('Converted media file could not be opened for upload.');
        }

        $uploaded = Storage::disk($this->disk())->put(
            $storagePath,
            $stream,
            [
                'ContentType' => $conversionResult['mime_type'],
            ]
        );

        fclose($stream);

        if (!$uploaded) {
            @unlink($temporaryTarget);
            throw new MediaProcessingException('Converted media file could not be uploaded to storage.');
        }

        $storedPaths[] = $storagePath;
        $sizeBytes = filesize($temporaryTarget) ?: 0;
        $url = Storage::disk($this->disk())->url($storagePath);
        @unlink($temporaryTarget);

        $asset = MediaAsset::create([
            'original_name' => $file->getClientOriginalName(),
            'title' => $options['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'media_type' => $mediaType,
            'status' => $options['status'] ?? 'active',
            'disk' => $this->disk(),
            'directory' => $directory,
            'file_name' => $fileName,
            'path' => $storagePath,
            'url' => $url,
            'source_extension' => strtolower((string) $file->getClientOriginalExtension()),
            'source_mime_type' => $file->getMimeType(),
            'converted_extension' => $conversionResult['extension'],
            'converted_mime_type' => $conversionResult['mime_type'],
            'size_bytes' => $sizeBytes,
            'width' => $conversionResult['width'] ?? null,
            'height' => $conversionResult['height'] ?? null,
            'duration_seconds' => $conversionResult['duration_seconds'] ?? null,
            'processing_status' => 'ready',
            'metadata' => ($options['metadata'] ?? []) ?: null,
            'created_by' => $uploadedBy,
        ]);

        $asset->update([
            'media_code' => 'MC-' . str_pad((string) $asset->id, 3, '0', STR_PAD_LEFT),
        ]);

        return $asset->fresh();
    }

    public function uploadBase64ImageData(
        string $dataUri,
        ?int $uploadedBy = null,
        array $options = [],
        array &$storedPaths = []
    ): MediaAsset {
        if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $dataUri, $matches)) {
            throw new MediaProcessingException('The provided base64 payload is not a valid image data URI.');
        }

        $mimeType = strtolower($matches[1]);
        $binary = base64_decode($matches[2], true);

        if ($binary === false) {
            throw new MediaProcessingException('The provided base64 image data could not be decoded.');
        }

        $extension = $this->mimeToExtension($mimeType);

        if (!$extension) {
            throw new MediaProcessingException('This base64 image format is not supported for upload.');
        }

        $sourceDirectory = storage_path('app/tmp');

        if (!is_dir($sourceDirectory)) {
            mkdir($sourceDirectory, 0777, true);
        }

        $sourceFileName = 'resource-payload-' . Str::lower(Str::uuid()->toString()) . '.' . $extension;
        $sourcePath = $sourceDirectory . '/' . $sourceFileName;

        if (file_put_contents($sourcePath, $binary) === false) {
            throw new MediaProcessingException('The provided base64 image could not be prepared for upload.');
        }

        try {
            $uploadedFile = new UploadedFile(
                $sourcePath,
                $options['original_name'] ?? $sourceFileName,
                $mimeType,
                null,
                true
            );

            return $this->uploadFile($uploadedFile, $uploadedBy, $options, $storedPaths);
        } finally {
            @unlink($sourcePath);
        }
    }

    protected function buildDirectory(string $mediaType): string
    {
        $folder = match ($mediaType) {
            'image' => 'images',
            'video' => 'videos',
            default => 'files',
        };

        return trim((string) config('media.base_directory', 'media-center'), '/')
            . '/' . $folder . '/' . now()->format('Y/m');
    }

    protected function disk(): string
    {
        return (string) config('media.disk', config('filesystems.default'));
    }

    protected function determineMediaType(UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if ($mimeType === 'application/pdf') {
            return 'pdf';
        }

        return 'file';
    }

    protected function mimeToExtension(string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            default => null,
        };
    }
}
