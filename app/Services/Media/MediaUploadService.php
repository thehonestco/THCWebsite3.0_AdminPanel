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

    public function createManyFromUrls(array $urls, ?int $uploadedBy = null, array $options = []): Collection
    {
        return DB::transaction(function () use ($urls, $uploadedBy, $options) {
            return collect($urls)
                ->map(fn (string $url) => $this->createFromUrl($url, $uploadedBy, [
                    'status' => $options['status'] ?? 'active',
                    'metadata' => $options['metadata'] ?? [],
                ]))
                ->values();
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

    public function createFromUrl(string $url, ?int $uploadedBy = null, array $options = []): MediaAsset
    {
        $parsedPath = (string) parse_url($url, PHP_URL_PATH);
        $originalName = basename($parsedPath) ?: ('media-' . Str::lower(Str::uuid()->toString()));
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION)) ?: 'bin';
        $title = $options['title'] ?? pathinfo($originalName, PATHINFO_FILENAME);
        $mediaType = $this->determineMediaTypeFromExtension($extension);
        $mimeType = $this->mimeTypeFromExtension($extension, $mediaType);
        $externalPath = 'external/' . Str::lower(Str::uuid()->toString()) . '.' . $extension;

        $asset = MediaAsset::create([
            'original_name' => $originalName,
            'title' => $title ?: $originalName,
            'media_type' => $mediaType,
            'status' => $options['status'] ?? 'active',
            'disk' => 'external',
            'directory' => 'external',
            'file_name' => $originalName,
            'path' => $externalPath,
            'url' => $url,
            'source_extension' => $extension,
            'source_mime_type' => $mimeType,
            'converted_extension' => $extension,
            'converted_mime_type' => $mimeType,
            'size_bytes' => 0,
            'width' => null,
            'height' => null,
            'duration_seconds' => null,
            'processing_status' => 'ready',
            'metadata' => ($options['metadata'] ?? []) ?: null,
            'created_by' => $uploadedBy,
        ]);

        $asset->update([
            'media_code' => 'MC-' . str_pad((string) $asset->id, 3, '0', STR_PAD_LEFT),
        ]);

        return $asset->fresh();
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

    protected function determineMediaTypeFromExtension(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' => 'image',
            'mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v' => 'video',
            'mp3', 'wav', 'aac', 'm4a', 'ogg', 'flac' => 'audio',
            'pdf' => 'pdf',
            default => 'file',
        };
    }

    protected function mimeTypeFromExtension(string $extension, string $mediaType): ?string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo',
            'mkv' => 'video/x-matroska',
            'm4v' => 'video/x-m4v',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'pdf' => 'application/pdf',
            default => $mediaType === 'file' ? 'application/octet-stream' : null,
        };
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
