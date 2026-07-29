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

        $mediaType = $this->determineMediaType($file->getMimeType());

        $conversion = $this->convertAndStore(
            $sourcePath,
            $mediaType,
            (string) $file->getClientOriginalExtension(),
            $file->getMimeType(),
            $storedPaths
        );

        $asset = MediaAsset::create([
            'original_name' => $file->getClientOriginalName(),
            'title' => $options['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'media_type' => $mediaType,
            'status' => $options['status'] ?? 'active',
            'disk' => $this->disk(),
            'directory' => $conversion['directory'],
            'file_name' => $conversion['file_name'],
            'path' => $conversion['path'],
            'key' => $conversion['path'],
            'url' => $conversion['url'],
            'source_extension' => strtolower((string) $file->getClientOriginalExtension()),
            'source_mime_type' => $file->getMimeType(),
            'converted_extension' => $conversion['extension'],
            'converted_mime_type' => $conversion['mime_type'],
            'size_bytes' => $conversion['size_bytes'],
            'width' => $conversion['width'] ?? null,
            'height' => $conversion['height'] ?? null,
            'duration_seconds' => $conversion['duration_seconds'] ?? null,
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

    /**
     * Register a media asset for a file the frontend has already PUT directly to S3
     * via a presigned URL. Verifies the object actually exists (HeadObject) before
     * creating the record. Image/video files are marked "processing" so the caller
     * can dispatch a conversion job; every other type is finalized immediately since
     * it is stored as-is with no conversion step.
     */
    public function createPendingFromKey(
        string $key,
        string $mimeType,
        ?string $originalName = null,
        ?int $uploadedBy = null,
        array $options = []
    ): MediaAsset {
        $disk = $this->disk();

        if (!Storage::disk($disk)->exists($key)) {
            throw new MediaProcessingException("Uploaded file was not found in storage for key: {$key}");
        }

        $mediaType = $this->determineMediaType($mimeType);
        $extension = strtolower((string) pathinfo($key, PATHINFO_EXTENSION)) ?: ($this->mimeToExtension($mimeType) ?? 'bin');
        $originalName = $originalName ?: basename($key);
        $directory = trim((string) dirname($key), '.') ?: '';

        $needsConversion = ($mediaType === 'image' && $mimeType !== 'image/webp')
            || (
                $mediaType === 'video'
                && $mimeType !== 'video/webm'
                && config('media.video.conversion_enabled', true)
            );

        $asset = MediaAsset::create([
            'original_name' => $originalName,
            'title' => $options['title'] ?? pathinfo($originalName, PATHINFO_FILENAME),
            'media_type' => $mediaType,
            'status' => $options['status'] ?? 'active',
            'disk' => $disk,
            'directory' => $directory,
            'file_name' => basename($key),
            'path' => $needsConversion ? null : $key,
            'key' => $key,
            'url' => $needsConversion ? null : Storage::disk($disk)->url($key),
            'source_extension' => $extension,
            'source_mime_type' => $mimeType,
            'converted_extension' => $needsConversion ? null : $extension,
            'converted_mime_type' => $needsConversion ? null : $mimeType,
            'size_bytes' => (int) Storage::disk($disk)->size($key),
            'processing_status' => $needsConversion ? 'processing' : 'ready',
            'metadata' => ($options['metadata'] ?? []) ?: null,
            'created_by' => $uploadedBy,
        ]);

        $asset->update([
            'media_code' => 'MC-' . str_pad((string) $asset->id, 3, '0', STR_PAD_LEFT),
        ]);

        return $asset->fresh();
    }

    /**
     * Download the original file referenced by $asset->key from S3, convert it
     * (image -> WebP, video -> WebM), upload the result under a new key, and
     * update the record. Called from the queued conversion job.
     */
    public function convertFromS3(MediaAsset $asset): void
    {
        if (!in_array($asset->media_type, ['image', 'video'], true)) {
            return;
        }

        $disk = $asset->disk ?: $this->disk();
        $localSourcePath = storage_path('app/tmp/' . Str::lower(Str::uuid()->toString()) . '.' . $asset->source_extension);

        if (!is_dir(dirname($localSourcePath))) {
            mkdir(dirname($localSourcePath), 0777, true);
        }

        $stream = Storage::disk($disk)->readStream($asset->key);

        if (!$stream) {
            throw new MediaProcessingException("Original file could not be read from storage for key: {$asset->key}");
        }

        file_put_contents($localSourcePath, stream_get_contents($stream));

        if (is_resource($stream)) {
            fclose($stream);
        }

        try {
            $storedPaths = [];
            $conversion = $this->convertAndStore(
                $localSourcePath,
                $asset->media_type,
                (string) $asset->source_extension,
                (string) $asset->source_mime_type,
                $storedPaths
            );

            $asset->update([
                'directory' => $conversion['directory'],
                'file_name' => $conversion['file_name'],
                'path' => $conversion['path'],
                'url' => $conversion['url'],
                'converted_extension' => $conversion['extension'],
                'converted_mime_type' => $conversion['mime_type'],
                'size_bytes' => $conversion['size_bytes'],
                'width' => $conversion['width'] ?? null,
                'height' => $conversion['height'] ?? null,
                'duration_seconds' => $conversion['duration_seconds'] ?? null,
                'processing_status' => 'ready',
            ]);
        } finally {
            @unlink($localSourcePath);
        }
    }

    /**
     * Run the WebP/WebM/passthrough conversion for a local source file and upload
     * the result to the media disk. Shared by the synchronous (multipart) upload
     * path and the async (presign -> queue job) upload path.
     */
    protected function convertAndStore(
        string $sourcePath,
        string $mediaType,
        string $originalExtension,
        ?string $originalMimeType,
        array &$storedPaths
    ): array {
        $directory = $this->buildDirectory($mediaType);
        $baseName = Str::lower(Str::uuid()->toString());
        $convertedExtension = match ($mediaType) {
            'image' => 'webp',
            'video' => 'webm',
            default => strtolower($originalExtension) ?: 'bin',
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
                $originalMimeType,
                $originalExtension
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

        return [
            'directory' => $directory,
            'file_name' => $fileName,
            'path' => $storagePath,
            'url' => $url,
            'extension' => $conversionResult['extension'],
            'mime_type' => $conversionResult['mime_type'],
            'size_bytes' => $sizeBytes,
            'width' => $conversionResult['width'] ?? null,
            'height' => $conversionResult['height'] ?? null,
            'duration_seconds' => $conversionResult['duration_seconds'] ?? null,
        ];
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
            'key' => $externalPath,
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

    protected function determineMediaType(?string $mimeType): string
    {
        $mimeType = (string) $mimeType;

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
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
